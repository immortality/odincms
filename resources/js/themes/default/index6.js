/* Velora — index6 interactions (vanilla JS + Bootstrap 5) */
(function () {
  'use strict';

  const $ = (s, ctx = document) => ctx.querySelector(s);
  const $$ = (s, ctx = document) => Array.from(ctx.querySelectorAll(s));

  /* Sticky header shadow + back-to-top */
  const header = $('.site-header');
  const toTop = $('.to-top');
  const onScroll = () => {
    const y = window.scrollY;
    header && header.classList.toggle('is-stuck', y > 60);
    toTop && toTop.classList.toggle('show', y > 600);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  toTop && toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  /* Close offcanvas when a link inside it is clicked (OdinCMS: not a dropdown parent, which opens its sub-menu) */
  $$('#mobileNav .nav-link:not(.dropdown-toggle), #mobileNav .dropdown-item').forEach((link) => {
    link.addEventListener('click', () => {
      const oc = bootstrap.Offcanvas.getInstance('#mobileNav');
      oc && oc.hide();
    });
  });

  /* Count-up numbers */
  const countUp = (el) => {
    const end = parseFloat(el.dataset.count);
    const dur = 1800;
    const start = performance.now();
    const step = (now) => {
      const p = Math.min((now - start) / dur, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * eased).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };

  /* Reveal, counters and progress bars share one observer */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      const el = e.target;
      if (el.classList.contains('reveal')) el.classList.add('in');
      if (el.dataset.count) countUp(el);
      if (el.dataset.progress) el.style.width = el.dataset.progress + '%';
      io.unobserve(el);
    });
  }, { threshold: 0.2 });
  $$('.reveal, [data-count], [data-progress]').forEach((el) => io.observe(el));

  /* Project filter */
  $$('.project-filter button').forEach((btn) => {
    btn.addEventListener('click', () => {
      $$('.project-filter button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      const f = btn.dataset.filter;
      $$('.project-item').forEach((item) => {
        item.classList.toggle('is-hidden', f !== '*' && !item.dataset.cat.split(' ').includes(f));
      });
    });
  });

  /* Pricing monthly / yearly */
  const toggle = $('.billing-toggle');
  if (toggle) {
    const pill = $('.pill', toggle);
    const buttons = $$('button', toggle);
    const movePill = (btn) => {
      pill.style.width = btn.offsetWidth + 'px';
      pill.style.transform = `translateX(${btn.offsetLeft - 5}px)`;
    };
    buttons.forEach((btn) => {
      btn.addEventListener('click', () => {
        buttons.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        movePill(btn);
        const period = btn.dataset.period;
        $$('[data-monthly]').forEach((p) => {
          p.textContent = p.dataset[period];
        });
        $$('.per').forEach((s) => (s.textContent = period === 'yearly' ? '/ year' : '/ month'));
      });
    });
    const sync = () => movePill($('button.active', toggle));
    window.addEventListener('resize', sync);
    document.fonts ? document.fonts.ready.then(sync) : sync();
  }

  /* Footer year */
  const yr = $('#year');
  yr && (yr.textContent = new Date().getFullYear());

  /* Demo forms */
  $$('form[data-demo]').forEach((f) => {
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const btn = $('button[type="submit"]', f);
      if (!btn) return;
      const html = btn.innerHTML;
      btn.innerHTML = 'Thank you! <i class="bi bi-check2"></i>';
      f.reset();
      setTimeout(() => (btn.innerHTML = html), 2600);
    });
  });
})();
