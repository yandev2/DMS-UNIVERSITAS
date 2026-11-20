<script>
(() => {
    if (window.__uploadErrorHandlerInstalled) {
        return;
    }
    window.__uploadErrorHandlerInstalled = true;

    const notifyUploadError = (title, message) => {
        if (!message) {
            return;
        }

        if (typeof window.FilamentNotification === 'function') {
            new window.FilamentNotification()
                .title(title)
                .body(message)
                .danger()
                .persistent()
                .send();
            return;
        }

        console.error(title, message);
    };

    const translateServerUploadError = (status, responseText) => {
        if (status === 413) {
            return 'Ukuran berkas melebihi batas unggahan web server (HTTP 413 Payload Too Large). Periksa client_max_body_size atau upload_max_filesize di server.';
        }

        if (status === 419) {
            return 'Sesi halaman kedaluwarsa (HTTP 419 CSRF). Muat ulang halaman lalu unggah berkas kembali.';
        }

        if (status === 422) {
            try {
                const payload = JSON.parse(responseText || '{}');
                const firstError =
                    payload?.errors?.['files.0']?.[0]
                    || payload?.errors?.files?.[0]
                    || Object.values(payload?.errors || {})?.[0]?.[0]
                    || payload?.message;

                if (typeof firstError === 'string' && firstError.trim() !== '') {
                    if (/failed to upload/i.test(firstError)) {
                        return 'Berkas gagal diterima server PHP (kemungkinan ukuran berkas melebihi batas upload_max_filesize / post_max_size di server atau koneksi terputus).';
                    }

                    return firstError;
                }
            } catch (_) {
                // Abaikan error parsing JSON, gunakan pesan fallback di bawah
            }

            return 'Validasi file sementara ditolak server (HTTP 422). Pastikan ukuran dan format berkas sesuai ketentuan kolom unggahan serta batas server.';
        }

        if (status === 0) {
            return 'Koneksi jaringan terputus atau waktu unggah habis sebelum berkas selesai dikirim ke server.';
        }

        if (status >= 500) {
            return `Terjadi gangguan internal server saat menerima unggahan berkas (HTTP ${status}). Coba ulangi beberapa saat lagi atau cek log Laravel.`;
        }

        return `Gagal mengunggah berkas ke server (HTTP ${status}).`;
    };

    const originalXhrOpen = XMLHttpRequest.prototype.open;
    const originalXhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        this.__isLivewireUpload = typeof url === 'string' && url.includes('livewire/upload-file');

        return originalXhrOpen.call(this, method, url, ...rest);
    };

    XMLHttpRequest.prototype.send = function (...args) {
        if (this.__isLivewireUpload && !this.__uploadErrorHooked) {
            this.__uploadErrorHooked = true;

            this.addEventListener('load', () => {
                if (this.status >= 400) {
                    const reason = translateServerUploadError(this.status, this.responseText);
                    notifyUploadError('Upload Berkas Gagal', reason);
                }
            });

            this.addEventListener('error', () => {
                notifyUploadError('Upload Berkas Gagal', translateServerUploadError(0, ''));
            });

            this.addEventListener('timeout', () => {
                notifyUploadError('Waktu Upload Habis', 'Pengiriman berkas terlalu lama sehingga koneksi diputus oleh browser. Periksa kestabilan jaringan Anda.');
            });
        }

        return originalXhrSend.apply(this, args);
    };

    document.addEventListener('FilePond:error', (event) => {
        const err = event?.detail?.error;
        if (!err || typeof err !== 'object') {
            return;
        }

        const file = event?.detail?.file;
        const fileName = file?.filename || file?.file?.name || 'Foto';
        const mainText = String(err.main || '');
        const subText = String(err.sub || '');
        const combined = `${mainText} ${subText}`.trim();

        if (/file is too large/i.test(combined) || /maximum file size/i.test(combined)) {
            notifyUploadError(
                'Ukuran Berkas Terlalu Besar',
                `"${fileName}" melebihi batas ukuran maksimal yang diizinkan pada kolom ini (${subText || 'periksa batas unggahan'}).`
            );
        } else if (/invalid type/i.test(combined)) {
            const isWordOnly = /wordprocessingml|msword|\.docx?$/i.test(subText);
            const isImageOnly = /image\//i.test(subText);

            let reason = `"${fileName}" ditolak karena format berkas tidak sesuai (${subText || 'periksa tipe berkas yang diizinkan'}).`;

            if (isWordOnly) {
                reason = `"${fileName}" ditolak. Hanya berkas Microsoft Word (.docx atau .doc) yang diperbolehkan pada kolom ini.`;
            } else if (isImageOnly) {
                reason = `"${fileName}" bukan format gambar yang diizinkan (gunakan JPG, PNG, WebP, GIF, atau AVIF; foto iPhone HEIC harap dikonversi ke JPG terlebih dahulu).`;
            }

            notifyUploadError('Format Berkas Tidak Didukung', reason);
        }
    });
})();
</script>

