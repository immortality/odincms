/* ============================================================
   OdinAdmin — core. Load on every page, after the Bootstrap bundle
   and before any other OdinAdmin script.

   Plain JavaScript, no jQuery. Every widget is a class that extends
   Odin.Component and is registered once:

     class Counter extends Odin.Component {
       static selector = '[data-counter]';     // elements it attaches to
       static defaults = { step: 1 };          // merged with data-* options you pass
       init() { this.on(this.el, 'click', () => …); }
     }
     Odin.register('counter', Counter);

   On DOMContentLoaded, Odin.init() creates one instance per matching
   element. Content added later: call Odin.init(container).
   Get an instance: Odin.get(element, 'counter').

   Also here:
     Odin.delegate(event, selector, handler)   one document-level listener
     Odin.dismissible(type, selector, closeFn) [data-dismiss="type"] buttons
     Odin.$ / Odin.$$                          querySelector helpers
   Components in this file: Sidebar, Submenu, Tooltip, and the "badge" dismiss type.
   ============================================================ */
(function (window, document) {
  'use strict';

  const $  = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  /* ---------- Base class ---------- */
  class Component {
    static selector = '';
    static defaults = {};

    constructor(element, options = {}) {
      this.el = element;
      this.options = Object.assign({}, this.constructor.defaults, options);
      this.init();
    }

    /** Override in subclasses. */
    init() {}

    $(selector)  { return $(selector, this.el); }
    $$(selector) { return $$(selector, this.el); }

    /** addEventListener that defaults to this.el and returns the handler. */
    on(target, event, handler, options) {
      (target || this.el).addEventListener(event, handler, options);
      return handler;
    }

    /** Dispatches a bubbling "odin:<name>" event from this.el. */
    emit(name, detail = {}) {
      this.el.dispatchEvent(new CustomEvent(`odin:${name}`, { bubbles: true, detail }));
    }

    /** Reads data-* attributes: "true"/"false" → boolean, numeric strings → number. */
    data(key, fallback) {
      const value = this.el.dataset[key];
      if (value === undefined || value === '') return fallback;
      if (value === 'true') return true;
      if (value === 'false') return false;
      return value !== '' && !isNaN(value) ? Number(value) : value;
    }
  }

  /* ---------- Registry ---------- */
  const Odin = {
    Component,
    $,
    $$,
    components: new Map(),     // name → class
    instances: new WeakMap(),  // element → Map(name → instance)
    ready: false,

    register(name, ComponentClass) {
      this.components.set(name, ComponentClass);
      if (this.ready) this.init(document, name);
      return ComponentClass;
    },

    /** Creates instances for every registered component (or only `name`) inside `root`. */
    init(root = document, name) {
      this.components.forEach((ComponentClass, key) => {
        if (name && key !== name) return;
        const selector = ComponentClass.selector;
        if (!selector) return;
        const matches = $$(selector, root);
        if (root !== document && root.matches && root.matches(selector)) matches.unshift(root);
        matches.forEach(el => this.create(key, el));
      });
    },

    create(name, element, options) {
      const ComponentClass = this.components.get(name);
      if (!ComponentClass) throw new Error(`Odin: unknown component "${name}"`);
      let map = this.instances.get(element);
      if (!map) this.instances.set(element, (map = new Map()));
      if (!map.has(name)) map.set(name, new ComponentClass(element, options));
      return map.get(name);
    },

    get(element, name) {
      const map = this.instances.get(element);
      return map ? map.get(name) : undefined;
    },

    /** One listener on document for all current and future matching elements. */
    delegate(event, selector, handler) {
      document.addEventListener(event, e => {
        const target = e.target.closest && e.target.closest(selector);
        if (target) handler(e, target);
      });
    },

    /** <button data-dismiss="type"> closes its closest `selector` with `close(element)`. */
    dismissTypes: new Map(),
    dismissible(type, selector, close = el => el.remove()) {
      this.dismissTypes.set(type, { selector, close });
    },
  };

  Odin.delegate('click', '[data-dismiss]', (e, button) => {
    const type = Odin.dismissTypes.get(button.dataset.dismiss);
    if (!type) return;
    const target = button.closest(type.selector);
    if (!target) return;
    e.preventDefault();
    type.close(target);
  });

  /* ---------- Sidebar: mobile off-canvas ---------- */
  class Sidebar extends Component {
    static selector = '#sidebar';
    static defaults = { breakpoint: 992 };

    init() {
      this.backdrop = $('#backdrop');
      this.burger = $('#burger');

      if (this.burger) this.on(this.burger, 'click', () => this.open());
      if (this.backdrop) this.on(this.backdrop, 'click', () => this.close());
      this.on(window, 'resize', () => { if (!this.isMobile()) this.close(); });
      this.on(document, 'keydown', e => { if (e.key === 'Escape') this.close(); });

      // Close after picking a link on mobile (submenu triggers stay open)
      this.$$('.sb-nav .nav-link').forEach(link => this.on(link, 'click', () => {
        if (this.isMobile() && !link.matches('[data-toggle="submenu"]')) this.close();
      }));
    }

    isMobile() { return window.innerWidth < this.options.breakpoint; }

    open() {
      this.el.classList.add('open');
      if (this.backdrop) this.backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';
      this.emit('sidebar-open');
    }

    close() {
      if (!this.el.classList.contains('open')) return;
      this.el.classList.remove('open');
      if (this.backdrop) this.backdrop.classList.remove('show');
      document.body.style.overflow = '';
      this.emit('sidebar-close');
    }
  }

  /* ---------- Submenu: <a data-toggle="submenu" data-target="#id"> ---------- */
  class Submenu extends Component {
    static selector = '[data-toggle="submenu"]';

    init() {
      this.panel = $(this.el.dataset.target);
      if (!this.panel) return;
      this.el.setAttribute('aria-expanded', String(this.isOpen()));
      this.on(this.el, 'click', e => {
        e.preventDefault();
        this.toggle();
      });
    }

    isOpen() { return this.panel.classList.contains('open'); }

    toggle(force) {
      const open = force === undefined ? !this.isOpen() : force;
      this.panel.classList.toggle('open', open);
      this.el.classList.toggle('open', open);
      this.el.setAttribute('aria-expanded', String(open));
    }
  }

  /* ---------- Tooltip: Bootstrap tooltip on any [data-bs-toggle="tooltip"] ----------
     Text from title="…". Options are Bootstrap's own data attributes:
     data-bs-placement="top|right|bottom|left", data-bs-custom-class="tooltip-light",
     data-bs-html="true", data-bs-trigger="click". Styles: css/components/tooltips.css */
  class Tooltip extends Component {
    static selector = '[data-bs-toggle="tooltip"]';

    init() {
      if (!window.bootstrap) return;
      // Bootstrap's sanitizer drops <kbd> in data-bs-html tooltips; allow it for shortcuts
      const allowList = Object.assign({}, bootstrap.Tooltip.Default.allowList, { kbd: [] });
      this.tip = bootstrap.Tooltip.getOrCreateInstance(this.el, { allowList });
    }
  }

  Odin.register('sidebar', Sidebar);
  Odin.register('submenu', Submenu);
  Odin.register('tooltip', Tooltip);

  // Removable badges (tags, active filters)
  Odin.dismissible('badge', '.badge-soft, .badge-solid, .badge-outline');

  const start = () => { Odin.ready = true; Odin.init(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();

  window.Odin = Odin;
})(window, document);
