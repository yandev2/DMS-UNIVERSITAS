<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'nidn_nip',
        'position_title',
        'phone',
        'email_verified_at',
        'password',
        'unit_id',
        'avatar_url',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user): bool {
            if ($user->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('avatar_url')) {
                $oldAvatar = $user->getOriginal('avatar_url');
                if ($oldAvatar && ! filter_var($oldAvatar, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($oldAvatar)) {
                    Storage::disk('public')->delete($oldAvatar);
                }
            }
        });

        static::deleted(function (User $user): void {
            if ($user->isForceDeleting() && $user->avatar_url && ! filter_var($user->avatar_url, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($user->avatar_url)) {
                    Storage::disk('public')->delete($user->avatar_url);
                }
            }
        });
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        if (filter_var($this->avatar_url, FILTER_VALIDATE_URL)) {
            return $this->avatar_url;
        }

        return Storage::disk('public')->url($this->avatar_url);
    }

    public function getAvatarUrl(): ?string
    {
        return $this->getFilamentAvatarUrl();
    }

    public function getDeletionRestrictionReason(): ?string
    {
        $reasons = [];

        if ($this->headedUnits()->exists()) {
            $reasons[] = 'menjabat sebagai pimpinan pada '.$this->headedUnits()->count().' unit organisasi';
        }

        if ($this->uploadedDocuments()->exists()) {
            $reasons[] = 'mengunggah '.$this->uploadedDocuments()->count().' dokumen arsip';
        }

        if ($this->finalApprovedDocuments()->exists()) {
            $reasons[] = 'mengesahkan '.$this->finalApprovedDocuments()->count().' dokumen arsip';
        }

        if ($this->documentApprovals()->exists()) {
            $reasons[] = 'memiliki '.$this->documentApprovals()->count().' riwayat persetujuan dokumen';
        }

        if ($this->posts()->exists()) {
            $reasons[] = 'menulis '.$this->posts()->count().' artikel berita';
        }

        if ($this->assignedAssets()->exists()) {
            $reasons[] = 'penanggung jawab '.$this->assignedAssets()->count().' aset inventaris';
        }

        return ! empty($reasons) ? implode(', ', $reasons) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('auth_security')
            ->logOnly(['name', 'email', 'nidn_nip', 'position_title', 'unit_id', 'avatar_url', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function headedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'head_user_id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function finalApprovedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'final_approved_by');
    }

    public function documentApprovals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'approved_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function assignedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'assigned_to_user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($panel->getId() === 'assets') {
            return $this->hasRole(['super_admin', 'bauk', 'upt_ict', 'rektor', 'warek', 'dekan', 'kaprodi', 'lpm', 'kepala_biro', 'staf_unit'])
                || $this->can('asset.access_panel');
        }

        return true;
    }
}
