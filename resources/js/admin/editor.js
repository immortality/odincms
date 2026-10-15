// TinyMCE (self-hosted, GPL) bundled by Vite.
// TinyMCE's own "oxide" skin and content CSS, bundled here and loaded on first use;
// resources/css/admin.scss frames the editor like an OdinAdmin .form-soft field.

import tinymce from 'tinymce/tinymce';
import 'tinymce/models/dom/model';
import 'tinymce/themes/silver';
import 'tinymce/icons/default';

import 'tinymce/plugins/advlist';
import 'tinymce/plugins/anchor';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/charmap';
import 'tinymce/plugins/code';
import 'tinymce/plugins/fullscreen';
import 'tinymce/plugins/image';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/media';
import 'tinymce/plugins/preview';
import 'tinymce/plugins/searchreplace';
import 'tinymce/plugins/table';
import 'tinymce/plugins/visualblocks';
import 'tinymce/plugins/wordcount';

const toolbars = {
    full: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | '
        + 'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | '
        + 'link image media table | removeformat code fullscreen',
    basic: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
};

/** Loads the oxide UI skin and returns the CSS for the content area. */
async function loadSkin() {
    const [, contentUi, content] = await Promise.all([
        import('tinymce/skins/ui/oxide/skin.min.css'),
        import('tinymce/skins/ui/oxide/content.min.css?inline'),
        import('tinymce/skins/content/default/content.min.css?inline'),
    ]);

    return [contentUi.default, content.default, 'body { font-family: Inter, system-ui, sans-serif; font-size: 14px; }'].join('\n');
}

export async function initEditors(root = document) {
    const contentStyle = await loadSkin();

    root.querySelectorAll('textarea[data-editor]').forEach((textarea) => {
        if (tinymce.get(textarea.id)) return;

        const mode = textarea.dataset.editor === 'basic' ? 'basic' : 'full';

        tinymce.init({
            target: textarea,
            license_key: 'gpl',
            skin: false,
            content_css: false,
            content_style: contentStyle,
            branding: false,
            promotion: false,
            menubar: mode === 'full' ? 'edit view insert format table' : false,
            plugins: 'advlist anchor autolink charmap code fullscreen image link lists media preview searchreplace table visualblocks wordcount',
            toolbar: toolbars[mode],
            height: Number(textarea.dataset.height ?? (mode === 'full' ? 420 : 220)),
            convert_urls: false,
            // Keep any markup typed in the Source code view: every element and attribute (classes, ids,
            // data-*, styles), inline SVG and empty icon tags (<i class="bi bi-star"></i>). Without this
            // TinyMCE drops unknown attributes and empty <i>/<span>, and turns <i>/<b> into <em>/<strong>.
            // The server removes active content on save (App\Cms\Support\HtmlSanitizer).
            verify_html: false,
            // Block content inside links (card links) and <style> blocks in the content.
            valid_children: '+a[div|p|h1|h2|h3|h4|h5|h6|ul|ol|li|img|svg|span|i|section|figure],+body[style],+div[style],+section[style]',
            setup: (editor) => {
                // Keep the textarea in sync so plain form posts and validation see the content.
                editor.on('change input undo redo', () => editor.save());
            },
        });
    });
}
