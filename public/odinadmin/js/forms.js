/* ============================================================
   OdinAdmin — form widgets (needs js/app.js). Markup reference: form-elements.html
   Plain JavaScript. Libraries (loaded before this file, each optional):
   Tom Select, flatpickr (+ l10n files), Dropzone 5.

   Components (all extend Odin.Component, created automatically):
     SelectField     select[data-ts]            Tom Select. data-ts = "default" | "avatar" | "icon"
                       Required single select: start with <option value=""></option> (needs value="",
                       otherwise Tom Select adds its own empty option at the end and validation passes)
                       data-placeholder="…"  data-clear="true"  data-create="true" (new tags)
                       data-search="false"   data-max="3" (multi limit)
                       avatar: <option data-initials="NM" data-tone="c2">   icon: <option data-icon="fas fa-…">
     DatePicker      input[data-fp]             flatpickr. data-fp = "date" | "datetime" | "time" | "range" | "multiple"
                       data-min-date, data-default-date ("a, b" for range / multiple)
                       Language: DatePicker.setLanguage('ky'); radios name="fp-lang" and the
                       top-bar .lang-menu [lang] items call it.
     Uploader        [data-dropzone]            Dropzone. data-previews="#id" data-max-files data-max-size (MB) data-accept data-url
     SlugField       [data-slug-source="#slug"] fills the slug input while it is untouched
     CharCounter     [maxlength][data-count="#id"]
     PasswordToggle  [data-toggle-password="#id"]
     Indeterminate   input[data-indeterminate]
     FormValidator   form.needs-validation      Bootstrap validation (demo: never posts)
   ============================================================ */
