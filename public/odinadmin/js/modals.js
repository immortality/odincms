/* ============================================================
   OdinAdmin — modals & dialogs (needs js/app.js). Markup reference: modals.html
   Bootstrap modals / offcanvas need no JS of their own (data-bs-toggle="modal").
   Dialogs use SweetAlert2 (load sweetalert2.min.js, not the ".all" build).

   Dialog API (all return Promises):
     Odin.Dialog.success(title, text)   .error()  .warning()  .info()
     Odin.Dialog.confirm({ title, text, html, confirmText, cancelText, danger, icon })  → true / false
     Odin.Dialog.prompt({ title, label, value, placeholder, confirmText, validate })     → string / null
     Odin.Dialog.fire({...any SweetAlert2 option})   themed Swal.fire

   Components:
     ConfirmTrigger  [data-confirm="Message"]  asks before the click goes through.
                       data-confirm-title, data-confirm-button ("Delete"), data-confirm-type="primary"
                       (default: danger). Links then open, submit buttons submit their form;
                       otherwise listen for the bubbling "odin:confirmed" event.
     DialogTrigger   [data-dialog="success|error|warning|info|question"] data-title data-text
     ModalFocus      .modal — focuses [autofocus] inside a Bootstrap modal when it opens
   Helper:  Odin.modal('#id') → bootstrap.Modal instance (show(), hide())
   ============================================================ */
(function (Odin) {
  'use strict';

  /* ---------- Dialog: themed SweetAlert2 ---------- */
  class Dialog {
    static CLASSES = {
      confirmButton: 'btn-brand',
      denyButton: 'btn-soft danger',
      cancelButton: 'btn-soft',
    };

    static get swal() {
      if (!window.Swal) throw new Error('Odin.Dialog needs SweetAlert2 (sweetalert2.min.js)');
      if (!Dialog.mixin) {
        Dialog.mixin = Swal.mixin({
          buttonsStyling: false,
          reverseButtons: true,      // Cancel left, main action right — same as modal footers
          customClass: Dialog.CLASSES,
          confirmButtonText: 'OK',
          cancelButtonText: 'Cancel',
        });
      }
      return Dialog.mixin;
    }

    /** Themed Swal.fire. customClass is merged, not replaced. */
    static fire(options = {}) {
      const customClass = Object.assign({}, Dialog.CLASSES, options.customClass);
      // Opened from inside a Bootstrap modal / side panel: mount the dialog in it, otherwise
      // Bootstrap's focus trap pulls focus back and the dialog can't be used with the keyboard.
      const target = options.target || document.querySelector('.modal.show, .offcanvas.show') || 'body';
      return Dialog.swal.fire(Object.assign({ target }, options, { customClass }));
    }

    static message(icon, title, text, options = {}) {
      return Dialog.fire(Object.assign({ icon, title, text }, options));
    }
    static success(title, text, options) { return Dialog.message('success', title, text, options); }
    static error(title, text, options)   { return Dialog.message('error', title, text, options); }
    static warning(title, text, options) { return Dialog.message('warning', title, text, options); }
    static info(title, text, options)    { return Dialog.message('info', title, text, options); }

    static async confirm({ title = 'Are you sure?', text, html, confirmText = 'Yes, continue',
                           cancelText = 'Cancel', danger = true, icon = 'warning', ...rest } = {}) {
      const result = await Dialog.fire(Object.assign({
        icon, title, text, html,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        focusCancel: danger,       // a destructive action should not be one Enter key away
        customClass: danger ? { confirmButton: 'btn-brand danger' } : {},
      }, rest));
      return result.isConfirmed;
    }

    static async prompt({ title, label, value = '', placeholder = '', confirmText = 'Save',
                          validate, input = 'text', ...rest } = {}) {
      const result = await Dialog.fire(Object.assign({
        title,
        input,
        inputLabel: label,
        inputValue: value,
        inputPlaceholder: placeholder,
        showCancelButton: true,
        confirmButtonText: confirmText,
        inputValidator: validate,
      }, rest));
      return result.isConfirmed ? result.value : null;
    }
  }

  /* ---------- [data-confirm] ---------- */
  class ConfirmTrigger extends Odin.Component {
    static selector = '[data-confirm]';

    init() {
      // Capture phase so the confirm runs before other click handlers on the element
      this.on(this.el, 'click', e => this.handle(e), true);
    }

    async handle(e) {
      if (this.bypass) { this.bypass = false; return; }
      e.preventDefault();
      e.stopImmediatePropagation();

      const confirmed = await Dialog.confirm({
        title: this.data('confirmTitle', 'Are you sure?'),
        text: this.el.dataset.confirm || undefined,
        confirmText: this.data('confirmButton', 'Yes, continue'),
        danger: this.data('confirmType', 'danger') === 'danger',
      });
      if (!confirmed) return;

      this.emit('confirmed');
      this.proceed();
    }

    /** Continue the original action: follow a link or submit the form. */
    proceed() {
      const el = this.el;
      if (el.tagName === 'A' && el.getAttribute('href') && el.getAttribute('href') !== '#') {
        window.location.href = el.href;
      } else if (el.form && (el.type === 'submit' || el.tagName === 'BUTTON' && !el.type)) {
        el.form.requestSubmit(el);
      } else if (el.dataset.confirmReplay !== undefined) {
        this.bypass = true;       // re-run the original click (other listeners)
        el.click();
      }
    }
  }

  /* ---------- [data-dialog] ---------- */
  class DialogTrigger extends Odin.Component {
    static selector = '[data-dialog]';

    init() {
      this.on(this.el, 'click', e => {
        e.preventDefault();
        Dialog.message(this.el.dataset.dialog, this.data('title', ''), this.data('text', ''));
      });
    }
  }

  /* ---------- Bootstrap modal: focus [autofocus] when shown ---------- */
  class ModalFocus extends Odin.Component {
    static selector = '.modal';

    init() {
      this.on(this.el, 'shown.bs.modal', () => {
        const target = this.$('[autofocus]');
        if (target) target.focus();
      });
    }
  }

  Odin.register('confirmTrigger', ConfirmTrigger);
  Odin.register('dialogTrigger', DialogTrigger);
  Odin.register('modalFocus', ModalFocus);

  Odin.Dialog = Dialog;
  Odin.modal = selector => window.bootstrap.Modal.getOrCreateInstance(
    typeof selector === 'string' ? document.querySelector(selector) : selector
  );
})(window.Odin);
