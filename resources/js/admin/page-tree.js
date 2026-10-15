// The page tree (Pages → index): jsTree on the left, the selected page's details on the right.
//
// <div data-page-tree data-reorder="{reorder url}" data-max-depth="5">
// <script type="application/json" data-page-tree-data>[jsTree nodes]</script>
// <div data-page-panel> with [data-panel-*] parts (see Modules/Pages/Resources/views/admin/index.blade.php).
//
// A drop is saved right away: { items: [{id, parent_id}, …] } in display order. If the server refuses
// (e.g. two pages with the same slug on one level), the tree goes back to the last saved order.

import $ from 'jquery'; // only jsTree needs it; it is bundled into this chunk
import 'jstree';
import 'jstree/dist/themes/default/style.css';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
// OdinAdmin's toast (type: success | danger)
const toast = (message, type) => window.Odin?.toast?.({ type: type === 'error' ? 'danger' : type, text: message });

export function initPageTree() {
    const el = document.querySelector('[data-page-tree]');
    const source = document.querySelector('[data-page-tree-data]');
    if (!el || !source) return;

    const reorderUrl = el.dataset.reorder || null;
    const maxDepth = Number(el.dataset.maxDepth) || 5;
    const panel = createPanel(document.querySelector('[data-page-panel]'));

    let saving = false;
    let lastSaved = JSON.parse(source.textContent);

    $(el).jstree({
        core: {
            data: lastSaved,
            multiple: false,
            themes: { name: 'default', dots: true, icons: true, responsive: false },
            // Only moves, by users who may reorder, within the depth limit.
            check_callback: (operation, node, parent) => {
                if (operation !== 'move_node') return false;
                if (!reorderUrl || saving) return false;

                return depthOf(parent) + heightOf(node) <= maxDepth;
            },
        },
        plugins: ['wholerow', 'state', ...(reorderUrl ? ['dnd'] : [])],
        dnd: { copy: false, inside_pos: 'last', is_draggable: () => Boolean(reorderUrl) && !saving },
        // Remembers the open branches and the selected page (e.g. after coming back from the edit form).
        state: { key: 'odincms.admin.pages.tree' },
    });

    const tree = $(el).jstree(true);

    // A level of 1 for top-level pages; jsTree's parents include the root "#".
    function depthOf(parent) {
        return parent.id === '#' ? 0 : parent.parents.length;
    }

    // The levels a node takes with its subpages.
    function heightOf(node) {
        return (node.children_d ?? []).reduce(
            (height, id) => Math.max(height, tree.get_node(id).parents.length - node.parents.length + 1),
            1,
        );
    }

    const showSelected = () => panel.show(tree.get_selected(true)[0] ?? null);

    $(el)
        .on('changed.jstree', showSelected)
        .on('ready.jstree state_ready.jstree refresh.jstree', showSelected)
        .on('move_node.jstree', () => save());

    function save() {
        saving = true;
        el.classList.add('is-saving');

        const items = tree.get_json('#', { flat: true }).map((node) => ({
            id: Number(node.id),
            parent_id: node.parent === '#' ? null : Number(node.parent),
        }));

        fetch(reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ items }),
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || response.statusText);

                // New addresses and folder icons after the move.
                Object.entries(data.pages ?? {}).forEach(([id, page]) => {
                    const node = tree.get_node(id);
                    if (node) Object.assign(node.data, page);
                });
                updateIcons();
                lastSaved = tree.get_json('#');
                showSelected();
                toast(data.message, 'success');
            })
            .catch((error) => {
                toast(error.message, 'error');
                tree.settings.core.data = lastSaved;
                tree.refresh();
            })
            .finally(() => {
                saving = false;
                el.classList.remove('is-saving');
            });
    }

    function updateIcons() {
        tree.get_json('#', { flat: true }).forEach(({ id }) => {
            const node = tree.get_node(id);
            tree.set_icon(node, node.children.length ? 'fas fa-folder' : 'far fa-file-alt');
        });
    }
}

/**
 * The details panel: title, image, slug, address, layout, and the Edit and Delete buttons.
 */
function createPanel(root) {
    const part = (name) => root?.querySelector(`[data-panel-${name}]`);
    const empty = part('empty');
    const page = part('page');
    const image = part('image');
    const status = part('status');
    const edit = part('edit');
    const deleteForm = part('delete')?.querySelector('form');

    return {
        show(node) {
            if (!root) return;

            const data = node?.data;
            empty.hidden = Boolean(data);
            page.hidden = !data;
            if (!data) return;

            part('title').textContent = data.title;
            part('slug').textContent = data.slug;
            part('path').textContent = `/${data.path}`;
            part('url').href = data.url;
            part('layout').textContent = data.layout;

            status.hidden = data.published;
            status.textContent = data.status;

            image.hidden = !data.image;
            if (data.image) {
                image.src = data.image;
                image.alt = data.title;
            } else {
                image.removeAttribute('src');
            }

            if (edit) edit.href = data.edit;
            if (deleteForm) {
                deleteForm.action = data.destroy;
                deleteForm.dataset.confirm = data.delete_confirm;
            }
        },
    };
}
