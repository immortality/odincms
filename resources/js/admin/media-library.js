// The media library page (modules/Media): OdinAdmin's file manager (odinadmin/js/file-manager.js, which
// handles selecting, checking, the grid / list switch and drag and drop) with real uploads, renaming,
// deleting and moving. Folders, filters, sorting and search are links, so the server keeps the order.
// Loaded only on that page.

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const VIEW_KEY = 'odincms.media.view';

function format(text, values) {
    return Object.entries(values).reduce((result, [key, value]) => result.replaceAll(`:${key}`, value), text ?? '');
}

/**
 * Laravel's trans_choice in the browser: "{0} No files|{1} 1 file|[2,*] :count files".
 */
function choice(text, count) {
    const parts = String(text ?? '').split('|');
    for (const part of parts) {
        const exact = part.match(/^\{(\d+)\}\s*(.*)$/s);
        if (exact && Number(exact[1]) === count) return format(exact[2], { count });
        const range = part.match(/^\[(\d+|\*),\s*(\d+|\*)\]\s*(.*)$/s);
        if (range && (range[1] === '*' || count >= Number(range[1])) && (range[2] === '*' || count <= Number(range[2]))) {
            return format(range[3], { count });
        }
    }
    const plain = parts.filter((part) => !/^[{[]/.test(part));
    return format(plain[count === 1 ? 0 : plain.length - 1] ?? parts[0], { count });
}

const toast = (message, type = 'success') => {
    if (message) window.Odin?.toast?.({ type: type === 'error' ? 'danger' : type, text: message });
};

/** The first validation error or message of a JSON error answer. */
function errorMessage(data, fallback) {
    const errors = data?.errors ? Object.values(data.errors).flat() : [];
    return errors[0] ?? data?.message ?? fallback;
}

/** POST a form (with _method for PATCH / DELETE) and return the JSON answer, or throw its error. */
async function send(url, method, fields, fallback) {
    const body = new FormData();
    if (method !== 'POST') body.append('_method', method);
    Object.entries(fields ?? {}).forEach(([key, value]) => body.append(key, value));

    const response = await fetch(url, {
        method: 'POST',
        body,
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(errorMessage(data, fallback));

    return data;
}

const swalButtons = { confirmButton: 'btn-brand', cancelButton: 'btn-soft' };

export function initMediaLibrary() {
    const Odin = window.Odin;
    if (!Odin?.FileManager) return;

    class MediaLibrary extends Odin.FileManager {
        static selector = '[data-media-library]';

        init() {
            this.strings = JSON.parse(this.el.dataset.strings || '{}');
            this.uploadUrl = this.el.dataset.uploadUrl;
            this.folderId = this.el.dataset.folderId || '';
            this.maxBytes = Number(this.el.dataset.maxKb || 0) * 1024;

            super.init();
            this.empty = this.$('.fm-section .fm-empty'); // not the one in the details panel

            this.restoreView();
            this.on(this.el, 'click', (e) => this.onAction(e));

            const move = document.querySelector('[data-media-move]');
            if (move) this.on(move, 'click', () => this.submitBulk('move', { folder_id: document.getElementById('media-move-folder')?.value ?? '' }));

            ['media-folder-modal', 'media-rename-folder-modal'].forEach((id) => {
                const modal = document.getElementById(id);
                if (modal) modal.addEventListener('shown.bs.modal', () => modal.querySelector('input[name="name"]')?.select());
            });

            // A folder form came back with errors: show it again.
            const open = { create: 'media-folder-modal', rename: 'media-rename-folder-modal' }[this.el.dataset.openModal];
            if (open && document.getElementById(open)) window.bootstrap.Modal.getOrCreateInstance(document.getElementById(open)).show();

            if (!this.files().length) this.showNothing();
        }

        /* ---------- The server orders and filters ---------- */
        sort() {
            this.apply();
        }

        apply() {
            if (this.empty) this.empty.hidden = this.files().length > 0;
        }

        restoreView() {
            const radios = this.$$('[data-fm-view]');
            try {
                const saved = window.localStorage.getItem(VIEW_KEY);
                const radio = radios.find((r) => r.value === saved);
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            } catch {
                // no storage (private window): the grid stays
            }
            radios.forEach((radio) => this.on(radio, 'change', () => {
                try {
                    if (radio.checked) window.localStorage.setItem(VIEW_KEY, radio.value);
                } catch {
                    // ignore
                }
            }));
        }

        /* ---------- Details ---------- */
        showInfo(file) {
            if (!this.info) return;
            const d = file.dataset;
            this.current = file;
            this.$('[data-media-info-empty]').hidden = true;
            this.$('[data-media-info-body]').hidden = false;

            const preview = this.$('[data-fm-field="preview"]');
            preview.className = `fm-preview ${d.src ? '' : d.tone}`;
            preview.replaceChildren();
            if (d.src) {
                const img = document.createElement('img');
                img.src = d.src;
                img.alt = '';
                preview.append(img);
            } else {
                const icon = document.createElement('i');
                icon.className = d.icon;
                preview.append(icon);
            }

            const fields = { name: d.name, type: d.type, size: d.sizeLabel, dims: d.dims, date: d.date, owner: d.owner, folder: d.folder };
            Object.entries(fields).forEach(([name, value]) => {
                this.$$(`[data-fm-field="${name}"]`).forEach((el) => { el.textContent = value ?? '—'; });
            });
            this.$('[data-fm-field="url"]').value = d.url;
            this.$('[data-fm-field="download"]').href = d.download;
        }

        showNothing() {
            this.current = null;
            const empty = this.$('[data-media-info-empty]');
            if (empty) empty.hidden = false;
            const body = this.$('[data-media-info-body]');
            if (body) body.hidden = true;
        }

        updateChecked() {
            super.updateChecked();
            const count = this.checks().filter((c) => c.checked).length;
            const label = this.$('[data-fm-bulk-count]');
            if (label && count) label.textContent = choice(this.strings.selected, count);
        }

        /* ---------- Actions ---------- */
        onAction(e) {
            const button = e.target.closest('[data-media-copy], [data-media-rename], [data-fm-delete]');
            if (!button) return;
            const file = button.closest('.fm-file') ?? this.current;
            if (!file) return;

            // [data-fm-delete] inside a file is handled by FileManager itself.
            if (button.matches('[data-fm-delete]')) {
                if (!button.closest('.fm-file')) {
                    e.preventDefault();
                    this.remove([file]);
                }
                return;
            }

            e.preventDefault();
            if (button.matches('[data-media-copy]')) this.copy(file.dataset.url);
            else this.rename(file);
        }

        async copy(url) {
            try {
                await navigator.clipboard.writeText(url);
            } catch {
                const input = this.$('[data-fm-field="url"]');
                input.value = url;
                input.select();
                document.execCommand('copy');
            }
            toast(this.strings.linkCopied);
        }

        async confirm(title) {
            if (!window.Swal) return window.confirm(title);
            const result = await window.Swal.fire({
                title,
                text: this.strings.deleteText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: this.strings.delete,
                cancelButtonText: this.strings.cancel,
                buttonsStyling: false,
                reverseButtons: true,
                focusCancel: true,
                customClass: { confirmButton: 'btn-brand danger', cancelButton: 'btn-soft' },
            });
            return result.isConfirmed;
        }

        async remove(files) {
            files = files.filter(Boolean);
            if (!files.length) return;

            const title = files.length === 1
                ? format(this.strings.deleteConfirm, { name: files[0].dataset.name })
                : format(this.strings.deleteManyConfirm, { count: files.length });
            if (!(await this.confirm(title))) return;

            // Several files: one request, then the page reloads with the message.
            if (files.length > 1) {
                this.submitBulk('delete');
                return;
            }

            try {
                const data = await send(files[0].dataset.delete, 'DELETE', {}, this.strings.error);
                const wasCurrent = files[0] === this.current;
                files[0].remove();
                this.updateChecked();
                this.apply();
                const next = this.$('.fm-file');
                if (wasCurrent) next ? this.select(next, false) : this.showNothing();
                toast(data.message);
            } catch (error) {
                toast(error.message, 'error');
            }
        }

        rename(file) {
            const d = file.dataset;
            const stem = d.name.replace(new RegExp(`\\.${d.ext}$`, 'i'), '');
            const save = async (name) => {
                const data = await send(d.update, 'PATCH', { name }, this.strings.error);
                d.name = data.name;
                file.querySelector('.fm-name').textContent = data.name;
                if (file === this.current) this.showInfo(file);
                toast(data.message);
            };

            if (!window.Swal) {
                const name = window.prompt(this.strings.fileName, stem);
                if (name && name.trim()) save(name.trim()).catch((error) => toast(error.message, 'error'));
                return;
            }

            window.Swal.fire({
                title: this.strings.rename,
                input: 'text',
                inputLabel: this.strings.fileName,
                inputValue: stem,
                inputAttributes: { maxlength: 180 },
                showCancelButton: true,
                confirmButtonText: this.strings.save,
                cancelButtonText: this.strings.cancel,
                buttonsStyling: false,
                reverseButtons: true,
                customClass: swalButtons,
                showLoaderOnConfirm: true,
                inputValidator: (value) => (value.trim() ? undefined : this.strings.fileName),
                preConfirm: (value) => save(value.trim()).catch((error) => window.Swal.showValidationMessage(error.message)),
            });
        }

        /** Post the bulk form (the page reloads with a message). */
        submitBulk(action, fields = {}) {
            const form = this.$('[data-media-bulk]');
            if (!form) return;
            form.querySelector('[name="action"]').value = action;
            form.querySelectorAll('[data-generated]').forEach((input) => input.remove());

            const add = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                input.dataset.generated = '';
                form.append(input);
            };
            this.checks().filter((c) => c.checked).forEach((c) => add('ids[]', c.value));
            Object.entries(fields).forEach(([name, value]) => add(name, value));
            form.submit();
        }

        /* ---------- Upload: one file per request, one after another ---------- */
        async upload(files) {
            if (!this.uploadUrl || !files.length) return;

            const panel = document.querySelector('.fm-uploads');
            const list = panel.querySelector('.fm-uploads-list');
            const title = panel.querySelector('[data-fm-upload-title]');
            const close = panel.querySelector('[data-fm-uploads-close]');
            if (!close.dataset.bound) {
                close.dataset.bound = '1';
                close.addEventListener('click', () => { panel.hidden = true; });
            }
            panel.hidden = false;

            let left = files.length;
            title.textContent = choice(this.strings.uploading, left);

            for (const file of files) {
                const row = document.createElement('div');
                row.className = 'fm-upload';
                row.innerHTML = '<div class="fm-upload-row"><span></span><span data-pct>0%</span></div><div class="src-bar"><span style="width:0"></span></div>';
                row.querySelector('span').textContent = file.name;
                list.prepend(row);

                try {
                    if (this.maxBytes && file.size > this.maxBytes) {
                        throw new Error(format(this.strings.tooLarge, { name: file.name, size: `${Math.round(this.maxBytes / 1048576)} MB` }));
                    }
                    const data = await this.send(file, row);
                    this.addItem(data.html);
                    row.querySelector('.src-bar span').classList.add('success');
                    row.querySelector('[data-pct]').innerHTML = '<i class="fas fa-check text-success"></i>';
                } catch (error) {
                    row.querySelector('.src-bar span').classList.add('danger');
                    row.querySelector('[data-pct]').innerHTML = '<i class="fas fa-times text-danger"></i>';
                    const message = document.createElement('div');
                    message.className = 'text-danger mt-1';
                    message.textContent = error.message;
                    row.append(message);
                }

                left--;
                title.textContent = left ? choice(this.strings.uploading, left) : this.strings.uploadDone;
            }
        }

        /** XMLHttpRequest, for the progress bar. */
        send(file, row) {
            return new Promise((resolve, reject) => {
                const body = new FormData();
                body.append('file', file);
                if (this.folderId) body.append('folder_id', this.folderId);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.uploadUrl);
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.addEventListener('progress', (e) => {
                    if (!e.lengthComputable) return;
                    const pct = Math.round((e.loaded / e.total) * 100);
                    row.querySelector('.src-bar span').style.width = `${pct}%`;
                    row.querySelector('[data-pct]').textContent = `${pct}%`;
                });
                xhr.addEventListener('load', () => {
                    let data = {};
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch {
                        // not JSON (e.g. PHP's post_max_size was exceeded)
                    }
                    if (xhr.status >= 200 && xhr.status < 300) resolve(data);
                    else reject(new Error(errorMessage(data, xhr.status === 413 ? format(this.strings.tooLarge, { name: file.name, size: `${Math.round(this.maxBytes / 1048576)} MB` }) : this.strings.uploadFailed)));
                });
                xhr.addEventListener('error', () => reject(new Error(this.strings.uploadFailed)));
                xhr.send(body);
            });
        }

        addItem(html) {
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const item = wrap.firstElementChild;
            this.list.prepend(item);
            this.apply();
            this.select(item, false);
        }
    }

    Odin.register('mediaLibrary', MediaLibrary);
}
