/* ============================================================
   OdinAdmin — file manager (needs js/app.js). Markup: file-manager.html,
   styles: css/pages/file-manager.css

   FileManager  [data-file-manager]
     .fm-file[data-name][data-kind][data-ext][data-size (bytes)][data-modified (Y-m-d H:i)]
             [data-owner][data-starred="1|0"][data-dims][data-src]   one file (grid card / list row)
     [data-fm-view="grid|list"] ... view switch (radios)      [data-fm-search] ... name filter
     [data-fm-kind="…"] ........... type chips (all, image, doc, video, archive)
     [data-fm-nav="all|recent|starred"]  left navigation      [data-fm-sort="name|date|size"]
     .fm-check input .............. multi-select → .fm-bulk bar ([data-fm-bulk-delete], [data-fm-bulk-clear])
     .fm-star ..................... star / unstar             [data-fm-delete] ... delete one file
     [data-fm-upload] + input[type=file][data-fm-input] ...... upload (also drag & drop onto .fm-main)
     [data-fm-info] ............... details panel ([data-fm-field="…"] slots)
   Template: uploads are previewed in the browser only (object URLs + fake progress).
   In cms/ send the files to the upload endpoint and render the returned items with the same markup.
   ============================================================ */
(function (Odin) {
  'use strict';

  // kind → [palette class, icon]
  const KINDS = {
    image:   ['violet',  'fas fa-file-image'],
    pdf:     ['danger',  'fas fa-file-pdf'],
    doc:     ['info',    'fas fa-file-word'],
    sheet:   ['success', 'fas fa-file-excel'],
    slides:  ['warning', 'fas fa-file-powerpoint'],
    archive: ['dark',    'fas fa-file-zipper'],
    video:   ['danger',  'fas fa-file-video'],
    audio:   ['info',    'fas fa-file-audio'],
    code:    ['dark',    'fas fa-file-code'],
    other:   ['light',   'fas fa-file'],
  };
  const EXT = {
    image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'], pdf: ['pdf'], doc: ['doc', 'docx', 'txt', 'rtf', 'odt'],
    sheet: ['xls', 'xlsx', 'csv', 'ods'], slides: ['ppt', 'pptx', 'key'], archive: ['zip', 'rar', '7z', 'gz', 'tar'],
    video: ['mp4', 'mov', 'webm', 'avi'], audio: ['mp3', 'wav', 'ogg', 'm4a'], code: ['html', 'css', 'js', 'json', 'xml', 'php'],
  };
  const GROUP = { image: 'image', pdf: 'doc', doc: 'doc', sheet: 'doc', slides: 'doc', video: 'video', audio: 'video', archive: 'archive', code: 'doc', other: 'doc' };
  const LABEL = { image: 'Image', pdf: 'PDF document', doc: 'Document', sheet: 'Spreadsheet', slides: 'Presentation', archive: 'Archive', video: 'Video', audio: 'Audio', code: 'Code', other: 'File' };

  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const size = b => b >= 1e9 ? (b / 1e9).toFixed(1) + ' GB' : b >= 1e6 ? (b / 1e6).toFixed(1) + ' MB' : b >= 1e3 ? Math.round(b / 1e3) + ' KB' : b + ' B';
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const date = s => `${s.slice(8, 10)} ${MONTHS[s.slice(5, 7) - 1]} ${s.slice(0, 4)}`; // 'Y-m-d …' → '02 Oct 2026'
  const kindOf = ext => Object.keys(EXT).find(k => EXT[k].includes(ext)) || 'other';

  class FileManager extends Odin.Component {
    static selector = '[data-file-manager]';

    init() {
      this.list = this.$('.fm-files');
      this.main = this.$('.fm-main');
      this.info = this.$('[data-fm-info]');
      this.bulk = this.$('.fm-bulk');
      this.empty = this.$('.fm-empty');
      this.state = { kind: 'all', nav: 'all', query: '', sort: 'name' };

      // Selecting / starring / deleting
      this.on(this.list, 'click', e => {
        const file = e.target.closest('.fm-file');
        if (!file) return;
        if (e.target.closest('.fm-star')) return this.star(file);
        if (e.target.closest('[data-fm-delete]')) { e.preventDefault(); return this.remove([file]); }
        if (e.target.closest('.fm-check, .dropdown')) return;
        this.select(file);
      });
      this.on(this.list, 'keydown', e => {
        const file = e.target.closest('.fm-file');
        if (file && (e.key === 'Enter' || e.key === ' ') && e.target === file) { e.preventDefault(); this.select(file); }
      });
      this.on(this.list, 'change', e => { if (e.target.closest('.fm-check')) this.updateChecked(); });

      // Toolbar
      this.$$('[data-fm-view]').forEach(r => this.on(r, 'change', () => this.list.classList.toggle('is-list', r.value === 'list' && r.checked)));
      const search = this.$('[data-fm-search]');
      if (search) this.on(search, 'input', () => { this.state.query = search.value.trim().toLowerCase(); this.apply(); });
      this.$$('[data-fm-kind]').forEach(chip => this.on(chip, 'click', () => {
        this.$$('[data-fm-kind]').forEach(c => c.classList.toggle('active', c === chip));
        this.state.kind = chip.dataset.fmKind;
        this.apply();
      }));
      this.$$('[data-fm-nav]').forEach(link => this.on(link, 'click', e => {
        e.preventDefault();
        this.$$('[data-fm-nav]').forEach(l => l.classList.toggle('active', l === link));
        this.state.nav = link.dataset.fmNav;
        this.apply();
      }));
      this.$$('[data-fm-sort]').forEach(item => this.on(item, 'click', e => {
        e.preventDefault();
        this.state.sort = item.dataset.fmSort;
        const label = this.$('[data-fm-sort-label]');
        if (label) label.textContent = item.textContent.trim();
        this.sort();
      }));

      // Bulk actions
      this.on(this.$('[data-fm-bulk-clear]'), 'click', () => { this.checks().forEach(c => { c.checked = false; }); this.updateChecked(); });
      this.on(this.$('[data-fm-bulk-delete]'), 'click', () => this.remove(this.checks().filter(c => c.checked).map(c => c.closest('.fm-file'))));

      // Upload: button, file input, drag & drop
      const input = this.$('[data-fm-input]');
      this.$$('[data-fm-upload]').forEach(btn => this.on(btn, 'click', () => input.click()));
      if (input) this.on(input, 'change', () => { this.upload([...input.files]); input.value = ''; });
      let depth = 0;
      const hasFiles = e => e.dataTransfer && [...e.dataTransfer.types].includes('Files');
      this.on(this.main, 'dragenter', e => { if (!hasFiles(e)) return; e.preventDefault(); depth++; this.main.classList.add('is-dragover'); });
      this.on(this.main, 'dragover', e => { if (hasFiles(e)) e.preventDefault(); });
      this.on(this.main, 'dragleave', () => { if (--depth <= 0) { depth = 0; this.main.classList.remove('is-dragover'); } });
      this.on(this.main, 'drop', e => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        depth = 0;
        this.main.classList.remove('is-dragover');
        this.upload([...e.dataTransfer.files]);
      });

      // Details panel (slide-in on smaller screens)
      this.$$('[data-fm-info-close]').forEach(b => this.on(b, 'click', () => this.el.classList.remove('info-open')));
      this.on(document, 'keydown', e => { if (e.key === 'Escape') this.el.classList.remove('info-open'); });

      this.sort();
      const first = this.$('.fm-file.selected') || this.$('.fm-file');
      if (first) this.select(first, false);
    }

    files() { return this.$$('.fm-file'); }
    checks() { return this.$$('.fm-check input'); }

    /* ---------- Filter & sort ---------- */
    apply() {
      const { kind, nav, query } = this.state;
      const recent = this.files().map(f => f.dataset.modified).sort().reverse()[5] || '';
      let shown = 0;
      this.files().forEach(f => {
        const ok = (kind === 'all' || GROUP[f.dataset.kind] === kind)
          && (nav !== 'starred' || f.dataset.starred === '1')
          && (nav !== 'recent' || f.dataset.modified >= recent)
          && (!query || f.dataset.name.toLowerCase().includes(query));
        f.classList.toggle('is-hidden', !ok);
        if (ok) shown++;
      });
      if (this.empty) this.empty.hidden = shown > 0;
      const count = this.$('[data-fm-count]');
      if (count) count.textContent = `${shown} file${shown === 1 ? '' : 's'}`;
    }

    sort() {
      const key = this.state.sort;
      const val = f => key === 'size' ? -Number(f.dataset.size) : key === 'date' ? f.dataset.modified : f.dataset.name.toLowerCase();
      const files = this.files().sort((a, b) => {
        const x = val(a), y = val(b);
        if (key === 'date') return x < y ? 1 : x > y ? -1 : 0;
        return x < y ? -1 : x > y ? 1 : 0;
      });
      files.forEach(f => this.list.appendChild(f));
      this.apply();
    }

    /* ---------- Selection & details ---------- */
    select(file, open = true) {
      this.files().forEach(f => f.classList.toggle('selected', f === file));
      this.showInfo(file);
      if (open && window.matchMedia('(max-width: 1399.98px)').matches) this.el.classList.add('info-open');
    }

    showInfo(file) {
      if (!this.info) return;
      const d = file.dataset;
      const [tone, icon] = KINDS[d.kind] || KINDS.other;
      const set = (name, html) => this.$$(`[data-fm-field="${name}"]`).forEach(el => { el.innerHTML = html; });
      const preview = this.$('[data-fm-field="preview"]');
      if (preview) {
        preview.className = `fm-preview ${d.src ? '' : tone}`;
        preview.innerHTML = d.src ? `<img src="${esc(d.src)}" alt="">` : `<i class="${icon}"></i>`;
      }
      set('name', esc(d.name));
      set('type', `${LABEL[d.kind] || 'File'} · ${esc((d.ext || '').toUpperCase())}`);
      set('size', size(Number(d.size)));
      set('dims', esc(d.dims || '—'));
      set('modified', date(d.modified));
      set('owner', esc(d.owner || '—'));
      set('starred', d.starred === '1' ? '<i class="fas fa-star text-warning"></i> Starred' : 'Not starred');
      this.current = file;
    }

    updateChecked() {
      const checked = this.checks().filter(c => c.checked);
      this.files().forEach(f => f.classList.toggle('checked', f.querySelector('.fm-check input').checked));
      this.list.classList.toggle('has-checked', checked.length > 0);
      if (this.bulk) {
        this.bulk.classList.toggle('show', checked.length > 0);
        const n = this.bulk.querySelector('[data-fm-bulk-count]');
        if (n) n.textContent = `${checked.length} selected`;
      }
    }

    star(file) {
      const on = file.dataset.starred !== '1';
      file.dataset.starred = on ? '1' : '0';
      const btn = file.querySelector('.fm-star');
      btn.innerHTML = `<i class="${on ? 'fas' : 'far'} fa-star"></i>`;
      btn.setAttribute('aria-pressed', String(on));
      btn.setAttribute('aria-label', on ? 'Remove star' : 'Add star');
      if (file === this.current) this.showInfo(file);
      this.apply();
    }

    remove(files) {
      if (!files.length) return;
      const what = files.length === 1 ? `“${files[0].dataset.name}”` : `${files.length} files`;
      if (!confirm(`Move ${what} to the trash?`)) return;
      files.forEach(f => f.remove());
      this.updateChecked();
      this.apply();
      const next = this.$('.fm-file:not(.is-hidden)');
      if (files.includes(this.current) && next) this.select(next, false);
    }

    /* ---------- Upload (template: preview only) ---------- */
    upload(files) {
      if (!files.length) return;
      const panel = document.querySelector('.fm-uploads');
      const listEl = panel && panel.querySelector('.fm-uploads-list');
      const title = panel && panel.querySelector('[data-fm-upload-title]');
      if (panel) {
        panel.hidden = false;
        const close = panel.querySelector('[data-fm-uploads-close]');
        if (close && !close.dataset.bound) { close.dataset.bound = '1'; close.addEventListener('click', () => { panel.hidden = true; }); }
      }
      let left = files.length;
      if (title) title.textContent = `Uploading ${left} file${left === 1 ? '' : 's'}`;

      files.forEach(file => {
        const row = document.createElement('div');
        row.className = 'fm-upload';
        row.innerHTML = `<div class="fm-upload-row"><span>${esc(file.name)}</span><span data-pct>0%</span></div><div class="src-bar"><span style="width:0"></span></div>`;
        if (listEl) listEl.prepend(row);
        const bar = row.querySelector('.src-bar span');
        const pct = row.querySelector('[data-pct]');
        let p = 0;
        const timer = setInterval(() => {
          p = Math.min(100, p + 8 + Math.random() * 22);
          bar.style.width = p + '%';
          pct.textContent = Math.round(p) + '%';
          if (p < 100) return;
          clearInterval(timer);
          bar.classList.add('success');
          pct.innerHTML = '<i class="fas fa-check text-success"></i>';
          this.addFile(file);
          left--;
          if (title) title.textContent = left ? `Uploading ${left} file${left === 1 ? '' : 's'}` : 'Upload complete';
        }, 180);
      });
    }

    addFile(file) {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const kind = kindOf(ext);
      const [tone, icon] = KINDS[kind];
      const src = kind === 'image' ? URL.createObjectURL(file) : '';
      const now = new Date();
      const modified = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
      const wrap = document.createElement('div');
      wrap.innerHTML = FileManager.fileHtml({ name: file.name, kind, ext, size: file.size, modified, owner: 'You', src, tone, icon });
      const el = wrap.firstElementChild;
      this.list.prepend(el);
      this.apply();
      this.select(el, false);
    }

    /** Same markup as the server-rendered items in file-manager.html. */
    static fileHtml(f) {
      return `<div class="fm-file ${f.tone}" tabindex="0" data-name="${esc(f.name)}" data-kind="${f.kind}" data-ext="${esc(f.ext)}" data-size="${f.size}" data-modified="${f.modified}" data-owner="${esc(f.owner)}" data-starred="0"${f.src ? ` data-src="${esc(f.src)}"` : ''}>
  <label class="fm-check"><input type="checkbox" class="form-check-input check-soft" aria-label="Select ${esc(f.name)}"></label>
  <button type="button" class="fm-star" aria-pressed="false" aria-label="Add star"><i class="far fa-star"></i></button>
  <div class="fm-thumb">${f.src ? `<img src="${esc(f.src)}" alt="" loading="lazy">` : `<i class="${f.icon}"></i>`}<span class="fm-ext">${esc(f.ext)}</span></div>
  <div class="fm-file-body">
    <div class="fm-file-text"><span class="fm-name">${esc(f.name)}</span><span class="fm-meta">${size(f.size)} · just now</span></div>
    <span class="fm-col fm-col-owner">${esc(f.owner)}</span>
    <span class="fm-col fm-col-date">${date(f.modified)}</span>
    <span class="fm-col fm-col-size">${size(f.size)}</span>
    <div class="dropdown">
      <button type="button" class="icon-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="File actions"><i class="fas fa-ellipsis-v"></i></button>
      <ul class="dropdown-menu dropdown-menu-end card-action-menu">
        <li><a class="dropdown-item" href="#"><i class="fas fa-download"></i> Download</a></li>
        <li><a class="dropdown-item" href="#"><i class="fas fa-share-alt"></i> Share</a></li>
        <li><a class="dropdown-item" href="#"><i class="fas fa-i-cursor"></i> Rename</a></li>
        <li><hr class="dropdown-divider mx-2"></li>
        <li><a class="dropdown-item danger" href="#" data-fm-delete><i class="far fa-trash-alt"></i> Move to trash</a></li>
      </ul>
    </div>
  </div>
</div>`;
    }
  }

  FileManager.KINDS = KINDS;
  Odin.register('fileManager', FileManager);
  Odin.FileManager = FileManager;
})(window.Odin);
