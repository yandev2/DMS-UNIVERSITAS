<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Tests\TestCase;
use Zvizvi\FilamentNotificationsTabs\Livewire\DatabaseNotifications;

class FilamentNotificationsTabsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_panel_uses_notifications_tabs_component_and_indonesian_translations(): void
    {
        $panel = Filament::getPanel('admin');
        $panel->boot();

        $this->assertSame(
            DatabaseNotifications::class,
            $panel->getDatabaseNotificationsLivewireComponent(),
        );

        $this->assertSame('Belum dibaca', __('filament-notifications-tabs::notifications.tabs.unread'));
        $this->assertSame('Semua', __('filament-notifications-tabs::notifications.tabs.all'));
        $this->assertSame('Hapus notifikasi', __('filament-notifications-tabs::notifications.actions.delete.label'));
        $this->assertSame('Tandai sudah dibaca', __('filament-notifications-tabs::notifications.actions.mark_as_read.label'));
        $this->assertSame('Tandai belum dibaca', __('filament-notifications-tabs::notifications.actions.mark_as_unread.label'));
    }

    public function test_database_notifications_filters_unread_and_all_tabs(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create(['is_active' => true]);

        Notification::make()
            ->title('Dokumen Menunggu Persetujuan')
            ->sendToDatabase($user);

        Notification::make()
            ->title('Dokumen Telah Diarsipkan')
            ->sendToDatabase($user);

        $readNotification = DatabaseNotification::query()
            ->where('notifiable_id', $user->getKey())
            ->where('data->title', 'Dokumen Telah Diarsipkan')
            ->firstOrFail();
        $readNotification->markAsRead();

        $this->actingAs($user);

        Livewire::test(DatabaseNotifications::class)
            ->assertSet('tab', 'unread')
            ->assertSeeText('Dokumen Menunggu Persetujuan')
            ->assertDontSeeText('Dokumen Telah Diarsipkan')
            ->call('setTab', 'all')
            ->assertSet('tab', 'all')
            ->assertSeeText('Dokumen Menunggu Persetujuan')
            ->assertSeeText('Dokumen Telah Diarsipkan');
    }

    public function test_database_notifications_can_toggle_read_status_and_delete_scoped_to_user(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['is_active' => true]);

        Notification::make()
            ->title('Pengingat Masa Berlaku')
            ->sendToDatabase($user);

        Notification::make()
            ->title('Notifikasi Pengguna Lain')
            ->sendToDatabase($otherUser);

        $userNotification = DatabaseNotification::query()
            ->where('notifiable_id', $user->getKey())
            ->firstOrFail();

        $otherNotification = DatabaseNotification::query()
            ->where('notifiable_id', $otherUser->getKey())
            ->firstOrFail();

        $this->actingAs($user);

        $component = Livewire::test(DatabaseNotifications::class)
            ->call('toggleNotificationReadStatus', $userNotification->id);

        $this->assertNotNull($userNotification->fresh()->read_at);

        $component->call('toggleNotificationReadStatus', $userNotification->id);

        $this->assertNull($userNotification->fresh()->read_at);

        // Pastikan aksi ke notifikasi milik pengguna lain diabaikan
        $component->call('toggleNotificationReadStatus', $otherNotification->id);
        $this->assertNull($otherNotification->fresh()->read_at);

        $component->call('deleteNotification', $otherNotification->id);
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);

        // Hapus notifikasi milik pengguna sendiri
        $component->call('deleteNotification', $userNotification->id);
        $this->assertDatabaseMissing('notifications', ['id' => $userNotification->id]);
    }
}
