/* ============================================================
   OdinAdmin — data tables
   Needs js/app.js (DataTable extends Odin.Component; get an instance with
   Odin.get(element, 'dataTable')).
   Client-side search, filters, sorting, pagination, bulk select,
   expandable rows, column visibility and CSV export for any
   element marked with [data-table]. Markup reference: basic-table.html.

   Hooks (all optional, looked up inside the [data-table] element):
     [data-per-page] on root ........ rows per page (default 10)
     tr[data-row] ................... a data row; data-* attributes hold
                                      the raw values used to filter/sort
     tr.row-details ................. details row, directly after its row
     input[data-search] ............. free-text search
     select[data-filter="key"] ...... exact match on tr.dataset[key]
     input[data-filter-from|to="key"] date range on tr.dataset[key]
     th[data-sort="key"] ............ sortable column (+ data-sort-type
                                      = text | number | date)
     select[data-per-page] .......... rows-per-page picker
     [data-pager] / [data-info] ..... pagination / "Showing x–y of n"
     [data-check-all] / .row-check .. bulk selection
     [data-bulk-bar] [data-bulk-count] [data-bulk-status="…"]
     [data-bulk-delete] [data-bulk-clear]
     [data-expand] .................. toggles the row's details row
     [data-delete] .................. deletes the row (after confirm)
     input[data-col="n"] ............ shows/hides column n (0-based)
     [data-export="csv"] ............ downloads the filtered rows
     [data-reset] ................... clears search, filters and sort
     [data-status-badge] ............ badge updated by bulk status change

   In cms/ the filtering and paging move to the server (query string);
   keep the same markup and drop the parts this file no longer needs.
   ============================================================ */
