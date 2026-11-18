<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class ActivityLog extends SpatieActivity
{
    use HasFactory;

    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'attribute_changes',
        'properties',
        'ip_address',
        'user_agent',
        'batch_uuid',
    ];

    protected function casts(): array
    {
        return [
            'attribute_changes' => 'array',
            'properties' => 'collection',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getEventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'deleted' => 'Dihapus',
            'restored' => 'Dipulihkan',
            'login' => 'Masuk (Login)',
            'logout' => 'Keluar (Logout)',
            'failed_login' => 'Gagal Masuk',
            'approved' => 'Disahkan',
            'rejected' => 'Ditolak',
            'revision_requested' => 'Minta Revisi',
            'stamped' => 'Dibubuhi Stempel',
            'download', 'downloaded' => 'Unduh Berkas',
            'verified' => 'Verifikasi QR',
            'archived' => 'Diarsipkan',
            'expired' => 'Kedaluwarsa',
            'submitted' => 'Diajukan',
            'published' => 'Dipublikasikan',
            'completed' => 'Selesai',
            'backup_created' => 'Backup Dibuat',
            'backup_restored' => 'Sistem Dipulihkan',
            'backup_downloaded' => 'Backup Diunduh',
            'backup_deleted' => 'Backup Dihapus',
            'backup_cleaned' => 'Retensi Dibersihkan',
            'restore_security_blocked' => 'Restore Diblokir',
            default => filled($this->event) ? ucfirst((string) $this->event) : 'Aktivitas',
        };
    }

    public function getEventColor(): string
    {
        return match ($this->event) {
            'created', 'approved', 'restored', 'stamped' => 'success',
            'updated', 'verified' => 'info',
            'login' => 'success',
            'logout' => 'gray',
            'deleted', 'failed_login', 'rejected' => 'danger',
            'revision_requested', 'expired' => 'warning',
            'download' => 'purple',
            default => 'primary',
        };
    }

    public function getEventIcon(): string
    {
        return match ($this->event) {
            'created' => 'heroicon-o-plus-circle',
            'updated' => 'heroicon-o-pencil-square',
            'deleted' => 'heroicon-o-trash',
            'restored' => 'heroicon-o-arrow-path',
            'login' => 'heroicon-o-arrow-right-on-rectangle',
            'logout' => 'heroicon-o-arrow-left-on-rectangle',
            'failed_login' => 'heroicon-o-shield-exclamation',
            'approved' => 'heroicon-o-check-badge',
            'rejected' => 'heroicon-o-x-circle',
            'revision_requested' => 'heroicon-o-arrow-uturn-left',
            'stamped' => 'heroicon-o-check-badge',
            'verified' => 'heroicon-o-qr-code',
            'archived' => 'heroicon-o-archive-box',
            'expired' => 'heroicon-o-clock',
            'download' => 'heroicon-o-arrow-down-tray',
            default => 'heroicon-o-shield-check',
        };
    }

    public function getHumanLogCategory(): string
    {
        return match ($this->log_name) {
            'auth_audit' => 'Autentikasi & Sesi',
            'documents', 'document_audit' => 'E-Arsip & Naskah Dinas',
            'incoming_letters' => 'Surat Masuk',
            'outgoing_letters' => 'Surat Keluar',
            'users', 'user_audit' => 'Manajemen Pengguna',
            'system', 'system_backup' => 'Sistem & Backup',
            'borang', 'accreditation', 'borang_export' => 'Borang Akreditasi',
            'cms_moderation', 'cms_export' => 'Publikasi & Berita',
            'bulk_zip_export' => 'Ekspor Arsip Massal',
            'default' => 'Umum / Institusi',
            null => 'Umum / Institusi',
            default => ucfirst((string) $this->log_name),
        };
    }

    /**
     * @return array{name: string, device: string, icon: string}
     */
    public function getParsedUserAgent(): array
    {
        $ua = (string) $this->user_agent;

        if (empty($ua) || $ua === '-') {
            return [
                'name' => 'Internal / Otomatisasi',
                'device' => 'Sistem Server',
                'icon' => 'heroicon-o-cpu-chip',
            ];
        }

        if (str_contains($ua, 'Symfony')) {
            return [
                'name' => 'Symfony CLI / Runner',
                'device' => 'Automated Process / CLI Worker',
                'icon' => 'heroicon-o-command-line',
            ];
        }

        if (str_contains($ua, 'Postman')) {
            return [
                'name' => 'Postman Client',
                'device' => 'API Development Tool',
                'icon' => 'heroicon-o-code-bracket',
            ];
        }

        $browser = 'Web Browser';
        $icon = 'heroicon-o-globe-alt';

        if (str_contains($ua, 'Edg/')) {
            $browser = 'Microsoft Edge';
        } elseif (str_contains($ua, 'Chrome/')) {
            $browser = 'Google Chrome';
        } elseif (str_contains($ua, 'Firefox/')) {
            $browser = 'Mozilla Firefox';
        } elseif (str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/')) {
            $browser = 'Apple Safari';
        }

        $platform = 'Desktop Client';
        if (str_contains($ua, 'Windows')) {
            $platform = 'Windows OS';
        } elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) {
            $platform = 'macOS';
        } elseif (str_contains($ua, 'Linux')) {
            $platform = 'Linux System';
        } elseif (str_contains($ua, 'Android')) {
            $platform = 'Android Mobile';
            $icon = 'heroicon-o-device-phone-mobile';
        } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            $platform = 'iOS Device';
            $icon = 'heroicon-o-device-phone-mobile';
        }

        return [
            'name' => $browser,
            'device' => $platform,
            'icon' => $icon,
        ];
    }

    public static function getFieldLabel(string $key): string
    {
        return match ($key) {
            'title' => 'Judul Naskah Dinas',
            'document_number' => 'Nomor Surat Resmi',
            'status' => 'Status Naskah / Persetujuan',
            'issued_date' => 'Tanggal Ditetapkan / Terbit',
            'expired_date' => 'Tanggal Kedaluwarsa',
            'category_id' => 'Kategori Dokumen',
            'unit_id' => 'Unit Kerja Pemilik',
            'academic_year_id' => 'Tahun Akademik',
            'visibility' => 'Taraf Visibilitas Dokumen',
            'is_active' => 'Status Keaktifan',
            'is_borang' => 'Status Bukti Borang',
            'has_expiry' => 'Memiliki Masa Berlaku',
            'is_manual_number' => 'Penomoran Manual',
            'description' => 'Deskripsi / Ringkasan',
            'reminder_recipients' => 'Penerima Pengingat',
            'uploaded_by' => 'Pengunggah Berkas',
            'email' => 'Alamat Email',
            'name' => 'Nama Lengkap',
            'role' => 'Peran / Hak Akses',
            'password' => 'Kata Sandi (Hashed)',
            'last_login_at' => 'Waktu Terakhir Masuk',
            'last_login_ip' => 'IP Terakhir Masuk',
            'approved_at' => 'Waktu Pengesahan',
            'approved_by' => 'Pejabat Pengesah',
            default => str_replace('_', ' ', ucfirst($key)),
        };
    }

    /**
     * @return array{display: string, raw: string, is_date: bool, is_null: bool, is_bool: bool}
     */
    public static function formatDiffValue(string $key, mixed $value): array
    {
        if (is_null($value)) {
            return [
                'display' => '[Kosong / Null]',
                'raw' => 'null',
                'is_date' => false,
                'is_null' => true,
                'is_bool' => false,
            ];
        }

        if (is_bool($value)) {
            return [
                'display' => $value ? 'Ya (Aktif / True)' : 'Tidak (Nonaktif / False)',
                'raw' => $value ? 'true' : 'false',
                'is_date' => false,
                'is_null' => false,
                'is_bool' => true,
            ];
        }

        if (is_array($value)) {
            return [
                'display' => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '',
                'raw' => json_encode($value) ?: '',
                'is_date' => false,
                'is_null' => false,
                'is_bool' => false,
            ];
        }

        $stringValue = (string) $value;

        // Cek format tanggal/timestamp ISO 8601 (contoh: 2025-12-11T00:00:00.000000Z atau 2025-12-11)
        if (is_string($value) && (str_contains($key, 'date') || str_contains($key, '_at')) && preg_match('/^\d{4}-\d{2}-\d{2}/', $stringValue)) {
            try {
                $carbon = Carbon::parse($stringValue);
                $hasTime = $carbon->format('H:i:s') !== '00:00:00';
                $formattedDate = $carbon->translatedFormat($hasTime ? 'd F Y, H:i:s' : 'd F Y');

                return [
                    'display' => $formattedDate.($hasTime ? ' WIB' : ''),
                    'raw' => $stringValue,
                    'is_date' => true,
                    'is_null' => false,
                    'is_bool' => false,
                ];
            } catch (\Throwable) {
                // fallback ke string biasa
            }
        }

        return [
            'display' => $stringValue,
            'raw' => $stringValue,
            'is_date' => false,
            'is_null' => false,
            'is_bool' => false,
        ];
    }

    public function getFormattedSubjectTitle(): string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return '-';
        }

        $subject = $this->subject;

        if (! $subject) {
            $baseName = class_basename($this->subject_type);

            return "{$baseName} #{$this->subject_id}";
        }

        if ($subject instanceof Document) {
            $num = $subject->document_number ? " [{$subject->document_number}]" : '';

            return "Naskah: {$subject->title}{$num}";
        }

        if ($subject instanceof User) {
            return "Pengguna: {$subject->name} ({$subject->email})";
        }

        if ($subject instanceof Unit) {
            return "Unit: {$subject->name} ({$subject->code})";
        }

        if (isset($subject->title)) {
            return (string) $subject->title;
        }

        if (isset($subject->name)) {
            return (string) $subject->name;
        }

        $baseName = class_basename($this->subject_type);

        return "{$baseName} #{$this->subject_id}";
    }

    protected static function booted(): void
    {
        static::creating(function (ActivityLog $activity): void {
            if (request()) {
                $activity->ip_address ??= request()->ip();
                $activity->user_agent ??= request()->userAgent();
            }
        });
    }
}
