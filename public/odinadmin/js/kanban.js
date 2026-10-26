/* ============================================================
   OdinAdmin — task board (needs js/app.js, js/forms.js and SortableJS).
   Markup reference: task-management.html, styles: css/pages/tasks.css

   TaskBoard  [data-task-board]   data-today="Y-m-d" (overdue reference; default: today)
     .kanban-col[data-status] > .kanban-list ... columns: assigned | progress | review | completed
     .task-card[data-id][data-due][data-project] cards; drag them between and within columns
     [data-col-count="status"] ................. card count in a column header
     [data-stat="total|assigned|overdue|completed"], [data-stat-bar="completed"] (summary blocks)
     [data-board-search] / [data-board-project] filters
     [data-task-delete] ......................... removes a card (after confirm)
     #task-new-modal form.needs-validation ...... adds a card; buttons with data-new-status="…"
                                                  open it with that column pre-selected
   In cms/ send { id, status, sort } after a drop (event "odin:task-move") and post the new-task form.
   ============================================================ */
(function (Odin) {
  'use strict';

  const PRIORITY = { high: ['danger', 'High'], medium: ['warning', 'Medium'], low: ['light', 'Low'] };
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const fmt = ymd => `${ymd.slice(8, 10)} ${MONTHS[ymd.slice(5, 7) - 1]}`;

  let newId = 1000;

  class TaskBoard extends Odin.Component {
    static selector = '[data-task-board]';

    init() {
      this.today = this.el.dataset.today || new Date().toISOString().slice(0, 10);
      this.search = this.$('[data-board-search]');
      this.project = this.$('[data-board-project]');
      this.modal = document.getElementById('task-new-modal');
      this.form = this.modal && this.modal.querySelector('form');

      if (window.Sortable) {
        this.$$('.kanban-list').forEach(list => Sortable.create(list, {
          group: 'tasks',
          animation: 160,
          forceFallback: true,         // same drag look in every browser (tilted card)
          fallbackOnBody: true,
          dragClass: 'sortable-drag',
          filter: '.dropdown, .dropdown *',
          preventOnFilter: false,
          onEnd: e => this.moved(e.item),
        }));
      }

      this.on(this.el, 'click', e => {
        const del = e.target.closest('[data-task-delete]');
        if (del) { e.preventDefault(); this.remove(del.closest('.task-card')); }
      });
      if (this.search) this.on(this.search, 'input', () => this.applyFilter());
      if (this.project) this.on(this.project, 'change', () => this.applyFilter());

      if (this.modal) {
        // Column "+" buttons pre-select their column
        this.on(this.modal, 'show.bs.modal', e => {
          this.form.reset();
          this.form.classList.remove('was-validated');
          const status = e.relatedTarget && e.relatedTarget.dataset.newStatus;
          if (status) this.form.elements.status.value = status;
          this.form.elements.due.value = this.today;
        });
        this.on(this.form, 'odin:form-valid', () => this.create());
      }

      this.$$('.task-card').forEach(card => this.refreshCard(card));
      this.update();
    }

    statusOf(card) { return card.closest('.kanban-col').dataset.status; }

    /* ---------- Cards ---------- */
    refreshCard(card) {
      const status = this.statusOf(card);
      card.dataset.status = status;
      const due = card.querySelector('.task-due');
      if (due) {
        const overdue = status !== 'completed' && card.dataset.due < this.today;
        due.classList.toggle('overdue', overdue);
        due.classList.toggle('done', status === 'completed');
        const icon = due.querySelector('i');
        if (icon) icon.className = status === 'completed' ? 'fas fa-check-circle' : overdue ? 'fas fa-exclamation-circle' : 'far fa-calendar';
      }
    }

    moved(card) {
      this.refreshCard(card);
      this.update();
      const list = card.parentElement;
      this.emit('task-move', { id: card.dataset.id, status: this.statusOf(card), sort: [...list.children].indexOf(card) + 1 });
    }

    remove(card) {
      const title = card.querySelector('.task-title');
      if (!confirm(`Delete the task “${title ? title.textContent.trim() : ''}”?`)) return;
      card.remove();
      this.update();
    }

    /* ---------- Counts ---------- */
    update() {
      const cards = this.$$('.task-card');
      const by = status => cards.filter(c => c.dataset.status === status).length;
      this.$$('[data-col-count]').forEach(el => { el.textContent = by(el.dataset.colCount); });

      const completed = by('completed');
      const overdue = cards.filter(c => c.dataset.status !== 'completed' && c.dataset.due < this.today).length;
      const set = (key, value) => document.querySelectorAll(`[data-stat="${key}"]`).forEach(el => { el.textContent = value; });
      set('total', cards.length);
      set('assigned', by('assigned'));
      set('overdue', overdue);
      set('completed', completed);
      set('completed-pct', cards.length ? Math.round(completed / cards.length * 100) + '%' : '0%');
      document.querySelectorAll('[data-stat-bar="completed"]').forEach(el => {
        el.style.width = (cards.length ? completed / cards.length * 100 : 0) + '%';
      });
    }

    applyFilter() {
      const q = this.search ? this.search.value.trim().toLowerCase() : '';
      const p = this.project ? this.project.value : '';
      this.$$('.task-card').forEach(card => {
        const ok = (!q || card.textContent.toLowerCase().includes(q)) && (!p || card.dataset.project === p);
        card.classList.toggle('is-filtered', !ok);
      });
    }

    /* ---------- New task ---------- */
    create() {
      const f = this.form.elements;
      const assignee = f.assignee.selectedOptions[0];
      const task = {
        id: ++newId,
        title: f.title.value.trim(),
        desc: f.desc.value.trim(),
        project: f.project.value,
        priority: f.priority.value,
        due: f.due.value,
        assignees: assignee && assignee.value ? [{ initials: assignee.dataset.initials, name: assignee.textContent.trim(), tone: assignee.dataset.tone }] : [],
      };
      const list = this.$(`.kanban-col[data-status="${f.status.value}"] .kanban-list`);
      list.insertAdjacentHTML('afterbegin', TaskBoard.cardHtml(task));
      const card = list.firstElementChild;
      this.refreshCard(card);
      this.update();
      this.applyFilter();
      bootstrap.Modal.getOrCreateInstance(this.modal).hide();
    }

    /** Same markup as the cards rendered on the server (task-management.html). */
    static cardHtml(t) {
      const [tone, label] = PRIORITY[t.priority] || PRIORITY.medium;
      const avatars = (t.assignees || []).map(a =>
        `<span class="av-sm av-xs ${esc(a.tone)}" title="${esc(a.name)}">${esc(a.initials)}</span>`).join('');
      return `<div class="task-card" data-id="${t.id}" data-due="${esc(t.due)}" data-project="${esc(t.project)}">
  <div class="task-card-top">
    <span class="badge-soft badge-sm light">${esc(t.project)}</span>
    <span class="task-priority ${tone}">${label}</span>
    <div class="dropdown">
      <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Task actions"><i class="fas fa-ellipsis-h"></i></button>
      <ul class="dropdown-menu dropdown-menu-end card-action-menu">
        <li><a class="dropdown-item" href="#"><i class="fas fa-pen"></i> Edit</a></li>
        <li><a class="dropdown-item" href="calendar.html"><i class="far fa-calendar-alt"></i> Show in calendar</a></li>
        <li><hr class="dropdown-divider mx-2"></li>
        <li><a class="dropdown-item danger" href="#" data-task-delete><i class="far fa-trash-alt"></i> Delete</a></li>
      </ul>
    </div>
  </div>
  <h6 class="task-title">${esc(t.title)}</h6>
  ${t.desc ? `<p class="task-desc">${esc(t.desc)}</p>` : ''}
  <div class="task-meta">
    <span class="task-due"><i class="far fa-calendar"></i>${fmt(t.due)}</span>
    <span title="Comments"><i class="far fa-comment"></i>0</span>
    <div class="av-stack">${avatars}</div>
  </div>
</div>`;
    }
  }

  Odin.register('taskBoard', TaskBoard);
  Odin.TaskBoard = TaskBoard;
})(window.Odin);
