// OdinCMS admin scripts.
//
// Loaded as a module on every admin page (layouts/app), after Bootstrap, SweetAlert2 and the
// OdinAdmin scripts (window.Odin). Modules must not load global libraries themselves;
// use @push('admin-scripts').

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

window.Alpine = Alpine;
window.Sortable = Sortable;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** A short notice (OdinAdmin's Odin.toast). type: success | error */
const toast = (message, type = 'success') => {
    if (!message) return;
    window.Odin?.toast?.({ type: type === 'error' ? 'danger' : type, text: message });
};

/**
 * Forms with data-confirm ask before submitting (delete, uninstall, ...).
 * <form data-confirm="Delete this post?" data-confirm-button="Delete">
 */
function initConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) {
            return;
        }
        event.preventDefault();

        const proceed = () => {
            form.dataset.confirmed = '1';
            form.requestSubmit(event.submitter ?? undefined);
        };

        if (!window.Swal) {
            if (window.confirm(form.dataset.confirm)) proceed();
            return;
        }

        window.Swal.fire({
            title: form.dataset.confirm,
            text: form.dataset.confirmText ?? '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: form.dataset.confirmButton ?? 'Yes',
            cancelButtonText: form.dataset.cancelButton ?? 'Cancel',
            // OdinAdmin buttons (the SweetAlert2 theme is in odinadmin/css/vendor/sweetalert2.css)
            buttonsStyling: false,
            reverseButtons: true,
            focusCancel: true,
            customClass: { confirmButton: 'btn-brand danger', cancelButton: 'btn-soft' },
        }).then((result) => {
            if (result.isConfirmed) proceed();
        });
    });
}

/**
 * Row checkboxes and bulk actions.
 * Head checkbox: .js-check-all[data-form="bulk-form"]; rows: .js-row-check[form="bulk-form"].
 * The bar (.js-bulk-action.bulk-bar, <x-admin.bulk-actions>) is shown only while rows are checked; its
 * .js-bulk-count shows data-text with :count replaced, the Apply button (.js-bulk-apply) is enabled once
 * an action is chosen, and .js-bulk-clear unchecks every row.
 */
function initBulkActions() {
    const update = (formId) => {
        const checked = document.querySelectorAll(`.js-row-check[form="${formId}"]:checked`).length;
        document.querySelectorAll(`.js-bulk-action[data-form="${formId}"]`).forEach((wrap) => {
            const select = wrap.querySelector('select');
            const button = wrap.querySelector('.js-bulk-apply');
            button.disabled = checked === 0 || !select.value;
            wrap.classList.toggle('show', checked > 0);
            const counter = wrap.querySelector('.js-bulk-count');
            if (counter) counter.textContent = (counter.dataset.text ?? ':count').replace(':count', checked);
        });
    };

    document.addEventListener('click', (event) => {
        const clear = event.target.closest('.js-bulk-clear');
        if (!clear) return;
        const formId = clear.closest('.js-bulk-action').dataset.form;
        document.querySelectorAll(`.js-row-check[form="${formId}"], .js-check-all[data-form="${formId}"]`).forEach((box) => {
            box.checked = false;
        });
        update(formId);
    });

    document.addEventListener('change', (event) => {
        const el = event.target;
        if (el.matches('.js-check-all')) {
            const formId = el.dataset.form;
            document.querySelectorAll(`.js-row-check[form="${formId}"]`).forEach((box) => {
                box.checked = el.checked;
            });
            update(formId);
        } else if (el.matches('.js-row-check')) {
            update(el.getAttribute('form'));
        } else if (el.closest('.js-bulk-action')) {
            update(el.closest('.js-bulk-action').dataset.form);
        }
    });
}

/**
 * Drag-and-drop ordering of a flat list.
 * <div data-sortable="{{ route('admin.blog.posts.reorder') }}"> children with data-id and a .sortable-handle.
 * Posts { ids: [...] } in the new order.
 */
function initSortables() {
    document.querySelectorAll('[data-sortable]').forEach((list) => {
        Sortable.create(list, {
            handle: '.sortable-handle',
            animation: 150,
            onEnd: () => {
                const ids = [...list.children].map((child) => child.dataset.id).filter(Boolean);
                fetch(list.dataset.sortable, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ ids }),
                }).then((response) => {
                    if (!response.ok) throw new Error(response.statusText);
                    toast('Order saved');
                }).catch(() => {
                    toast('Could not save the order', 'error');
                });
            },
        });
    });
}

/**
 * Drag-and-drop trees (menu links, categories), rendered by <x-admin.tree>.
 * <div data-tree="{reorder url}"> holds nested ul.cms-tree > li.cms-tree-item[data-id] (each with a
 * .sortable-handle) and a [data-tree-save] button, which posts { items: [{id, parent_id}, …] } in display order.
 */
function initTrees() {
    document.querySelectorAll('[data-tree]').forEach((root, index) => {
        const save = root.querySelector('[data-tree-save]');

        root.querySelectorAll('.cms-tree').forEach((list) => Sortable.create(list, {
            group: `cms-tree-${index}`,
            handle: '.sortable-handle',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: () => {
                if (save) save.disabled = false;
            },
        }));

        save?.addEventListener('click', () => {
            const items = [...root.querySelectorAll('.cms-tree-item')].map((item) => {
                const parent = item.parentElement.closest('.cms-tree-item');
                return { id: Number(item.dataset.id), parent_id: parent ? Number(parent.dataset.id) : null };
            });

            save.disabled = true;
            fetch(root.dataset.tree, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ items }),
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || response.statusText);
                    toast(data.message);
                })
                .catch((error) => {
                    toast(error.message, 'error');
                    save.disabled = false;
                });
        });
    });
}

/**
 * Tree tables (Pages), odinadmin/js/tree-table.js: "Save order" posts { items: [{id, parent_id}, …] }
 * in display order to data-reorder. If the server refuses, the "order has changed" bar comes back.
 * <div data-tree-table data-reorder="{reorder url}">
 */
function initTreeTables() {
    document.addEventListener('odin:tree-order-save', (event) => {
        const root = event.target.closest('[data-tree-table][data-reorder]');
        if (!root) return;

        const items = event.detail.order.map((item) => ({ id: Number(item.id), parent_id: item.parent ? Number(item.parent) : null }));
        const restore = () => {
            const table = window.Odin?.get?.(root, 'treeTable');
            if (!table) return;
            table.saved = event.detail.previous;
            table.checkOrder();
        };

        fetch(root.dataset.reorder, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ items }),
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || root.dataset.reorderError || response.statusText);
                toast(data.message);
            })
            .catch((error) => {
                toast(error.message, 'error');
                restore();
            });
    });
}

initConfirmForms();
initBulkActions();
initSortables();
initTrees();
initTreeTables();

// TinyMCE is large: load it only on pages that contain an editor.
if (document.querySelector('[data-editor]')) {
    import('./admin/editor.js').then(({ initEditors }) => initEditors());
}

// The image cropper too: only where a <x-admin.form.cropped-image> is.
if (document.querySelector('[data-cropped-image]')) {
    import('./admin/cropped-image.js').then(({ initCroppedImages }) => initCroppedImages());
}

// Leaflet: only where a <x-admin.form.map> is.
if (document.querySelector('[data-map-picker]')) {
    import('./admin/map-picker.js').then(({ initMapPickers }) => initMapPickers());
}

Alpine.start();
