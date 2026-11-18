<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile_page_and_is_redirected(): void
    {
        $response = $this->get('/admin/profile');

        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_render_edit_profile_page(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin/profile');

        $response->assertSuccessful();

        Livewire::test(EditProfile::class)
            ->assertSuccessful()
            ->assertFormSet([
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@univbi.ac.id',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Nama Baru Yang Valid',
                'email' => 'baru@univbi.ac.id',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Nama Baru Yang Valid', $user->name);
        $this->assertSame('baru@univbi.ac.id', $user->email);
    }

    public function test_email_must_be_unique_except_for_current_user(): void
    {
        $userA = User::factory()->create([
            'email' => 'user.a@univbi.ac.id',
            'is_active' => true,
        ]);

        $userB = User::factory()->create([
            'email' => 'user.b@univbi.ac.id',
            'is_active' => true,
        ]);

        $this->actingAs($userA);

        // Keeping own email should pass
        Livewire::test(EditProfile::class)
            ->fillForm([
                'email' => 'user.a@univbi.ac.id',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Taking another user's email should fail
        Livewire::test(EditProfile::class)
            ->fillForm([
                'email' => 'user.b@univbi.ac.id',
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_user_can_upload_avatar_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'is_active' => true,
            'avatar_url' => null,
        ]);

        $this->actingAs($user);

        $fakeImage = UploadedFile::fake()->image('profile.jpg', 300, 300);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'avatar_url' => $fakeImage,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_url);
        Storage::disk('public')->assertExists($user->avatar_url);
    }

    public function test_old_avatar_physical_file_is_deleted_when_new_avatar_is_uploaded(): void
    {
        Storage::fake('public');

        $oldPath = 'avatars/old-avatar.png';
        Storage::disk('public')->put($oldPath, 'dummy content');

        $user = User::factory()->create([
            'is_active' => true,
            'avatar_url' => $oldPath,
        ]);

        $this->actingAs($user);

        Storage::disk('public')->assertExists($oldPath);

        $newImage = UploadedFile::fake()->image('new-avatar.png', 400, 400);

        $test = Livewire::test(EditProfile::class);

        $test->set('data.avatar_url', []);
        $test->fillForm([
            'avatar_url' => $newImage,
        ]);

        $test->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        // Old file must be deleted from physical storage
        Storage::disk('public')->assertMissing($oldPath);

        // New file must exist
        $this->assertNotSame($oldPath, $user->avatar_url);
        Storage::disk('public')->assertExists($user->avatar_url);
    }

    public function test_old_avatar_physical_file_is_deleted_when_avatar_is_removed(): void
    {
        Storage::fake('public');

        $avatarPath = 'avatars/avatar-to-remove.png';
        Storage::disk('public')->put($avatarPath, 'dummy content');

        $user = User::factory()->create([
            'is_active' => true,
            'avatar_url' => $avatarPath,
        ]);

        $this->actingAs($user);

        Storage::disk('public')->assertExists($avatarPath);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'avatar_url' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertNull($user->avatar_url);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_user_can_change_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'old-password-123',
                'password' => 'new-secret-password-456',
                'passwordConfirmation' => 'new-secret-password-456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('new-secret-password-456', $user->password));
    }

    public function test_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'wrong-current-password',
                'password' => 'new-secret-password-456',
                'passwordConfirmation' => 'new-secret-password-456',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $user->refresh();

        $this->assertTrue(Hash::check('old-password-123', $user->password));
    }

    public function test_user_cannot_change_password_without_providing_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'currentPassword' => null,
                'password' => 'new-secret-password-456',
                'passwordConfirmation' => 'new-secret-password-456',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $user->refresh();

        $this->assertTrue(Hash::check('old-password-123', $user->password));
    }

    public function test_user_cannot_change_password_if_confirmation_does_not_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'old-password-123',
                'password' => 'new-secret-password-456',
                'passwordConfirmation' => 'mismatched-password-789',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $user->refresh();

        $this->assertTrue(Hash::check('old-password-123', $user->password));
    }

    public function test_user_model_returns_correct_filament_avatar_url(): void
    {
        $user = new User;
        $this->assertNull($user->getFilamentAvatarUrl());

        $user->avatar_url = 'https://example.com/external-avatar.jpg';
        $this->assertSame('https://example.com/external-avatar.jpg', $user->getFilamentAvatarUrl());

        $user->avatar_url = 'avatars/local-avatar.png';
        $this->assertStringContainsString('storage/avatars/local-avatar.png', $user->getFilamentAvatarUrl());
    }

    public function test_topbar_user_menu_links_to_profile_page(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $profileUrl = Filament::getProfileUrl();

        $this->assertNotNull($profileUrl);
        $this->assertStringContainsString('/admin/profile', $profileUrl);
    }
}
