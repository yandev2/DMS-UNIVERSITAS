<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Document;
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
        $docTitle = $this->document->title;
        $unitName = $this->document->unit?->name ?: 'Unit Kampus';
        $expiredDateStr = $this->document->expired_date ? $this->document->expired_date->format('d/m/Y') : '-';
        $viewUrl = url('/admin/documents/'.$this->document->id);
        $recipientName = $notifiable->name ?? 'Bapak/Ibu';

        return (new MailMessage)
            ->subject("[KEDALUWARSA] Masa Berlaku Naskah Dinas Telah Berakhir: {$docNumber}")
            ->greeting("Yth. {$recipientName},")
            ->line('Pemberitahuan resmi bahwa masa berlaku naskah dinas berikut di lingkungan Universitas Bina Insan telah berakhir dan statusnya otomatis dialihkan menjadi **Kedaluwarsa (Expired)**.')
            ->line("**Nomor Naskah:** {$docNumber}")
            ->line("**Judul Dokumen:** {$docTitle}")
            ->line("**Unit Pengelola:** {$unitName}")
            ->line("**Tanggal Berakhir Masa Berlaku:** {$expiredDateStr}")
            ->action('Buka Dokumen di Portal Archiver', $viewUrl)
            ->line('Harap segera menindaklanjuti dengan penerbitan naskah pembaruan, perpanjangan, atau pengarsipan pasif sesuai prosedur tata naskah dinas.')
            ->salutation('Salam hormat,'.PHP_EOL.'Sistem e-Arsip & Borang UNIVBI (Archiver-Sistem)');
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
