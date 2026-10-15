/* ============================================================
   OdinAdmin — drag & drop lists with Dragula (needs js/app.js and dragula.min.js).
   Markup reference: dragula.html

   DragBoard   [data-plugin="dragula"]
                 one list:      put the attribute on the list itself
                 several lists: put it on a wrapper with data-containers='["id-a","id-b"]'
                 data-handle-class="drag-handle"  only drag by that element
                 data-copy="true"                 copy instead of move (source lists stay full)
               Fires a bubbling "odin:drop" event on the wrapper:
                 detail = { item, target, source, order }  (order = data-id values of the target list)
               Use it to save the new order, e.g. fetch('/admin/menu/order', { method: 'POST', body: … }).
   DragCount   [data-drag-count="#list-id"]  shows the number of items in that list, kept up to date.
   ============================================================ */
(function (Odin) {
  'use strict';

  class DragBoard extends Odin.Component {
    static selector = '[data-plugin="dragula"]';

    init() {
      if (!window.dragula) return;
      this.containers = this.findContainers();
      this.handleClass = this.data('handleClass', '');

      this.drake = dragula(this.containers, {
        moves: (item, source, handle) => this.canMove(handle),
        copy: this.data('copy', false),
        revertOnSpill: true,
        mirrorContainer: document.body,
      });

      this.drake.on('drag', () => document.body.classList.add('is-dragging'));
      this.drake.on('dragend', () => document.body.classList.remove('is-dragging'));
      this.drake.on('drop', (item, target, source) => this.dropped(item, target, source));
    }

    findContainers() {
      const ids = this.el.dataset.containers;
      if (!ids) return [this.el];
      return JSON.parse(ids).map(id => document.getElementById(id)).filter(Boolean);
    }

    canMove(handle) {
      if (!this.handleClass) return true;
      return !!(handle && handle.closest(`.${this.handleClass}`));
    }

    /** data-id values of the items in a list, in their current order. */
    static order(list) {
      return Array.from(list.children).map(child => child.dataset.id).filter(Boolean);
    }

    dropped(item, target, source) {
      DragCount.refresh();
      this.emit('drop', { item, target, source, order: DragBoard.order(target) });
    }

    destroy() {
      if (this.drake) this.drake.destroy();
    }
  }

  class DragCount extends Odin.Component {
    static selector = '[data-drag-count]';

    static refresh() {
      document.querySelectorAll(DragCount.selector).forEach(el => {
        const counter = Odin.get(el, 'dragCount');
        if (counter) counter.update();
      });
    }

    init() {
      this.list = document.querySelector(this.el.dataset.dragCount);
      this.update();
    }

    update() {
      if (this.list) this.el.textContent = this.list.children.length;
    }
  }

  Odin.register('dragBoard', DragBoard);
  Odin.register('dragCount', DragCount);
  Object.assign(Odin, { DragBoard, DragCount });
})(window.Odin);
