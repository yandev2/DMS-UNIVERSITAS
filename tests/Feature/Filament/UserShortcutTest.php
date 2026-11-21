<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserShortcuts\Pages\ManageUserShortcuts;
use App\Filament\Widgets\DashboardWelcomeBannerWidget;
use App\Models\User;
use App\Models\UserShortcut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\LegacyMasterDataSeeder;
use Tests\TestCase;

class UserShortcutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegacyMasterDataSeeder::class);
    }

    public function test_any_authenticated_user_can_create_edit_and_delete_their_own_shortcuts(): void
    {
        $stafTi = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $this->actingAs($stafTi);

        $this->get('/admin/user-shortcuts')->assertOk();

        Livewire::test(ManageUserShortcuts::class)
            ->callAction('create', data: [
                'title' => 'SISTER Kemdiktisaintek',
                'url' => 'https://sister.kemdikbud.go.id/beranda',
            ])
            ->assertHasNoActionErrors();

        $shortcut = UserShortcut::where('user_id', $stafTi->id)->first();
        $this->assertNotNull($shortcut);
        $this->assertSame('SISTER Kemdiktisaintek', $shortcut->title);
        $this->assertSame('sister.kemdikbud.go.id', $shortcut->getHostDomain());
        $this->assertSame('S', $shortcut->getInitial());

        // Edit pintasan milik sendiri
        Livewire::test(ManageUserShortcuts::class)
            ->callTableAction('edit', $shortcut, data: [
                'title' => 'Portal SISTER Nasional',
                'url' => 'https://www.sister.kemdikbud.go.id',
            ])
            ->assertHasNoTableActionErrors();

        $shortcut->refresh();
        $this->assertSame('Portal SISTER Nasional', $shortcut->title);
        $this->assertSame('sister.kemdikbud.go.id', $shortcut->getHostDomain());

        // Hapus pintasan milik sendiri
        Livewire::test(ManageUserShortcuts::class)
            ->callTableAction('delete', $shortcut)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('user_shortcuts', ['id' => $shortcut->id]);
    }

    public function test_user_shortcuts_are_strictly_isolated_per_user_and_rendered_in_welcome_banner(): void
    {
        $userA = User::where('email', 'kaprodi.ti@univbi.ac.id')->firstOrFail();
        $userB = User::where('email', 'kaprodi.si@univbi.ac.id')->firstOrFail();

        $shortcutA = UserShortcut::create([
            'user_id' => $userA->id,
            'title' => 'SIAKAD Prodi TI',
            'url' => 'https://siakad.univbinainsan.ac.id',
        ]);

        $shortcutB = UserShortcut::create([
            'user_id' => $userB->id,
            'title' => 'Portal Rahasia Prodi SI',
            'url' => 'https://si.univbinainsan.ac.id',
        ]);

        // User A hanya melihat pintasan miliknya di halaman kelola maupun di Card Welcome Dashboard
        $this->actingAs($userA);

        Livewire::test(ManageUserShortcuts::class)
            ->assertCanSeeTableRecords([$shortcutA])
            ->assertCanNotSeeTableRecords([$shortcutB]);

        Livewire::test(DashboardWelcomeBannerWidget::class)
            ->assertSee('SIAKAD Prodi TI')
            ->assertSee('siakad.univbinainsan.ac.id')
            ->assertDontSee('Portal Rahasia Prodi SI');
    }

    public function test_shortcut_validates_http_https_scheme_and_enforces_max_limit_per_user(): void
    {
        $user = User::where('email', 'staf.ti@univbi.ac.id')->firstOrFail();
        $this->actingAs($user);

        // Tolak URL berbahaya non-http/https (mis. javascript:)
        Livewire::test(ManageUserShortcuts::class)
            ->callAction('create', data: [
                'title' => 'XSS Link',
                'url' => 'javascript:alert(1)',
            ])
            ->assertHasActionErrors(['url']);

        // Isi hingga batas maksimal (12 pintasan)
        for ($i = 1; $i <= UserShortcut::MAX_PER_USER; $i++) {
            UserShortcut::create([
                'user_id' => $user->id,
                'title' => "Pintasan Ke-{$i}",
                'url' => "https://example{$i}.univbinainsan.ac.id",
            ]);
        }

        $this->assertSame(UserShortcut::MAX_PER_USER, UserShortcut::where('user_id', $user->id)->count());

        // Percobaan menambah pintasan ke-13 wajib ditolak oleh validasi
        Livewire::test(ManageUserShortcuts::class)
            ->callAction('create', data: [
                'title' => 'Pintasan Ke-13 Melebihi Batas',
                'url' => 'https://example13.univbinainsan.ac.id',
            ])
            ->assertHasActionErrors(['title']);

        $this->assertSame(UserShortcut::MAX_PER_USER, UserShortcut::where('user_id', $user->id)->count());
    }
}
