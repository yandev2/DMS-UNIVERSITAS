<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\Document;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicApprovedDocumentRule implements ValidationRule
{
    /**
     * Validasi bahwa naskah dinas yang ditautkan bertaraf akses Publik dan berstatus Sah (Approved/Archived).
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $document = Document::find($value);

        if (! $document) {
            $fail('Naskah dinas yang dipilih tidak ditemukan dalam pangkalan data e-Arsip.');

            return;
        }

        if ($document->visibility !== VisibilityLevel::Public) {
            $fail('Keamanan Akses: Hanya naskah dinas yang bertaraf akses Publik yang diperbolehkan ditautkan ke portal berita publik.');

            return;
        }

        if (! in_array($document->status, [DocumentStatus::Approved, DocumentStatus::Archived], true)) {
            $fail('Keabsahan Naskah: Dokumen yang ditautkan wajib berstatus Disahkan (Approved) atau Diarsip secara resmi.');

            return;
        }
    }
}
