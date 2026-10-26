/* ============================================================
   OdinAdmin — task calendar (needs js/app.js, js/forms.js, FullCalendar 6.1 global build).
   Markup reference: calendar.html, styles: css/vendor/fullcalendar.css

   TaskCalendar  [data-task-calendar]
     data-source="#id" ....... <script type="application/json"> with the tasks:
                               [{ id, title, project, status, priority, start: "Y-m-d", due: "Y-m-d",
                                  assignees: [{ initials, name, tone }], checklist: [done, total] }]
                               status: assigned | progress | review | completed
     data-today="Y-m-d" ...... reference date (overdue marks, initial month); default: today
     [data-cal-status] ....... checkboxes that show / hide a status
     #task-view-modal ........ details of a clicked task ([data-task-field="…"] slots)
     #task-new-modal ......... form.needs-validation to add a task; opened by "Add Task" or by
                               selecting days in the calendar (dates are pre-filled)
   Drag a task to move it, drag its end to change the due date.
   In cms/ load the tasks from the server and send changes in eventDrop / eventResize / save.
   ============================================================ */
(function (Odin) {
  'use strict';

  const STATUS = { assigned: 'Assigned', progress: 'In Progress', review: 'Review', completed: 'Completed' };
  const PRIORITY = { high: ['danger', 'High'], medium: ['warning', 'Medium'], low: ['light', 'Low'] };
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const addDays = (ymd, n) => { const d = new Date(ymd + 'T00:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const fmt = ymd => `${ymd.slice(8, 10)} ${MONTHS[ymd.slice(5, 7) - 1]} ${ymd.slice(0, 4)}`;
  const ymdOf = date => { const d = new Date(date); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };

  let newId = 1000;

  class TaskCalendar extends Odin.Component {
    static selector = '[data-task-calendar]';

    init() {
      if (!window.FullCalendar) return;
      const source = document.querySelector(this.el.dataset.source);
      this.today = this.el.dataset.today || ymdOf(new Date());
      this.viewModal = document.getElementById('task-view-modal');
      this.newModal = document.getElementById('task-new-modal');
      this.newForm = this.newModal && this.newModal.querySelector('form');

      this.calendar = new FullCalendar.Calendar(this.el, {
        initialView: 'dayGridMonth',
        initialDate: this.today,
        now: this.today,
        height: '100%',
        firstDay: 1,
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        buttonText: { today: 'Today', month: 'Month', week: 'Week', list: 'List' },
        expandRows: true,          // weeks fill the full height of the card
        dayMaxEventRows: 5,        // then "+N more"
        editable: true,
        selectable: true,
        eventDisplay: 'block',
        displayEventTime: false,
        events: JSON.parse(source ? source.textContent : '[]').map(task => this.toEvent(task)),
        eventContent: arg => ({ html: this.eventHtml(arg.event) }),
        eventClick: info => { info.jsEvent.preventDefault(); this.show(info.event); },
        select: info => this.openNew(info.startStr, addDays(info.endStr, -1)),
        eventDrop: info => this.moved(info.event),
        eventResize: info => this.moved(info.event),
      });
      this.calendar.render();

      // Status filter chips
      document.querySelectorAll('[data-cal-status]').forEach(input =>
        this.on(input, 'change', () => this.filter()));

      // "Add Task" buttons outside the calendar
      document.querySelectorAll('[data-cal-new]').forEach(btn =>
        this.on(btn, 'click', () => this.openNew(this.today, this.today)));

      if (this.newForm) this.on(this.newForm, 'odin:form-valid', () => this.create());
      const done = this.viewModal && this.viewModal.querySelector('[data-task-complete]');
      if (done) this.on(done, 'click', () => this.complete());
    }

    /* ---------- Tasks ↔ events ---------- */
    toEvent(task) {
      return {
        id: String(task.id),
        title: task.title,
        start: task.start,
        end: addDays(task.due, 1), // all-day end is exclusive
        allDay: true,
        classNames: ['task-ev', 'st-' + task.status],
        extendedProps: task,
      };
    }

    isOverdue(task) { return task.status !== 'completed' && task.due < this.today; }

    eventHtml(event) {
      const task = event.extendedProps;
      const flag = this.isOverdue(task) ? '<i class="fas fa-exclamation-circle task-ev-flag" title="Overdue"></i>' : '';
      const check = task.status === 'completed' ? '<i class="fas fa-check" style="font-size:.62rem"></i>' : '';
      return `<div class="task-ev-inner">${flag}${check}<span class="task-ev-title">${esc(event.title)}</span></div>`;
    }

    refresh(event) {
      event.setProp('classNames', ['task-ev', 'st-' + event.extendedProps.status]);
    }

    moved(event) {
      const start = ymdOf(event.start);
      const due = event.end ? addDays(ymdOf(event.end), -1) : start;
      event.setExtendedProp('start', start);
      event.setExtendedProp('due', due);
      this.refresh(event);
      this.emit('task-change', { id: event.id, start, due }); // in cms/: save the new dates
    }

    filter() {
      const shown = new Set([...document.querySelectorAll('[data-cal-status]:checked')].map(i => i.value));
      this.calendar.getEvents().forEach(e => e.setProp('display', shown.has(e.extendedProps.status) ? 'block' : 'none'));
    }

    /* ---------- Details modal ---------- */
    show(event) {
      if (!this.viewModal || !window.bootstrap) return;
      this.current = event;
      const t = event.extendedProps;
      const [tone, prio] = PRIORITY[t.priority] || PRIORITY.medium;
      const [done, total] = t.checklist || [0, 0];
      const set = (name, html) => { const el = this.viewModal.querySelector(`[data-task-field="${name}"]`); if (el) el.innerHTML = html; };

      set('title', esc(t.title));
      set('status', `<span class="badge-soft badge-pill badge-dot st-${t.status}">${STATUS[t.status]}</span>`);
      set('project', esc(t.project));
      set('priority', `<span class="task-priority ${tone}">${prio}</span>`);
      set('dates', `${fmt(t.start)} → ${fmt(t.due)}` + (this.isOverdue(t) ? ' <span class="badge-soft danger badge-sm ms-1">Overdue</span>' : ''));
      set('assignees', (t.assignees || []).map(a =>
        `<span class="d-inline-flex align-items-center gap-2 me-3"><span class="av-sm av-xs ${esc(a.tone)}">${esc(a.initials)}</span>${esc(a.name)}</span>`).join(''));
      set('checklist', total
        ? `<div class="d-flex align-items-center gap-2"><div class="src-bar flex-grow-1"><span class="${done === total ? 'success' : ''}" style="width:${Math.round(done / total * 100)}%"></span></div><span class="tbl-sub">${done}/${total}</span></div>`
        : '<span class="tbl-muted">No checklist</span>');

      const btn = this.viewModal.querySelector('[data-task-complete]');
      if (btn) btn.hidden = t.status === 'completed';
      bootstrap.Modal.getOrCreateInstance(this.viewModal).show();
    }

    complete() {
      if (!this.current) return;
      this.current.setExtendedProp('status', 'completed');
      const [, total] = this.current.extendedProps.checklist || [0, 0];
      this.current.setExtendedProp('checklist', [total, total]);
      this.refresh(this.current);
      this.filter();
      bootstrap.Modal.getOrCreateInstance(this.viewModal).hide();
    }

    /* ---------- New task ---------- */
    openNew(start, due) {
      if (!this.newModal || !window.bootstrap) return;
      this.newForm.reset();
      this.newForm.classList.remove('was-validated');
      this.newForm.querySelector('[name="start"]').value = start;
      this.newForm.querySelector('[name="due"]').value = due;
      bootstrap.Modal.getOrCreateInstance(this.newModal).show();
      this.calendar.unselect();
    }

    create() {
      const f = this.newForm.elements;
      const assignee = f.assignee.selectedOptions[0];
      const start = f.start.value;
      const due = f.due.value < start ? start : f.due.value;
      this.calendar.addEvent(this.toEvent({
        id: ++newId,
        title: f.title.value.trim(),
        project: f.project.value,
        status: f.status.value,
        priority: f.priority.value,
        start, due,
        assignees: assignee && assignee.value
          ? [{ initials: assignee.dataset.initials, name: assignee.textContent.trim(), tone: assignee.dataset.tone }] : [],
        checklist: [0, 0],
      }));
      this.filter();
      bootstrap.Modal.getOrCreateInstance(this.newModal).hide();
    }
  }

  Odin.register('taskCalendar', TaskCalendar);
  Odin.TaskCalendar = TaskCalendar;
})(window.Odin);
