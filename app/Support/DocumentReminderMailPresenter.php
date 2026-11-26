<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ReminderInterval;
use App\Models\Document;
use App\Models\LandingPageSetting;

class DocumentReminderMailPresenter
{
    /**
     * Menyusun payload tampilan email untuk pengingat mendekati masa kedaluwarsa (H-60, H-30, H-7).
     *
     * @return array<string, mixed>
     */
    public static function forExpiryReminder(
        object $notifiable,
        Document $document,
        ReminderInterval $interval,
        int $daysRemaining
    ): array {
        $tone = match ($interval) {
            ReminderInterval::H7 => 'danger',
            ReminderInterval::H30 => 'warning',
            ReminderInterval::H60 => 'info',
        };

        $headlineAccent = match ($interval) {
            ReminderInterval::H7 => 'segera ditinjau!',
            ReminderInterval::H30 => 'ditinjau segera.',
            ReminderInterval::H60 => 'ditinjau!',
        };

        $badgeLabel = match ($interval) {
            ReminderInterval::H7 => 'KRITIS · H-7',
            ReminderInterval::H30 => 'PERINGATAN · H-30',
            ReminderInterval::H60 => 'PENGINGAT · H-60',
        };

        $statusText = "Sisa {$daysRemaining} Hari ({$interval->getLabel()})";

        return self::buildBasePayload(
            notifiable: $notifiable,
            document: $document,
            tone: $tone,
            badgeLabel: $badgeLabel,
            headlineLead: 'Dokumen arsip perlu',
            headlineAccent: $headlineAccent,
            introText: 'Berikut rincian naskah dinas yang mendekati berakhirnya masa berlaku dan menunggu tindak lanjut Anda:',
            statusText: $statusText,
            ctaHeading: 'Siap meninjau dokumen ini?',
            ctaDescription: 'Buka portal admin untuk memeriksa berkas, memperpanjang masa berlaku, atau menyiapkan naskah pengganti.',
        );
    }

    /**
     * Menyusun payload tampilan email saat dokumen resmi berakhir masa berlakunya (Expired).
     *
     * @return array<string, mixed>
     */
    public static function forExpired(object $notifiable, Document $document): array
    {
        return self::buildBasePayload(
            notifiable: $notifiable,
            document: $document,
            tone: 'danger',
            badgeLabel: 'KEDALUWARSA',
            headlineLead: 'Masa berlaku naskah',
            headlineAccent: 'telah berakhir!',
            introText: 'Sistem telah mengalihkan status naskah dinas berikut menjadi Kedaluwarsa (Expired) karena melewati batas tanggal berlaku:',
            statusText: 'Kedaluwarsa (Expired)',
            ctaHeading: 'Perlu pembaruan atau pengarsipan?',
            ctaDescription: 'Akses portal admin untuk menerbitkan dokumen pengganti, memperbarui masa berlaku, atau mengelola arsip pasif.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildBasePayload(
        object $notifiable,
        Document $document,
        string $tone,
        string $badgeLabel,
        string $headlineLead,
        string $headlineAccent,
        string $introText,
        string $statusText,
        string $ctaHeading,
        string $ctaDescription,
    ): array {
        $settings = self::resolveSettingsSafely();

        $universityName = filled($settings?->university_name)
            ? trim((string) $settings->university_name)
            : (string) config('app.name', 'Universitas Bina Insan');

        $universityAcronym = filled($settings?->university_acronym)
            ? trim((string) $settings->university_acronym)
            : 'UNIVBI';

        $systemName = filled($settings?->system_name)
            ? trim((string) $settings->system_name)
            : 'Sistem e-Arsip & Borang';

        $rawLogoUrl = $settings?->getLogoUrl();
        $logoUrl = null;
        if (filled($rawLogoUrl)) {
            $logoUrl = (str_starts_with($rawLogoUrl, 'http://') || str_starts_with($rawLogoUrl, 'https://'))
                ? $rawLogoUrl
                : url($rawLogoUrl);
        }

        $supportEmail = collect([
            $settings?->campus_email,
            $settings?->developer_email,
            config('mail.from.address'),
        ])->first(fn (mixed $email): bool => is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false);

        $supportEmail = is_string($supportEmail) ? trim($supportEmail) : null;

        $copyrightText = filled($settings?->copyright_text)
            ? trim((string) $settings->copyright_text)
            : '© '.date('Y')." {$universityName}. Hak Cipta Dilindungi Undang-Undang.";

        $palette = match ($tone) {
            'danger' => [
                'accentColor' => '#dc2626',
                'badgeBg' => '#fef2f2',
                'badgeBorder' => '#fecaca',
                'badgeText' => '#b91c1c',
            ],
            'warning' => [
                'accentColor' => '#d97706',
                'badgeBg' => '#fffbeb',
                'badgeBorder' => '#fde68a',
                'badgeText' => '#b45309',
            ],
            default => [
                'accentColor' => '#2563eb',
                'badgeBg' => '#ffffff',
                'badgeBorder' => '#bfdbfe',
                'badgeText' => '#1d4ed8',
            ],
        };

        return [
            'universityName' => $universityName,
            'universityAcronym' => $universityAcronym,
            'logoBadgeText' => mb_strtoupper(mb_substr($universityAcronym, 0, 8, 'UTF-8'), 'UTF-8'),
            'systemName' => $systemName,
            'logoUrl' => $logoUrl,
            'supportEmail' => $supportEmail,
            'copyrightText' => $copyrightText,
            'recipientName' => filled($notifiable->name ?? null) ? trim((string) $notifiable->name) : 'Bapak/Ibu',
            'documentNumber' => filled($document->document_number) ? (string) $document->document_number : 'Tanpa Nomor',
            'documentTitle' => (string) $document->title,
            'unitName' => $document->unit?->name ?: 'Unit Kampus',
            'expiredDateStr' => $document->expired_date ? $document->expired_date->format('d/m/Y') : '-',
            'badgeLabel' => $badgeLabel,
            'headlineLead' => $headlineLead,
            'headlineAccent' => $headlineAccent,
            'introText' => $introText,
            'statusText' => $statusText,
            'ctaHeading' => $ctaHeading,
            'ctaDescription' => $ctaDescription,
            'documentUrl' => url('/admin/documents/'.$document->id),
            'adminPortalUrl' => url('/admin'),
            'publicPortalUrl' => url('/'),
            'accentColor' => $palette['accentColor'],
            'badgeBg' => $palette['badgeBg'],
            'badgeBorder' => $palette['badgeBorder'],
            'badgeText' => $palette['badgeText'],
        ];
    }

    private static function resolveSettingsSafely(): ?LandingPageSetting
    {
        try {
            return LandingPageSetting::current();
        } catch (\Throwable) {
            return null;
        }
    }
}