(function (Odin) {
  'use strict';

  /* ============================================================
     SelectField — Tom Select
     ============================================================ */
  class SelectField extends Odin.Component {
    static selector = 'select[data-ts]';

    init() {
      if (!window.TomSelect) return;
      const multiple = this.el.multiple;
      const search = this.data('search', true);
      const plugins = {};
      if (multiple) plugins.remove_button = { title: 'Remove' };
      if (this.data('clear', false)) plugins.clear_button = { title: 'Clear' };
      if (!multiple && search) plugins.dropdown_input = {};

      const settings = {
        plugins,
        placeholder: this.data('placeholder', ''),
        create: this.data('create', false),
        persist: false,
        maxItems: multiple ? this.data('max', null) : 1,
        maxOptions: null,
        allowEmptyOption: false,
        hidePlaceholder: false,
        render: this.renderers(this.el.dataset.ts),
      };
      if (!search) settings.controlInput = null;

      this.ts = new TomSelect(this.el, settings);
    }

    /** Option/item templates for data-ts="avatar" and "icon". */
    renderers(type) {
      if (type !== 'avatar' && type !== 'icon') return {};
      const media = (data, escape) => {
        const opt = data.$option ? data.$option.dataset : {};
        let lead = '';
        if (type === 'avatar') {
          lead = `<span class="av-sm av-xs ${escape(opt.tone || 'c2')}">${escape(opt.initials || '')}</span>`;
        } else if (opt.icon) {
          lead = `<i class="ts-icon ${escape(opt.icon)}"></i>`;
        }
        return `<div><span class="ts-media">${lead}<span>${escape(data.text)}</span></span></div>`;
      };
      return { option: media, item: media };
    }

    /** Re-reads the <select> (e.g. after a native form reset). */
    sync() {
      if (this.ts) this.ts.sync();
    }

    focus() {
      if (this.ts) this.ts.focus();
    }
  }

  /* ============================================================
     DatePicker — flatpickr with a shared, switchable language
     ============================================================ */
  class DatePicker extends Odin.Component {
    static selector = 'input[data-fp]';
    static instances = new Set();
    static language = document.documentElement.lang || 'en';

    // flatpickr has no Kyrgyz locale — this one follows the format of its l10n files.
    static KY = {
      weekdays: {
        shorthand: ['Жк', 'Дш', 'Шш', 'Шр', 'Бш', 'Жм', 'Иш'],
        longhand: ['Жекшемби', 'Дүйшөмбү', 'Шейшемби', 'Шаршемби', 'Бейшемби', 'Жума', 'Ишемби'],
      },
      months: {
        shorthand: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
        longhand: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август',
                   'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
      },
      firstDayOfWeek: 1,
      ordinal: () => '',
      rangeSeparator: ' — ',
      weekAbbreviation: 'Апт.',
      scrollTitle: 'Чоңойтуу үчүн айлантыңыз',
      toggleTitle: 'Которуу үчүн басыңыз',
      amPM: ['ТЧ', 'ТК'],
      yearAriaLabel: 'Жыл',
      monthAriaLabel: 'Ай',
      hourAriaLabel: 'Саат',
      minuteAriaLabel: 'Мүнөт',
      time_24hr: true,
    };

    static PRESETS = {
      date:     { altInput: true, altFormat: 'j F Y', dateFormat: 'Y-m-d' },
      datetime: { altInput: true, altFormat: 'j F Y, H:i', dateFormat: 'Y-m-d H:i',
                  enableTime: true, time_24hr: true, minuteIncrement: 5 },
      time:     { enableTime: true, noCalendar: true, time_24hr: true, dateFormat: 'H:i', minuteIncrement: 15 },
      range:    { altInput: true, altFormat: 'j M Y', dateFormat: 'Y-m-d', mode: 'range' },
      multiple: { altInput: true, altFormat: 'j M', dateFormat: 'Y-m-d', mode: 'multiple', conjunction: ', ' },
    };

    static locale(code) {
      if (code === 'ky') return DatePicker.KY;
      if (code !== 'en' && window.flatpickr && flatpickr.l10ns[code]) return flatpickr.l10ns[code];
      return 'default';
    }

    /** Re-creates every picker in `code`, keeps the selected dates and syncs the language controls. */
    static setLanguage(code) {
      DatePicker.language = code;
      DatePicker.instances.forEach(picker => picker.rebuild());
      document.querySelectorAll('input[name="fp-lang"]').forEach(r => { r.checked = r.value === code; });
      document.querySelectorAll('.lang-menu [lang]').forEach(a => a.classList.toggle('active', a.lang === code));
      const current = document.querySelector('.tb-lang .lang-code');
      if (current) current.textContent = code.toUpperCase();
    }

    init() {
      if (!window.flatpickr) return;
      this.baseClass = this.el.className;
      this.create();
      DatePicker.instances.add(this);
    }

    config(dates) {
      const type = this.el.dataset.fp;
      const config = Object.assign({}, DatePicker.PRESETS[type] || DatePicker.PRESETS.date, {
        locale: DatePicker.locale(DatePicker.language),
        altInputClass: `${this.baseClass} fp-alt`,
        disableMobile: true, // keep the themed, translated picker on phones too
      });
      const preset = this.data('defaultDate', null);
      if (dates && dates.length) config.defaultDate = dates;
      else if (preset !== null) config.defaultDate = config.mode ? String(preset).split(',').map(s => s.trim()) : preset;
      if (this.el.dataset.minDate) config.minDate = this.el.dataset.minDate;
      if (type === 'range') config.showMonths = window.innerWidth >= 768 ? 2 : 1;
      return config;
    }

    create(dates) {
      this.fp = flatpickr(this.el, this.config(dates));
    }

    rebuild() {
      const dates = this.fp ? this.fp.selectedDates.slice() : [];
      if (this.fp) this.fp.destroy();
      this.create(dates);
    }
  }

  // Language controls: radios on the page + the top-bar language menu (demo)
  Odin.delegate('change', 'input[name="fp-lang"]', (e, radio) => {
    if (radio.checked) DatePicker.setLanguage(radio.value);
  });
  if (window.flatpickr && document.querySelector('input[data-fp]')) {
    Odin.delegate('click', '.lang-menu [lang]', (e, item) => {
      e.preventDefault();
      DatePicker.setLanguage(item.lang);
    });
  }

  /* ============================================================
     Uploader — Dropzone (preview only in the template)
     ============================================================ */
  class Uploader extends Odin.Component {
    static selector = '[data-dropzone]';
    static defaults = { accept: 'image/*', maxSize: 5 };
    static TEMPLATE =
      '<div class="dz-preview">' +
        '<div class="dz-thumb"><img data-dz-thumbnail alt=""><i class="far fa-file"></i></div>' +
        '<span class="dz-cover-badge">Cover</span>' +
        '<div class="dz-meta"><span class="dz-name" data-dz-name></span><span class="dz-size" data-dz-size></span></div>' +
        '<div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>' +
        '<div class="dz-error-message" data-dz-errormessage></div>' +
        '<button type="button" class="dz-remove-btn" data-dz-remove aria-label="Remove file"><i class="fas fa-times"></i></button>' +
      '</div>';

    init() {
      if (!window.Dropzone) return;
      Dropzone.autoDiscover = false;
      const maxFiles = this.data('maxFiles', null);

      this.dz = new Dropzone(this.el, {
        // Template: files are only previewed. In cms/ set data-url to the upload route, send the CSRF
        // token in `headers`, and call processQueue() on submit (or use autoProcessQueue: true).
        url: this.data('url', '#'),
        autoProcessQueue: false,
        acceptedFiles: this.data('accept', this.options.accept),
        maxFilesize: this.data('maxSize', this.options.maxSize),
        maxFiles,
        thumbnailWidth: 480,
        thumbnailHeight: maxFiles === 1 ? 270 : 480,
        previewsContainer: this.data('previews', null),
        previewTemplate: Uploader.TEMPLATE,
        dictInvalidFileType: 'This file type is not allowed.',
        dictFileTooBig: 'Too big ({{filesize}} MB). Max {{maxFilesize}} MB.',
        dictMaxFilesExceeded: 'Only {{maxFiles}} file(s) allowed.',
      });

      // Single-image field: a new file replaces the old one
      if (maxFiles === 1) {
        this.dz.on('addedfile', file => this.dz.files.filter(f => f !== file).forEach(f => this.dz.removeFile(f)));
      }

      // Keyboard access: the area acts as a button
      this.el.tabIndex = 0;
      this.el.setAttribute('role', 'button');
      this.on(this.el, 'keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.dz.hiddenFileInput.click(); }
      });
    }

    get files() { return this.dz ? this.dz.files : []; }
  }

  /* ============================================================
     Small helpers
     ============================================================ */
  class SlugField extends Odin.Component {
    static selector = '[data-slug-source]';

    // Latin, Kyrgyz / Russian Cyrillic and accented letters → a-z0-9-
    static CYR = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z', и: 'i', й: 'y',
      к: 'k', л: 'l', м: 'm', н: 'n', ң: 'ng', о: 'o', ө: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u',
      ү: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e',
      ю: 'yu', я: 'ya', ı: 'i', ß: 'ss' };

    static slugify(text) {
      return text.toLowerCase()
        .replace(/[Ѐ-ӿıß]/g, ch => (SlugField.CYR[ch] !== undefined ? SlugField.CYR[ch] : ''))
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    }

    init() {
      this.target = document.querySelector(this.el.dataset.slugSource);
      if (!this.target) return;
      this.touched = false;
      this.on(this.target, 'input', () => { this.touched = this.target.value !== ''; });
      this.on(this.el, 'input', () => { if (!this.touched) this.target.value = SlugField.slugify(this.el.value); });
    }
  }

  class CharCounter extends Odin.Component {
    static selector = '[maxlength][data-count]';

    init() {
      this.output = document.querySelector(this.el.dataset.count);
      this.max = parseInt(this.el.getAttribute('maxlength'), 10);
      if (!this.output || !this.max) return;
      this.on(this.el, 'input', () => this.update());
      this.update();
    }

    update() {
      const length = this.el.value.length;
      this.output.textContent = `${length} / ${this.max}`;
      this.output.classList.toggle('near', length >= this.max * 0.9);
    }
  }

  class PasswordToggle extends Odin.Component {
    static selector = '[data-toggle-password]';

    init() {
      this.input = document.querySelector(this.el.dataset.togglePassword);
      if (this.input) this.on(this.el, 'click', () => this.toggle());
    }

    toggle() {
      const show = this.input.type === 'password';
      this.input.type = show ? 'text' : 'password';
      this.el.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      const icon = this.el.querySelector('i');
      if (icon) icon.className = show ? 'far fa-eye-slash' : 'far fa-eye';
    }
  }

  class Indeterminate extends Odin.Component {
    static selector = 'input[data-indeterminate]';
    init() { this.el.indeterminate = true; }
  }

  class FormValidator extends Odin.Component {
    static selector = 'form.needs-validation';

    init() {
      this.success = this.$('[data-form-success]');
      this.on(this.el, 'submit', e => this.submit(e));
      this.on(this.el, 'reset', () => this.reset());
    }

    submit(e) {
      e.preventDefault(); // template: nothing is posted
      const valid = this.el.checkValidity();
      this.el.classList.add('was-validated');
      if (this.success) this.success.hidden = !valid;
      if (!valid) this.focusFirstInvalid();
      this.emit(valid ? 'form-valid' : 'form-invalid');
    }

    focusFirstInvalid() {
      const first = this.$(':invalid');
      if (!first) return;
      const select = Odin.get(first, 'select');
      if (select) select.focus();
      else first.focus();
    }

    reset() {
      this.el.classList.remove('was-validated');
      if (this.success) this.success.hidden = true;
      // Native reset runs after this event: then sync widgets with the restored values
      setTimeout(() => {
        this.$$('select[data-ts]').forEach(el => { const s = Odin.get(el, 'select'); if (s) s.sync(); });
        this.$$('[data-count]').forEach(el => { const c = Odin.get(el, 'charCounter'); if (c) c.update(); });
      }, 0);
    }
  }

  Odin.register('select', SelectField);
  Odin.register('datePicker', DatePicker);
  Odin.register('uploader', Uploader);
  Odin.register('slugField', SlugField);
  Odin.register('charCounter', CharCounter);
  Odin.register('passwordToggle', PasswordToggle);
  Odin.register('indeterminate', Indeterminate);
  Odin.register('formValidator', FormValidator);

  Object.assign(Odin, { SelectField, DatePicker, Uploader, SlugField, CharCounter, PasswordToggle, FormValidator });
})(window.Odin);
