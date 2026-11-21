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

    public function shortcuts(): HasMany
    {
        return $this->hasMany(UserShortcut::class)->orderBy('sort_order')->orderBy('id');
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

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * Mengembalikan daftar ID unit yang dapat diakses pengguna,
     * atau null apabila pengguna memiliki akses global lintas unit.
     *
     * @return list<int>|null
     */
    public function accessibleUnitIds(): ?array
    {
        if ($this->hasRole('super_admin') || $this->can('document.bypass_unit_scope')) {
            return null;
        }

        if (! $this->unit_id) {
            return [];
        }

        $unitIds = [(int) $this->unit_id];

        if ($this->hasRole('dekan')) {
            $childIds = Unit::query()
                ->where('parent_id', $this->unit_id)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $unitIds = array_values(array_unique(array_merge($unitIds, $childIds)));
        }

        return $unitIds;
    }

    public function canAccessUnit(?int $unitId): bool
    {
        $accessibleUnitIds = $this->accessibleUnitIds();

        if ($accessibleUnitIds === null) {
            return true;
        }

        return $unitId !== null && in_array($unitId, $accessibleUnitIds, true);
    }
}
