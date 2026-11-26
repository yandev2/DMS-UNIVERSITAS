<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Document;
use App\Support\DocumentReminderMailPresenter;
use Carbon\CarbonInterface;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Document $document,
        public CarbonInterface $evaluatedAt
    ) {}

    /**
     * Mengutamakan pengiriman notifikasi panel lokal sebelum saluran email eksternal.
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

        return (new MailMessage)
            ->subject("[KEDALUWARSA] Masa Berlaku Naskah Dinas Telah Berakhir: {$docNumber}")
            ->view(
                'emails.document-reminder',
                DocumentReminderMailPresenter::forExpired(
                    notifiable: $notifiable,
                    document: $this->document,
                ),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $docNumber = $this->document->document_number ?: 'Tanpa Nomor';
        $expiredDateStr = $this->document->expired_date ? $this->document->expired_date->format('d/m/Y') : '-';

        return FilamentNotification::make()
            ->title("Naskah Kedaluwarsa: {$docNumber}")
            ->body("Masa berlaku naskah '{$this->document->title}' telah berakhir pada {$expiredDateStr}. Status otomatis dialihkan menjadi Kedaluwarsa.")
            ->danger()
            ->actions([
                FilamentAction::make('view')
                    ->label('Buka Naskah')
                    ->url(url('/admin/documents/'.$this->document->id)),
            ])
            ->getDatabaseMessage();
    }
}
