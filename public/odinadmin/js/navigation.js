/* ============================================================
   OdinAdmin — menu editor (needs js/app.js and SortableJS).
   Markup reference: navigation.html, styles: css/components/nav-tree.css

   NavEditor  [data-nav-editor]   one per menu area (Header, Footer, Sidebar)
     ul.nav-tree ................. the tree; every li.nt-item has a ul.nt-list
     li.nt-item data-* ........... the item's values:
                                   data-id, data-label-en|ky|ru, data-type (page | category | custom),
                                   data-page, data-category, data-url, data-target (_self | _blank),
                                   data-icon, data-active ("1" | "0")
     form[data-nav-form] ......... edit panel; inputs carry data-field="<data key>"
                                   (label-en, type, page, url, …); [data-show-for="page"] blocks
                                   are shown only for that link type
     [data-nav-empty] ............ shown while no item is selected
     [data-nav-parent] ........... "Top level" / "Under: …" text in the form
     [data-nav-add] [data-nav-delete] [data-nav-expand] [data-nav-collapse]
     [data-nav-save] + [data-nav-saved] (alert shown after saving)

   Drag an item by its handle to reorder it or drop it into another item's
   list to nest it — any depth. toJSON() returns the tree:
     [{ id, label: {en, ky, ru}, type, page, category, url, target, icon, active, children: [...] }]
   In cms/ post that JSON on save instead of the demo alert.
   ============================================================ */
