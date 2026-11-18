<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\ActivityLog;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);
        $record->loadCount([
            'photos as actual_photos_count',
            'postDocuments as actual_documents_count',
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        /** @var Post $record */
        $record = $this->record;

        return [
            Action::make('view_public')
                ->label('Lihat di Portal Publik')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('info')
                ->url(fn (): string => $record->getPublicUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $record->status === PostStatus::Published),

            Action::make('download_zip')
                ->label('Unduh Paket Foto (.ZIP)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->url(fn (): string => $record->getZipDownloadUrl(), shouldOpenInNewTab: true)
                ->visible(function () use ($record): bool {
                    $photosCount = $record->getAttribute('actual_photos_count') ?? $record->photos()->count();

                    return ((int) $photosCount > 0) && (auth()->user()?->can('post.download_zip') ?? false);
                }),

            Action::make('submit_review')
                ->label('Ajukan ke Humas')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Ajukan Naskah Berita ke Humas?')
                ->modalDescription('Naskah berita akan dikirim ke antrean peninjauan Tim Humas Universitas untuk dimoderasi sebelum tayang publik.')
                ->modalSubmitActionLabel('Ya, Ajukan Naskah')
                ->visible(fn (): bool => in_array($record->status, [PostStatus::Draft, PostStatus::Revision], true) && (auth()->user()?->can('update', $record) ?? false))
                ->action(function () use ($record): void {
                    $record->update([
                        'status' => PostStatus::PendingReview,
                        'submitted_at' => now(),
                    ]);

                    activity('cms_moderation')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->event('submitted')
                        ->log("Naskah berita '{$record->title}' diajukan ke tim Humas.");

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Naskah Berhasil Diajukan')
                        ->body('Naskah telah dikirim ke Tim Humas untuk peninjauan.')
                        ->send();
                }),

            Action::make('publish')
                ->label('Publikasikan')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publikasikan Naskah Berita ke Portal Resmi?')
                ->modalDescription('Naskah berita, dokumentasi foto, dan tautan naskah dinas akan langsung tayang secara resmi di portal publik.')
                ->modalSubmitActionLabel('Ya, Publikasikan')
                ->visible(fn (): bool => $record->status !== PostStatus::Published && (auth()->user()?->can('post.moderate_humas') ?? false))
                ->action(function () use ($record): void {
                    $record->update([
                        'status' => PostStatus::Published,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'published_at' => $record->published_at ?? now(),
                    ]);

                    activity('cms_moderation')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->event('published')
                        ->log("Naskah berita '{$record->title}' disetujui dan dipublikasikan di portal resmi.");

                    $record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Berita Berhasil Dipublikasikan')
                        ->body('Naskah berita kini tayang secara resmi di portal publik.')
                        ->send();
                }),

            Action::make('request_revision')
                ->label('Minta Revisi')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->modalHeading('Kembalikan Naskah untuk Revisi')
                ->modalDescription('Catatan redaksional akan dicantumkan dan status naskah dikembalikan ke penulis untuk disunting ulang.')
                ->modalSubmitActionLabel('Kirim Catatan Revisi')
                ->form([
                    Textarea::make('moderation_note')
                        ->label('Catatan Redaksional / Perbaikan Wajib')
                        ->placeholder('Tuliskan bagian narasi, foto, atau dokumen yang perlu diperbaiki...')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn (): bool => $record->status === PostStatus::PendingReview && (auth()->user()?->can('post.moderate_humas') ?? false))
                ->action(function (array $data) use ($record): void {
                    $record->update([
                        'status' => PostStatus::Revision,
                        'moderation_note' => $data['moderation_note'],
                    ]);

                    activity('cms_moderation')
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->event('revision_requested')
                        ->withProperties(['moderation_note' => $data['moderation_note']])
                        ->log("Tim Humas meminta revisi naskah '{$record->title}': {$data['moderation_note']}");

                    $record->refresh();

                    Notification::make()
                        ->warning()
                        ->title('Permintaan Revisi Dikirim')
                        ->body('Catatan perbaikan telah dicatat dan naskah dikembalikan ke penulis.')
                        ->send();
                }),

            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Post $record */
        $record = $this->getRecord();

        return $schema
            ->components([
                SchemaView::make('filament.components.post-status-banner')
                    ->viewData([
                        'post' => $record,
                    ]),

                Tabs::make('post_details_tabs')
                    ->tabs([
                        Tab::make('Naskah Berita & Informasi Lengkap')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                SchemaView::make('filament.components.post-metadata-view')
                                    ->viewData([
                                        'post' => $record,
                                    ]),
                            ]),

                        Tab::make('Galeri Multi-Foto Dokumentasi')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->badge(function () use ($record): ?int {
                                $count = (int) ($record->getAttribute('actual_photos_count') ?? $record->photos()->count());

                                return $count > 0 ? $count : null;
                            })
                            ->schema([
                                SchemaView::make('filament.components.post-gallery-view')
                                    ->viewData([
                                        'post' => $record,
                                    ]),
                            ]),

                        Tab::make('Tautan Naskah Dinas Sah Terkait')
                            ->icon(Heroicon::OutlinedDocumentCheck)
                            ->badge(function () use ($record): ?int {
                                $count = (int) ($record->getAttribute('actual_documents_count') ?? $record->postDocuments()->count());

                                return $count > 0 ? $count : null;
                            })
                            ->schema([
                                SchemaView::make('filament.components.post-documents-view')
                                    ->viewData([
                                        'post' => $record,
                                    ]),
                            ]),

                        Tab::make('Riwayat & Log Moderasi Humas')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->badge(function () use ($record): ?int {
                                $count = ActivityLog::where('subject_type', Post::class)
                                    ->where('subject_id', $record->id)
                                    ->count();

                                return $count ?: null;
                            })
                            ->schema([
                                SchemaView::make('filament.components.post-activity-log')
                                    ->viewData([
                                        'post' => $record,
                                    ]),
                            ]),
                    ]),

                $this->getRelationManagersContentComponent(),
            ]);
    }
}
