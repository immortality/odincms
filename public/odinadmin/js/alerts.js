/* ============================================================
   OdinAdmin — alerts & toasts (needs js/app.js). Markup reference: alerts.html

   Alerts:  <button data-dismiss="alert"> closes its alert with a fade
            (add data-hide to the alert to only hide it instead of removing it)
   Toasts:  Odin.toast({ type, title, text, icon, timeout, position })
              type:     primary | success | warning | danger | info | light | dark
              timeout:  ms before it closes (0 = stays until closed), default 5000
              position: top-right (default) | top-center | bottom-right | bottom-left
            <button data-toast="success" data-title="…" data-text="…"
                    [data-position="…"] [data-timeout="0"]> shows one on click.
   In cms/ call Odin.toast() for Laravel session flash messages.
   ============================================================ */
(function (Odin) {
  'use strict';

  /* ---------- Alert ---------- */
  class Alert {
    static selector = '.alert-soft, .alert-solid, .alert-outline, .alert-accent';

    /** Fades the alert out, then removes it (or hides it when it has data-hide). */
    static close(alert) {
      alert.classList.add('alert-closing');
      setTimeout(() => {
        if (alert.hasAttribute('data-hide')) {
          alert.hidden = true;
          alert.classList.remove('alert-closing');
        } else {
          alert.remove();
        }
      }, 200);
    }
  }

  /* ---------- Toast ---------- */
  class Toast {
    static ICONS = {
      primary: 'fas fa-bell',
      success: 'fas fa-check-circle',
      warning: 'fas fa-exclamation-triangle',
      danger:  'fas fa-times-circle',
      info:    'fas fa-info-circle',
      light:   'far fa-lightbulb',
      dark:    'fas fa-moon',
    };
    static defaults = { type: 'primary', title: '', text: '', icon: '', timeout: 5000, position: 'top-right' };

    /** One container per position, created on first use. */
    static stack(position) {
      let stack = document.querySelector(`.toast-stack.${position}`);
      if (!stack) {
        stack = document.createElement('div');
        stack.className = `toast-stack ${position}`;
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
      }
      return stack;
    }

    static show(options) {
      return new Toast(options).show();
    }

    constructor(options = {}) {
      this.options = Object.assign({}, Toast.defaults, options);
      if (!Toast.ICONS[this.options.type]) this.options.type = 'primary';
      this.el = this.build();
    }

    build() {
      const { type, title, text, icon, timeout } = this.options;
      const item = document.createElement('div');
      item.className = `toast-item ${type}`;
      item.setAttribute('role', type === 'danger' || type === 'warning' ? 'alert' : 'status');

      const ico = document.createElement('span');
      ico.className = 'alert-ico boxed';
      ico.innerHTML = `<i class="${icon || Toast.ICONS[type]}"></i>`;

      const body = document.createElement('div');
      body.className = 'alert-body';
      if (title) body.appendChild(Object.assign(document.createElement('strong'), { className: 'alert-title', textContent: title }));
      if (text) body.appendChild(Object.assign(document.createElement('p'), { textContent: text }));

      const close = document.createElement('button');
      close.type = 'button';
      close.className = 'alert-close';
      close.setAttribute('aria-label', 'Close');
      close.innerHTML = '<i class="fas fa-times"></i>';
      close.addEventListener('click', () => this.close());

      item.append(ico, body, close);

      if (timeout > 0) {
        // The timer bar drives closing, so hovering (which pauses the bar) keeps the toast open
        const bar = document.createElement('span');
        bar.className = 'toast-timer';
        bar.style.animationDuration = `${timeout}ms`;
        bar.addEventListener('animationend', () => this.close());
        item.appendChild(bar);
      }
      return item;
    }

    show() {
      Toast.stack(this.options.position).appendChild(this.el);
      return this;
    }

    close() {
      if (this.el.classList.contains('toast-out')) return;
      this.el.classList.add('toast-out');
      setTimeout(() => this.el.remove(), 200);
    }
  }

  /* ---------- Toast trigger: <button data-toast="success" data-title="…"> ---------- */
  class ToastTrigger extends Odin.Component {
    static selector = '[data-toast]';

    init() {
      this.on(this.el, 'click', () => Toast.show({
        type: this.el.dataset.toast,
        title: this.data('title', ''),
        text: this.data('text', ''),
        icon: this.data('icon', ''),
        position: this.data('position', Toast.defaults.position),
        timeout: this.data('timeout', Toast.defaults.timeout),
      }));
    }
  }

  Odin.dismissible('alert', Alert.selector, Alert.close);
  Odin.register('toastTrigger', ToastTrigger);

  Odin.Alert = Alert;
  Odin.Toast = Toast;
  Odin.toast = options => Toast.show(options);
  window.OdinAlerts = { toast: Odin.toast, close: Alert.close }; // older name, kept for compatibility
})(window.Odin);
