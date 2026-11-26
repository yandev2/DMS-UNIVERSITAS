<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ReminderInterval;
use App\Models\Document;
use App\Support\DocumentReminderMailPresenter;
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

        $urgencyPrefix = match ($this->interval) {
            ReminderInterval::H7 => '[KRITIS - Sisa 7 Hari]',
            ReminderInterval::H30 => '[PERINGATAN - Sisa 30 Hari]',
            ReminderInterval::H60 => '[PENGINGAT - Sisa 60 Hari]',
        };

        $subject = "{$urgencyPrefix} Pemberitahuan Masa Berlaku Naskah Dinas: {$docNumber}";

        return (new MailMessage)
            ->subject($subject)
            ->view(
                'emails.document-reminder',
                DocumentReminderMailPresenter::forExpiryReminder(
                    notifiable: $notifiable,
                    document: $this->document,
                    interval: $this->interval,
                    daysRemaining: $this->daysRemaining,
                ),
            );
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
