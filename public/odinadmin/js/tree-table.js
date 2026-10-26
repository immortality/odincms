/* ============================================================
   OdinAdmin — tree table (nested rows that can be sorted by drag & drop).
   Needs js/app.js and SortableJS. Markup reference: pages-list.html

   TreeTable  [data-tree-table]
     tbody tr[data-id][data-parent][data-depth] ... rows in tree order (each child under its parent);
                                                    data-parent="" for top level, data-status for the filter
     .row-handle ................. drag handle; an item moves among its siblings, its subpages move with it
     [data-tree-toggle] .......... expands / collapses a row's subpages
     [data-tree-delete] .......... deletes a row (after confirm); disabled while the row has subpages
     [data-tree-search] .......... free-text search (matches + their parents stay visible)
     select[data-tree-filter="status"]
     [data-tree-expand] [data-tree-collapse]
     [data-tree-info] ............ "20 pages"
     [data-tree-order-bar] ....... shown after the order changed; [data-tree-save] / [data-tree-undo]
     tr[data-tree-empty] ......... shown when nothing matches

   Dragging is off while a search or filter is active.
   order() returns [{ id, parent, sort }] — in cms/ post it on save: "Save order" emits
   odin:tree-order-save with { order, previous }; if the server refuses, set table.saved = previous
   and call table.checkOrder() to show the bar again.

   Texts: data-tree-labels='{"total":"{count} pages", …}' overrides TreeTable.labels (other languages).
   ============================================================ */