(function (Odin) {
  'use strict';

  // Status → [badge colour, label]. Same mapping as the badges in the HTML.
  const STATUS = {
    published: ['success', 'Published'],
    draft:     ['muted',   'Draft'],
    pending:   ['warning', 'Pending'],
    scheduled: ['info',    'Scheduled'],
    archived:  ['violet',  'Archived'],
    active:    ['success', 'Active'],
    invited:   ['info',    'Invited'],
    suspended: ['danger',  'Suspended'],
  };

  // Cells that hold controls, not data: skipped by search and export
  const CONTROL_CELLS = '.col-check, .col-expand, .col-actions';

  // Text of a node with its text nodes joined by spaces. Works for rows that are
  // off the current page (detached), where innerText has no layout to go by.
  function textOf(node) {
    const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT);
    const parts = [];
    while (walker.nextNode()) parts.push(walker.currentNode.nodeValue);
    return parts.join(' ').replace(/\s+/g, ' ').trim();
  }

  class DataTable extends Odin.Component {
    static selector = '[data-table]';
    static defaults = { perPage: 10 };

    init() {
      this.table   = this.$('table');
      this.tbody   = this.table.tBodies[0];
      this.headRow = this.table.tHead.rows[0];
      this.perPage = this.data('perPage', this.options.perPage);
      this.page    = 1;
      this.sort    = null; // { key, type, dir }
      this.hidden  = new Set();

      this.rows = Array.from(this.tbody.querySelectorAll('tr[data-row]'));
      this.rows.forEach(row => {
        const next = row.nextElementSibling;
        row._details = next && next.classList.contains('row-details') ? next : null;
        this.indexText(row);
      });

      this.search  = this.$('[data-search]');
      this.filters = this.$$('[data-filter], [data-filter-from], [data-filter-to]');
      this.pager   = this.$('[data-pager]');
      this.info    = this.$('[data-info]');
      this.checkAll = this.$('[data-check-all]');
      this.bulkBar = this.$('[data-bulk-bar]');

      this.bind();
      this.refresh();
    }


    indexText(row) {
      row._text = Array.from(row.cells)
        .filter(td => !td.matches(CONTROL_CELLS))
        .map(textOf)
        .join(' ')
        .toLowerCase();
    }

    /* ---------- events ---------- */
    bind() {
      if (this.search) this.search.addEventListener('input', () => this.refresh());
      this.filters.forEach(el => el.addEventListener('change', () => this.refresh()));

      const perPage = this.$('select[data-per-page]');
      if (perPage) {
        perPage.value = String(this.perPage);
        perPage.addEventListener('change', () => {
          this.perPage = parseInt(perPage.value, 10);
          this.refresh();
        });
      }

      const reset = this.$('[data-reset]');
      if (reset) reset.addEventListener('click', () => {
        if (this.search) this.search.value = '';
        this.filters.forEach(el => { el.value = ''; });
        this.sort = null;
        this.refresh();
      });

      this.$$('th[data-sort]').forEach(th => {
        th.classList.add('th-sort');
        th.tabIndex = 0;
        th.setAttribute('aria-sort', 'none');
        th.addEventListener('click', () => this.sortBy(th));
        th.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.sortBy(th); }
        });
      });

      if (this.pager) this.pager.addEventListener('click', e => {
        const btn = e.target.closest('[data-page]');
        if (!btn || btn.disabled) return;
        this.page = parseInt(btn.dataset.page, 10);
        this.render();
      });

      if (this.checkAll) this.checkAll.addEventListener('change', () => {
        this.visible.forEach(row => this.select(row, this.checkAll.checked));
        this.updateBulk();
      });

      this.tbody.addEventListener('change', e => {
        if (!e.target.matches('.row-check')) return;
        this.select(e.target.closest('tr'), e.target.checked);
        this.updateBulk();
      });

      this.tbody.addEventListener('click', e => {
        const expand = e.target.closest('[data-expand]');
        if (expand) return this.toggleDetails(expand.closest('tr'), expand);

        const del = e.target.closest('[data-delete]');
        if (del) {
          e.preventDefault();
          const row = del.closest('tr[data-row]');
          if (confirm('Delete this record? This cannot be undone.')) this.remove([row]);
        }
      });

      this.$$('[data-bulk-status]').forEach(btn => btn.addEventListener('click', e => {
        e.preventDefault();
        this.selected().forEach(row => this.setStatus(row, btn.dataset.bulkStatus));
        this.refresh(false);
      }));

      const bulkDelete = this.$('[data-bulk-delete]');
      if (bulkDelete) bulkDelete.addEventListener('click', () => {
        const rows = this.selected();
        if (rows.length && confirm(`Delete ${rows.length} selected record(s)? This cannot be undone.`)) {
          this.remove(rows);
        }
      });

      const bulkClear = this.$('[data-bulk-clear]');
      if (bulkClear) bulkClear.addEventListener('click', () => {
        this.rows.forEach(row => this.select(row, false));
        this.updateBulk();
      });

      this.$$('input[data-col]').forEach(cb => {
        cb.addEventListener('change', () => this.setColumn(parseInt(cb.dataset.col, 10), cb.checked));
      });

      const exportCsv = this.$('[data-export="csv"]');
      if (exportCsv) exportCsv.addEventListener('click', e => { e.preventDefault(); this.exportCsv(); });
    }

    /* ---------- filtering, sorting, paging ---------- */
    matches(row) {
      const q = this.search ? this.search.value.trim().toLowerCase() : '';
      if (q && !row._text.includes(q)) return false;

      for (const el of this.filters) {
        if (!el.value) continue;
        if (el.dataset.filter && row.dataset[el.dataset.filter] !== el.value) return false;

        const fromKey = el.dataset.filterFrom, toKey = el.dataset.filterTo;
        const date = (row.dataset[fromKey || toKey] || '').slice(0, 10);
        if (fromKey && date < el.value) return false;
        if (toKey && date > el.value) return false;
      }
      return true;
    }

    sortBy(th) {
      const key = th.dataset.sort;
      const dir = this.sort && this.sort.key === key ? -this.sort.dir : 1;
      this.sort = { key, dir, type: th.dataset.sortType || 'text' };
      this.refresh(false);
    }

    compare(a, b) {
      const { key, dir, type } = this.sort;
      const x = a.dataset[key] || '', y = b.dataset[key] || '';
      if (type === 'number') return (parseFloat(x) - parseFloat(y)) * dir;
      return x.localeCompare(y, undefined, { sensitivity: 'base' }) * dir;
    }

    // Re-run filters/sort. resetPage = false keeps the current page (clamped).
    refresh(resetPage = true) {
      if (resetPage) this.page = 1;
      this.filtered = this.rows.filter(row => this.matches(row));
      if (this.sort) this.filtered.sort((a, b) => this.compare(a, b));

      this.$$('th[data-sort]').forEach(th => {
        const active = this.sort && this.sort.key === th.dataset.sort;
        th.classList.toggle('asc',  !!active && this.sort.dir === 1);
        th.classList.toggle('desc', !!active && this.sort.dir === -1);
        th.setAttribute('aria-sort', active ? (this.sort.dir === 1 ? 'ascending' : 'descending') : 'none');
      });
      this.render();
    }

    render() {
      const total = this.filtered.length;
      const pages = Math.max(1, Math.ceil(total / this.perPage));
      this.page = Math.min(Math.max(1, this.page), pages);

      const start = (this.page - 1) * this.perPage;
      this.visible = this.filtered.slice(start, start + this.perPage);

      const frag = document.createDocumentFragment();
      this.visible.forEach(row => {
        frag.append(row);
        if (row._details) frag.append(row._details);
      });
      if (!total) frag.append(this.emptyRow());
      this.tbody.replaceChildren(frag);

      if (this.info) {
        this.info.innerHTML = total
          ? `Showing <strong>${start + 1}–${start + this.visible.length}</strong> of <strong>${total}</strong>`
          : 'No records';
      }
      if (this.pager) this.renderPager(pages);
      this.updateBulk();
    }

    renderPager(pages) {
      const p = this.page;
      const btn = (page, label, opts = {}) =>
        `<button type="button" class="pager-btn${opts.active ? ' active' : ''}" data-page="${page}"` +
        `${opts.disabled ? ' disabled' : ''}${opts.active ? ' aria-current="page"' : ''}` +
        `${opts.aria ? ` aria-label="${opts.aria}"` : ''}>${label}</button>`;

      // 1 … 4 5 6 … 12 — always first, last, and the current page ±1
      const list = [];
      for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || Math.abs(i - p) <= 1 || pages <= 7) list.push(i);
        else if (list[list.length - 1] !== '…') list.push('…');
      }

      this.pager.innerHTML =
        btn(p - 1, '<i class="fas fa-chevron-left"></i>', { disabled: p === 1, aria: 'Previous page' }) +
        list.map(i => i === '…' ? '<span class="pager-gap">…</span>' : btn(i, i, { active: i === p })).join('') +
        btn(p + 1, '<i class="fas fa-chevron-right"></i>', { disabled: p === pages, aria: 'Next page' });
    }

    visibleColumnCount() {
      return this.headRow.cells.length - this.hidden.size;
    }

    emptyRow() {
      const tr = document.createElement('tr');
      tr.className = 'table-empty';
      tr.innerHTML = `<td colspan="${this.visibleColumnCount()}">` +
        '<i class="fas fa-search"></i>No records match your search or filters.</td>';
      return tr;
    }

    /* ---------- selection & bulk actions ---------- */
    select(row, on) {
      const cb = row.querySelector('.row-check');
      if (cb) cb.checked = on;
      row.classList.toggle('selected', on);
    }

    selected() {
      return this.rows.filter(row => row.classList.contains('selected'));
    }

    updateBulk() {
      const count = this.selected().length;
      if (this.bulkBar) {
        this.bulkBar.classList.toggle('show', count > 0);
        const label = this.bulkBar.querySelector('[data-bulk-count]');
        if (label) label.textContent = `${count} selected`;
      }
      if (this.checkAll) {
        const onPage = this.visible.filter(row => row.classList.contains('selected')).length;
        this.checkAll.checked = onPage > 0 && onPage === this.visible.length;
        this.checkAll.indeterminate = onPage > 0 && onPage < this.visible.length;
      }
    }

    setStatus(row, status) {
      if (!STATUS[status]) return;
      row.dataset.status = status;
      const badge = row.querySelector('[data-status-badge]');
      if (badge) {
        badge.className = `badge-status ${STATUS[status][0]}`;
        badge.textContent = STATUS[status][1];
      }
      this.indexText(row);
    }

    remove(rows) {
      this.rows = this.rows.filter(row => !rows.includes(row));
      rows.forEach(row => {
        row.remove();
        if (row._details) row._details.remove();
      });
      this.refresh(false);
    }

    /* ---------- details rows & columns ---------- */
    toggleDetails(row, btn) {
      if (!row._details) return;
      const open = row._details.hidden;
      row._details.hidden = !open;
      row.classList.toggle('has-open-details', open);
      btn.setAttribute('aria-expanded', String(open));
    }

    setColumn(index, show) {
      show ? this.hidden.delete(index) : this.hidden.add(index);
      [this.headRow, ...this.rows].forEach(row => {
        if (row.cells[index]) row.cells[index].hidden = !show;
      });
      const span = this.visibleColumnCount();
      this.rows.forEach(row => { if (row._details) row._details.cells[0].colSpan = span; });
      const empty = this.tbody.querySelector('.table-empty td');
      if (empty) empty.colSpan = span;
    }

    /* ---------- export ---------- */
    exportCsv() {
      const keep = cell => !cell.hidden && !cell.matches(CONTROL_CELLS);
      const clean = cell => `"${textOf(cell).replace(/"/g, '""')}"`;
      const lines = [this.headRow, ...this.filtered].map(row =>
        Array.from(row.cells).filter(keep).map(clean).join(','));

      const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = (this.el.dataset.table || 'table') + '.csv';
      link.click();
      URL.revokeObjectURL(link.href);
    }
  }

  Odin.register('dataTable', DataTable);
  Odin.DataTable = DataTable;
  window.OdinDataTable = DataTable;
})(window.Odin);
