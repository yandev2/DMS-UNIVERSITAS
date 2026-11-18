import { renderAsync } from 'docx-preview';

/**
 * Mengambil berkas .docx secara terotentikasi dari server dan me-rendernya ke DOM
 * menggunakan docx-preview ketika pratinjau PDF LibreOffice tidak tersedia.
 */
window.dmsRenderWordPreview = async function dmsRenderWordPreview(stageEl, streamUrl, onStateChange) {
    const notify = typeof onStateChange === 'function' ? onStateChange : () => {};

    if (!stageEl || !streamUrl) {
        notify('error', 'Tautan pratinjau dokumen Word tidak tersedia.');
        return;
    }

    notify('loading', '');

    try {
        const response = await fetch(streamUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/octet-stream, */*',
            },
        });

        if (!response.ok) {
            throw new Error(`Server menolak permintaan pratinjau (HTTP ${response.status}).`);
        }

        const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
        if (contentType.includes('text/html')) {
            throw new Error('Respons server bukan berkas Word yang valid.');
        }

        const buffer = await response.arrayBuffer();
        if (!buffer || buffer.byteLength === 0) {
            throw new Error('Berkas dokumen Word kosong.');
        }

        stageEl.innerHTML = '';

        await renderAsync(buffer, stageEl, null, {
            className: 'dms-docx-preview',
            inWrapper: true,
            hideWrapperOnPrint: true,
            ignoreWidth: false,
            ignoreHeight: false,
            ignoreFonts: false,
            breakPages: true,
            ignoreLastRenderedPageBreak: true,
            renderHeaders: true,
            renderFooters: true,
            renderFootnotes: true,
            renderEndnotes: true,
            useBase64URL: true,
        });

        notify('ready', '');
    } catch (error) {
        console.warn('DMS Word preview render failed:', error);
        const message =
            error instanceof Error && error.message
                ? error.message
                : 'Gagal memuat pratinjau dokumen Word di browser.';
        notify('error', message);
    }
};

/**
 * Menginisialisasi satu kontainer pratinjau Word secara idempoten (aman untuk Livewire & halaman publik).
 */
window.dmsMountWordPreview = function dmsMountWordPreview(rootEl) {
    if (!rootEl || rootEl.dataset.wordMounted === '1') {
        return;
    }
    rootEl.dataset.wordMounted = '1';

    const streamUrl = rootEl.dataset.streamUrl || '';
    const loadingEl = rootEl.querySelector('[data-word-loading]');
    const errorEl = rootEl.querySelector('[data-word-error]');
    const errorMsgEl = rootEl.querySelector('[data-word-error-message]');
    const stageEl = rootEl.querySelector('[data-word-stage]');

    window.dmsRenderWordPreview(stageEl, streamUrl, (state, message) => {
        if (loadingEl) {
            loadingEl.hidden = state !== 'loading';
        }
        if (errorEl) {
            errorEl.hidden = state !== 'error';
        }
        if (stageEl) {
            stageEl.hidden = state !== 'ready';
        }
        if (state === 'error' && errorMsgEl && message) {
            errorMsgEl.textContent = message;
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-word-preview-root]').forEach((el) => {
        window.dmsMountWordPreview(el);
    });
});
