<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ReminderInterval;
use App\Models\Document;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiryReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public ReminderInterval $interval,
        public int $daysRemaining
    ) {}

    /**
     * Mengutamakan penyimpanan notifikasi panel sebelum pengiriman email eksternal.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $docNumber = $this->document->document_number ?: 'Tanpa Nomor';
        $docTitle = $this->document->title;
        $unitName = $this->document->unit?->name ?: 'Unit Kampus';
        $expiredDateStr = $this->document->expired_date ? $this->document->expired_date->format('d/m/Y') : '-';
        $viewUrl = url('/admin/documents/'.$this->document->id);

        $urgencyPrefix = match ($this->interval) {
            ReminderInterval::H7 => '[KRITIS - Sisa 7 Hari]',
            ReminderInterval::H30 => '[PERINGATAN - Sisa 30 Hari]',
            ReminderInterval::H60 => '[PENGINGAT - Sisa 60 Hari]',
        };

        $subject = "{$urgencyPrefix} Pemberitahuan Masa Berlaku Naskah Dinas: {$docNumber}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Yth. {$notifiable->name},")
            ->line('Pemberitahuan resmi mengenai masa berlaku naskah dinas di lingkungan Universitas Bina Insan.')
            ->line("**Nomor Naskah:** {$docNumber}")
            ->line("**Judul Dokumen:** {$docTitle}")
            ->line("**Unit Pengelola:** {$unitName}")
            ->line("**Tanggal Kedaluwarsa:** {$expiredDateStr}")
            ->line("**Sisa Waktu Aktif:** {$this->daysRemaining} Hari Kalender ({$this->interval->getLabel()})")
            ->action('Buka Dokumen di Portal Archiver', $viewUrl)
            ->line('Harap segera berkoordinasi dengan unit terkait untuk pembaruan, perpanjangan, atau penyusunan dokumen pengganti sebelum tanggal masa berlaku berakhir.')
            ->salutation('Salam hormat,'.PHP_EOL.'Sistem e-Arsip & Borang UNIVBI (Archiver-Sistem)');
    }

    public function toDatabase(object $notifiable): array
    {
        $docNumber = $this->document->document_number ?: 'Tanpa Nomor';
        $statusColor = match ($this->interval) {
            ReminderInterval::H7 => 'danger',
            ReminderInterval::H30 => 'warning',
            ReminderInterval::H60 => 'info',
        };

        return FilamentNotification::make()
            ->title("Pengingat [{$this->interval->getLabel()}]: {$docNumber}")
            ->body("Naskah '{$this->document->title}' berakhir dalam {$this->daysRemaining} hari (Tenggat: {$this->document->expired_date?->format('d/m/Y')}).")
            ->status($statusColor)
            ->actions([
                FilamentAction::make('view')
                    ->label('Buka Naskah')
                    ->url(url('/admin/documents/'.$this->document->id)),
            ])
            ->getDatabaseMessage();
    }
}
