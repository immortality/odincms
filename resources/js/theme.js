// Frontend theme scripts, built into one deferred file. Only the Bootstrap components the theme uses
// are bundled (dropdown menus, the mobile offcanvas, carousels, collapse); each one enables its
// data-bs-* attributes when imported.
import Carousel from 'bootstrap/js/src/carousel';
import Collapse from 'bootstrap/js/src/collapse';
import Dropdown from 'bootstrap/js/src/dropdown';
import Offcanvas from 'bootstrap/js/src/offcanvas';

// The template's script (html/assets/js/index6.js) uses the global "bootstrap", as with the CDN build.
window.bootstrap = { Carousel, Collapse, Dropdown, Offcanvas };

// Imports run first, but the template only touches window.bootstrap inside click handlers.
import './themes/default/index6';

// Progress bars ([data-progress], e.g. the "Why choose us" section) start empty and grow to their value when
// they scroll into view: the template's IntersectionObserver (index6.js) sets their width. Without JS, or with
// reduced motion, they show their full value at once.
if (document.querySelector('[data-progress]') && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('has-progress-js');
}
