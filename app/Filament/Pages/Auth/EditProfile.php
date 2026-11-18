<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Components\ButtonHint;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Profil Saya';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)
            ->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->columns(2)
            ->components([
                Section::make('Informasi Profil Pengguna')
                    ->description('Perbarui foto profil, nama lengkap, dan alamat email resmi akun Anda.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->schema([
                        $this->getAvatarFormComponent(),
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ])
                    ->columns(2),

                Section::make('Keamanan & Kata Sandi')
                    ->description('Biarkan kosong apabila Anda tidak ingin mengubah kata sandi akun saat ini.')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->schema([
                        $this->getCurrentPasswordFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->label('Foto Profil')
            ->avatar()
            ->alignCenter()
            ->image()
            ->imageEditor()
            ->circleCropper()
            ->disk('public')
            ->directory('avatars')
            ->maxSize(2048)
            ->columnSpanFull()
            ->hintAction(ButtonHint::make(
                'Unggah foto profil dalam format JPG, PNG, atau WebP dengan resolusi persegi dan ukuran maksimal 2MB.',
                'Panduan Foto Profil'
            ));
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label('Nama Lengkap & Gelar')
            ->required()
            ->maxLength(255)
            ->autofocus()
            ->columnSpanFull()
            ->hintAction(ButtonHint::make(
                'Nama lengkap beserta gelar akademik resmi yang digunakan pada lembar pengesahan arsip.',
                'Panduan Nama Pengguna'
            ));
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Alamat Email Resmi')
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->columnSpanFull()
            ->hintAction(ButtonHint::make(
                'Alamat email aktif untuk akses masuk sistem dan penerimaan notifikasi dokumen.',
                'Panduan Email Akun'
            ));
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label('Kata Sandi Saat Ini (Lama)')
            ->password()
            ->autocomplete('current-password')
            ->currentPassword(guard: filament()->getAuthGuard())
            ->revealable(filament()->arePasswordsRevealable())
            ->required(fn (Get $get): bool => filled($get('password')))
            ->columnSpanFull()
            ->dehydrated(false)
            ->hintAction(ButtonHint::make(
                'Wajib dimasukkan jika Anda berniat mengubah kata sandi baru demi verifikasi keamanan akun.',
                'Panduan Kata Sandi Lama'
            ));
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi Baru')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state))
            ->dehydrateStateUsing(fn (#[SensitiveParameter] $state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation')
            ->columnSpan(1)
            ->hintAction(ButtonHint::make(
                'Biarkan kosong jika tidak ingin mengubah kata sandi. Minimal 8 karakter.',
                'Panduan Kata Sandi Baru'
            ));
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label('Konfirmasi Kata Sandi')
            ->password()
            ->autocomplete('new-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->required(fn (Get $get): bool => filled($get('password')))
            ->columnSpan(1)
            ->dehydrated(false)
            ->hintAction(ButtonHint::make(
                'Ketik ulang kata sandi baru untuk memastikan kesesuaian karakter.',
                'Panduan Konfirmasi Kata Sandi'
            ));
    }

    #[Exposed]
    public function save(): void
    {
        parent::save();

        $this->data['currentPassword'] = null;
    }
}