(function (Odin) {
  'use strict';

  class TreeTable extends Odin.Component {
    static selector = '[data-tree-table]';

    /** {count} {shown} {total} are replaced by numbers, {title} by the row title. */
    static labels = {
      total: '{count} pages',
      match: '{shown} of {total} pages match',
      expand: 'Expand subpages',
      collapse: 'Collapse subpages',
      delete: 'Delete',
      hasChildren: 'Has subpages — move or delete them first',
      confirm: 'Delete “{title}”? This cannot be undone.',
    };

    init() {
      let labels = {};
      try { labels = JSON.parse(this.el.dataset.treeLabels || '{}'); } catch (e) { /* keep the defaults */ }
      this.labels = Object.assign({}, TreeTable.labels, labels);

      this.tbody = this.$('tbody');
      this.search = this.$('[data-tree-search]');
      this.filter = this.$('[data-tree-filter="status"]');
      this.info = this.$('[data-tree-info]');
      this.orderBar = this.$('[data-tree-order-bar]');
      this.empty = this.$('tr[data-tree-empty]');
      this.collapsed = new Set();
      this.saved = this.order();

      if (window.Sortable) {
        this.sortable = Sortable.create(this.tbody, {
          handle: '.row-handle',
          draggable: 'tr[data-id]',
          animation: 150,
          onStart: e => this.hideSubtree(e.item, true),
          onMove: e => !e.related.dataset.id || e.related.dataset.parent === e.dragged.dataset.parent,
          onEnd: e => { this.hideSubtree(e.item, false); this.rebuild(); },
        });
      }

      this.on(this.tbody, 'click', e => {
        const toggle = e.target.closest('[data-tree-toggle]');
        if (toggle) return this.toggle(toggle.closest('tr'));
        const del = e.target.closest('[data-tree-delete]');
        if (del && !del.disabled) this.remove(del.closest('tr'));
      });
      if (this.search) this.on(this.search, 'input', () => this.render());
      if (this.filter) this.on(this.filter, 'change', () => this.render());
      this.on(this.$('[data-tree-expand]'), 'click', () => { this.collapsed.clear(); this.render(); });
      this.on(this.$('[data-tree-collapse]'), 'click', () => {
        this.rows().forEach(row => { if (this.childRows(row).length) this.collapsed.add(row.dataset.id); });
        this.render();
      });
      this.on(this.$('[data-tree-save]'), 'click', () => this.save());
      this.on(this.$('[data-tree-undo]'), 'click', () => this.undo());

      this.render();
    }

    /* ---------- Tree helpers ---------- */
    rows() { return [...this.tbody.querySelectorAll('tr[data-id]')]; }
    childRows(row) { return this.rows().filter(r => r.dataset.parent === row.dataset.id); }
    byId(id) { return this.tbody.querySelector(`tr[data-id="${CSS.escape(id)}"]`); }

    descendants(row) {
      const out = [];
      const walk = r => this.childRows(r).forEach(c => { out.push(c); walk(c); });
      walk(row);
      return out;
    }

    ancestors(row) {
      const out = [];
      for (let p = this.byId(row.dataset.parent || ''); p; p = this.byId(p.dataset.parent || '')) out.push(p);
      return out;
    }

    /* ---------- Drag & drop ---------- */
    hideSubtree(row, hide) {
      this.descendants(row).forEach(r => { r.hidden = hide || r.hidden; });
      if (!hide) this.render();
    }

    /** Puts every row back in tree order: siblings keep the order they now have in the DOM. */
    rebuild() {
      const rows = this.rows();
      const index = new Map(rows.map((r, i) => [r, i]));
      const children = parent => rows.filter(r => (r.dataset.parent || '') === parent).sort((a, b) => index.get(a) - index.get(b));
      const ordered = [];
      const walk = parent => children(parent).forEach((r, i) => { r.dataset.sort = i + 1; ordered.push(r); walk(r.dataset.id); });
      walk('');
      ordered.forEach(r => this.tbody.insertBefore(r, this.empty || null));
      this.render();
      this.checkOrder();
    }

    order() {
      return this.rows().map(r => ({ id: r.dataset.id, parent: r.dataset.parent || null, sort: Number(r.dataset.sort || 0) }));
    }

    checkOrder() {
      const changed = JSON.stringify(this.order()) !== JSON.stringify(this.saved);
      if (this.orderBar) this.orderBar.hidden = !changed;
    }

    save() {
      const previous = this.saved;
      this.saved = this.order();
      this.checkOrder();
      // template: nothing is posted; in cms/ listen for odin:tree-order-save and post detail.order
      this.emit('tree-order-save', { order: this.saved, previous });
    }

    undo() {
      const rows = new Map(this.rows().map(r => [r.dataset.id, r]));
      this.saved.forEach(({ id, sort }) => {
        const row = rows.get(id);
        if (row) { row.dataset.sort = sort; this.tbody.insertBefore(row, this.empty || null); }
      });
      this.render();
      this.checkOrder();
    }

    /* ---------- Expand / collapse, delete ---------- */
    toggle(row) {
      const id = row.dataset.id;
      if (this.collapsed.has(id)) this.collapsed.delete(id); else this.collapsed.add(id);
      this.render();
    }

    remove(row) {
      const title = row.querySelector('.tbl-title');
      if (!confirm(this.label('confirm', { title: title ? title.textContent.trim() : '' }))) return;
      row.remove();
      this.saved = this.saved.filter(o => o.id !== row.dataset.id);
      this.render();
    }

    /* ---------- Visibility ---------- */
    render() {
      const query = this.search ? this.search.value.trim().toLowerCase() : '';
      const status = this.filter ? this.filter.value : '';
      const filtering = !!(query || status);
      const rows = this.rows();

      // Rows that match, plus their parents so the path stays readable
      const visible = new Set();
      rows.forEach(row => {
        const okText = !query || row.textContent.toLowerCase().includes(query);
        const okStatus = !status || row.dataset.status === status;
        if (okText && okStatus) {
          visible.add(row);
          this.ancestors(row).forEach(a => visible.add(a));
        }
      });

      rows.forEach(row => {
        const hasChildren = this.childRows(row).length > 0;
        const collapsedAbove = !filtering && this.ancestors(row).some(a => this.collapsed.has(a.dataset.id));
        row.hidden = !visible.has(row) || collapsedAbove;
        row.classList.toggle('is-context', filtering && visible.has(row) && !this.matches(row, query, status));

        const toggle = row.querySelector('[data-tree-toggle]');
        if (toggle) {
          const open = filtering || !this.collapsed.has(row.dataset.id);
          toggle.classList.toggle('is-empty', !hasChildren);
          toggle.tabIndex = hasChildren ? 0 : -1;
          toggle.setAttribute('aria-expanded', String(hasChildren && open));
          toggle.setAttribute('aria-label', this.label(open ? 'collapse' : 'expand'));
        }
        const del = row.querySelector('[data-tree-delete]');
        if (del) {
          del.disabled = hasChildren;
          del.title = this.label(hasChildren ? 'hasChildren' : 'delete');
        }
      });

      if (this.sortable) this.sortable.option('disabled', filtering);
      this.el.classList.toggle('is-filtering', filtering);

      const shown = rows.filter(r => !r.hidden && !r.classList.contains('is-context')).length;
      if (this.empty) this.empty.hidden = visible.size > 0;
      if (this.info) {
        this.info.innerHTML = filtering
          ? this.label('match', { shown, total: rows.length }, true)
          : this.label('total', { count: rows.length }, true);
      }
    }

    /** A text from this.labels with its {placeholders} filled; html: escaped, the values in <strong>. */
    label(key, values = {}, html = false) {
      const esc = text => String(text).replace(/[&<>"']/g, ch => `&#${ch.charCodeAt(0)};`);
      const fill = (text, wrap) => text.replace(/\{(\w+)\}/g, (m, name) => (name in values ? wrap(values[name]) : m));
      const text = this.labels[key] || '';
      return html ? fill(esc(text), v => `<strong>${esc(v)}</strong>`) : fill(text, v => v);
    }

    matches(row, query, status) {
      return (!query || row.textContent.toLowerCase().includes(query)) && (!status || row.dataset.status === status);
    }
  }

  Odin.register('treeTable', TreeTable);
  Odin.TreeTable = TreeTable;
})(window.Odin);