(function (Odin) {
  'use strict';

  const LANGS = ['en', 'ky', 'ru'];
  const FIELDS = ['label-en', 'label-ky', 'label-ru', 'type', 'page', 'category', 'url', 'target', 'icon', 'active'];
  const dataKey = field => field.replace(/-([a-z])/g, (m, c) => c.toUpperCase()); // label-en → labelEn

  let newId = 0;

  class NavEditor extends Odin.Component {
    static selector = '[data-nav-editor]';

    init() {
      this.tree = this.$('.nav-tree');
      this.form = this.$('[data-nav-form]');
      this.empty = this.$('[data-nav-empty]');
      this.saved = this.$('[data-nav-saved]');
      this.group = 'nav-' + (this.el.id || Math.random().toString(36).slice(2));
      this.selected = null;

      [this.tree, ...this.tree.querySelectorAll('.nt-list')].forEach(list => this.sortable(list));
      this.tree.querySelectorAll('.nt-item').forEach(item => this.refresh(item));

      // Tree clicks: toggle, select
      this.on(this.tree, 'click', e => {
        const toggle = e.target.closest('.nt-toggle');
        if (toggle) return this.toggle(toggle.closest('.nt-item'));
        const label = e.target.closest('.nt-label');
        if (label) this.select(label.closest('.nt-item'));
      });

      this.on(this.$('[data-nav-add]'), 'click', () => this.add());
      this.on(this.$('[data-nav-expand]'), 'click', () => this.setAll(false));
      this.on(this.$('[data-nav-collapse]'), 'click', () => this.setAll(true));
      this.on(this.$('[data-nav-save]'), 'click', () => this.save());
      this.on(this.$('[data-nav-delete]'), 'click', () => this.remove());

      this.on(this.form, 'submit', e => { e.preventDefault(); this.apply(); });
      this.on(this.form, 'change', e => { if (e.target.dataset.field === 'type') this.showType(e.target.value); });

      this.showForm(false);
    }

    /* ---------- Drag & drop ---------- */
    sortable(list) {
      if (!window.Sortable) return;
      Sortable.create(list, {
        group: this.group,
        handle: '.nt-handle',
        draggable: '.nt-item',
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        emptyInsertThreshold: 10,
        onStart: () => this.tree.classList.add('is-dragging'),
        onEnd: e => {
          this.tree.classList.remove('is-dragging');
          this.tree.querySelectorAll('.nt-item').forEach(item => this.refresh(item));
          if (this.selected === e.item) this.showParent();
          this.dirty();
        },
      });
    }

    /* ---------- Tree state ---------- */
    children(item) { return item.querySelector(':scope > .nt-list'); }

    refresh(item) {
      const has = this.children(item).children.length > 0;
      item.classList.toggle('has-children', has);
      if (!has) item.classList.remove('collapsed');
      const toggle = item.querySelector(':scope > .nt-row .nt-toggle');
      const open = has && !item.classList.contains('collapsed');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Collapse' : 'Expand');
      item.classList.toggle('is-hidden', item.dataset.active === '0');
    }

    toggle(item, collapse = !item.classList.contains('collapsed')) {
      if (!item.classList.contains('has-children')) return;
      item.classList.toggle('collapsed', collapse);
      this.refresh(item);
    }

    setAll(collapse) {
      this.tree.querySelectorAll('.nt-item.has-children').forEach(item => this.toggle(item, collapse));
    }

    /* ---------- Selection & form ---------- */
    select(item) {
      if (this.selected) this.selected.classList.remove('selected');
      this.selected = item;
      item.classList.add('selected');

      FIELDS.forEach(field => {
        const value = item.dataset[dataKey(field)] || '';
        this.form.querySelectorAll(`[data-field="${field}"]`).forEach(input => {
          if (input.type === 'radio') input.checked = input.value === (value || 'custom');
          else if (input.type === 'checkbox') input.checked = value !== '0';
          else input.value = field === 'target' ? (value || '_self') : value;
        });
      });
      this.showType(item.dataset.type || 'custom');
      this.showParent();
      this.form.classList.remove('was-validated');

      // Always start on the first language tab
      const firstTab = this.form.querySelector('.tabs [data-bs-toggle="tab"]');
      if (firstTab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(firstTab).show();
      this.showForm(true);
    }

    showForm(show) {
      this.form.hidden = !show;
      if (this.empty) this.empty.hidden = show;
    }

    showType(type) {
      this.form.querySelectorAll('[data-show-for]').forEach(block => {
        const on = block.dataset.showFor === type;
        block.hidden = !on;
        block.querySelectorAll('input, select').forEach(input => { input.disabled = !on; });
      });
    }

    showParent() {
      const info = this.form.querySelector('[data-nav-parent]');
      if (!info || !this.selected) return;
      const parent = this.selected.parentElement.closest('.nt-item');
      info.textContent = parent ? `Under: ${parent.dataset.labelEn}` : 'Top level';
    }

    apply() {
      if (!this.selected) return;
      this.form.classList.add('was-validated');
      if (!this.form.checkValidity()) {
        const invalid = this.form.querySelector(':invalid');
        const pane = invalid.closest('.tab-pane');
        if (pane && window.bootstrap) {
          const tab = this.form.querySelector(`[data-bs-target="#${pane.id}"]`);
          if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
        }
        invalid.focus();
        return;
      }

      const item = this.selected;
      FIELDS.forEach(field => {
        const inputs = [...this.form.querySelectorAll(`[data-field="${field}"]`)];
        if (!inputs.length) return;
        let value;
        if (inputs[0].type === 'radio') value = (inputs.find(i => i.checked) || {}).value || '';
        else if (inputs[0].type === 'checkbox') value = inputs[0].checked ? '1' : '0';
        else value = inputs[0].disabled ? '' : inputs[0].value.trim();
        item.dataset[dataKey(field)] = value;
      });
      this.render(item);
      this.refresh(item);
      this.form.classList.remove('was-validated');
      this.dirty();
    }

    /** Updates the row text from the item's data. */
    render(item) {
      const d = item.dataset;
      const title = item.querySelector(':scope > .nt-row .nt-title');
      title.innerHTML = '';
      if (d.icon) {
        const icon = document.createElement('i');
        icon.className = d.icon;
        title.append(icon);
      }
      title.append(d.labelEn || 'Untitled');
      item.querySelector(':scope > .nt-row .nt-meta').textContent = NavEditor.linkText(item);
    }

    static linkText(item) {
      const d = item.dataset;
      const text = d.type === 'page' ? '/' + (d.page || '')
        : d.type === 'category' ? '/category/' + (d.category || '')
        : d.url || '#';
      return text.replace(/^\/\/?$/, '/') + (d.target === '_blank' ? ' ↗' : '') + (d.active === '0' ? ' · hidden' : '');
    }

    /* ---------- Add / delete ---------- */
    add() {
      const item = document.createElement('li');
      item.className = 'nt-item';
      Object.assign(item.dataset, {
        id: 'new-' + (++newId), labelEn: 'New item', labelKy: '', labelRu: '',
        type: 'custom', url: '#', target: '_self', icon: '', active: '1',
      });
      item.innerHTML =
        '<div class="nt-row">' +
          '<button type="button" class="nt-toggle" aria-expanded="false" aria-label="Expand"><i class="fas fa-chevron-right"></i></button>' +
          '<span class="nt-handle" title="Drag to move"><i class="fas fa-grip-vertical"></i></span>' +
          '<button type="button" class="nt-label"><span class="nt-title"></span><span class="nt-meta"></span></button>' +
        '</div>' +
        '<ul class="nt-list"></ul>';
      this.tree.append(item);
      this.sortable(this.children(item));
      this.render(item);
      this.refresh(item);
      this.select(item);
      item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      const label = this.form.querySelector('[data-field="label-en"]');
      if (label) { label.focus(); label.select(); }
      this.dirty();
    }

    remove() {
      const item = this.selected;
      if (!item) return;
      const count = item.querySelectorAll('.nt-item').length;
      const question = count
        ? `Delete “${item.dataset.labelEn}” and its ${count} subitem(s)?`
        : `Delete “${item.dataset.labelEn}”?`;
      if (!confirm(question)) return;
      const parent = item.parentElement.closest('.nt-item');
      item.remove();
      this.selected = null;
      if (parent) this.refresh(parent);
      this.showForm(false);
      this.dirty();
    }

    /* ---------- Save ---------- */
    dirty() { if (this.saved) this.saved.hidden = true; }

    toJSON(list = this.tree) {
      return [...list.children].map(item => {
        const d = item.dataset;
        return {
          id: d.id,
          label: Object.fromEntries(LANGS.map(l => [l, d['label' + l[0].toUpperCase() + l[1]] || ''])),
          type: d.type, page: d.page || null, category: d.category || null, url: d.url || null,
          target: d.target || '_self', icon: d.icon || null, active: d.active !== '0',
          children: this.toJSON(this.children(item)),
        };
      });
    }

    save() {
      const json = this.toJSON();
      console.log(`[${this.el.id}] menu`, json); // template: in cms/ post this to the server
      if (this.saved) {
        const count = this.tree.querySelectorAll('.nt-item').length;
        this.saved.querySelector('.alert-body').textContent =
          `Menu saved — ${count} item(s). (Demo: the JSON is in the browser console.)`;
        this.saved.hidden = false;
      }
      this.emit('nav-save', { menu: json });
    }
  }

  Odin.register('navEditor', NavEditor);
  Odin.NavEditor = NavEditor;
})(window.Odin);
