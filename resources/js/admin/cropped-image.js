// <x-admin.form.cropped-image>: drag and drop (or Select) an image, crop it in a modal to the
// module's proportions, and post the original with the crop area. Loaded only on pages that use it.

import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function format(text, values) {
    return Object.entries(values).reduce((result, [key, value]) => result.replaceAll(`:${key}`, value), text);
}

function setup(root) {
    const width = Number(root.dataset.cropWidth);
    const height = Number(root.dataset.cropHeight);
    const maxKb = Number(root.dataset.maxKb);
    const strings = JSON.parse(root.dataset.strings);

    const fileInput = root.querySelector('[data-file]');
    const cropInput = root.querySelector('[data-crop]');
    const removeInput = root.querySelector('[data-remove]');
    const preview = root.querySelector('[data-preview]');
    const previewImage = root.querySelector('[data-preview-image]');
    const drop = root.querySelector('[data-drop]');
    const removed = root.querySelector('[data-removed]');
    const error = root.querySelector('[data-error]');
    const modalElement = root.querySelector('[data-modal]');
    const cropImage = modalElement.querySelector('[data-crop-image]');
    const tooSmall = modalElement.querySelector('[data-too-small]');

    // Bootstrap modals belong at the end of <body> (a card or the sidebar layout could clip them otherwise).
    document.body.appendChild(modalElement);
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

    let cropper = null;
    let pending = null;              // the file being cropped (not yet applied)
    let applied = null;              // the files of the last Apply, restored on Cancel
    let source = root.dataset.original || null; // the image the current crop refers to
    let objectUrl = null;

    const show = (element, visible) => { element.hidden = !visible; };

    const state = () => {
        const hasImage = !!previewImage.getAttribute('src') && removeInput.value !== '1';
        show(preview, hasImage);
        show(drop, !hasImage);
        show(removed, removeInput.value === '1');
    };

    const fail = (message) => {
        error.textContent = message;
        show(error, !!message);
    };

    const openCropper = (url, data = null) => {
        cropImage.src = url;
        modal.show();

        modalElement.addEventListener('shown.bs.modal', () => {
            cropper?.destroy();
            cropper = new Cropper(cropImage, {
                aspectRatio: width / height,
                viewMode: 1,
                autoCropArea: 1,
                dragMode: 'move',
                background: false,
                responsive: true,
                data: data ?? undefined,
                crop: (event) => {
                    const area = { w: Math.round(event.detail.width), h: Math.round(event.detail.height) };
                    const small = area.w < width || area.h < height;
                    show(tooSmall, small);
                    if (small) {
                        tooSmall.querySelector('span').textContent = format(strings.too_small, { area: `${area.w}×${area.h}`, size: `${width}×${height}` });
                    }
                },
            });
        }, { once: true });
    };

    const chooseFile = (file) => {
        fail('');
        if (!file) return;
        if (!ACCEPTED.includes(file.type) || file.size > maxKb * 1024) {
            restoreFileInput();
            return fail(ACCEPTED.includes(file.type) ? format(strings.too_large, { max: Math.round(maxKb / 1024) }) : strings.not_image);
        }

        pending = file;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        openCropper(objectUrl);
    };

    const apply = () => {
        if (!cropper) return;
        const data = cropper.getData(true); // whole pixels of the original
        cropInput.value = JSON.stringify({ x: data.x, y: data.y, width: data.width, height: data.height });

        if (pending) {
            // Put the new original into the file input, so the form posts it.
            const transfer = new DataTransfer();
            transfer.items.add(pending);
            fileInput.files = transfer.files;
            applied = transfer.files;
            source = objectUrl;
            pending = null;
        }

        previewImage.src = cropper.getCroppedCanvas({ width: Math.min(width, 800), imageSmoothingQuality: 'high' }).toDataURL('image/jpeg', 0.85);
        removeInput.value = '0';
        state();
        modal.hide();
    };

    const restoreFileInput = () => {
        // The browser put the cancelled file into the input: go back to the last applied one.
        if (applied) {
            fileInput.files = applied;
        } else {
            fileInput.value = '';
        }
    };

    const cancel = () => {
        if (pending) restoreFileInput();
        pending = null;
        modal.hide();
    };

    modalElement.addEventListener('hidden.bs.modal', () => {
        cropper?.destroy();
        cropper = null;
    });

    // Select / drop
    drop.addEventListener('click', () => fileInput.click());
    drop.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', () => {
        const [file] = fileInput.files;
        if (!file) {
            restoreFileInput();

            return;
        }
        chooseFile(file);
    });
    ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (event) => {
        event.preventDefault();
        drop.classList.add('dz-drag-hover');
    }));
    ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, (event) => {
        event.preventDefault();
        drop.classList.remove('dz-drag-hover');
    }));
    drop.addEventListener('drop', (event) => chooseFile(event.dataTransfer.files[0]));

    // Buttons
    const actions = {
        change: () => fileInput.click(),
        recrop: () => {
            if (!source) return;
            const crop = cropInput.value ? JSON.parse(cropInput.value) : null;
            openCropper(source, crop);
        },
        // Only a flag (the server checks it first), so Undo restores everything as it was.
        remove: () => {
            removeInput.value = '1';
            state();
        },
        undo: () => {
            removeInput.value = '0';
            state();
        },
        apply,
        cancel,
        'zoom-in': () => cropper?.zoom(0.1),
        'zoom-out': () => cropper?.zoom(-0.1),
        reset: () => cropper?.reset(),
    };

    [root, modalElement].forEach((container) => container.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button || !actions[button.dataset.action]) return;
        event.preventDefault();
        actions[button.dataset.action]();
    }));

    state();
}

export function initCroppedImages(root = document) {
    root.querySelectorAll('[data-cropped-image]').forEach(setup);
}
