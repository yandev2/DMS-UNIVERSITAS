<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\Actions\DocumentWorkflowActions;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DeletionGuard;
use App\Models\ActivityLog;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * @return array<Action|ActionGroup>
     */
    public function getCachedHeaderActions(): array
    {
        return array_values(array_filter(
            $this->cachedHeaderActions,
            fn ($action): bool => ($action instanceof Action ? $action->getName() : '') !== 'preview',
        ));
    }

    protected function getHeaderActions(): array
    {
        /** @var Document $record */
        $record = $this->record;

        return [
            Action::make('preview')
                ->label('Pratinjau Berkas')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->modalHeading("Pratinjau: {$record->title}")
                ->modalWidth(Width::SixExtraLarge)
                ->modalContent(fn (): View => view('filament.components.document-preview-modal', [
                    'streamUrl' => route('admin.documents.stream', $record),
                    'downloadUrl' => route('admin.documents.download', $record),
                    'document' => $record,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->visible(fn (): bool => auth()->user()?->can('view', $record) && ($record->primaryFile !== null || $record->files()->exists())),

            Action::make('download')
                ->label('Unduh Berkas')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('admin.documents.download', $record))
                ->visible(fn (): bool => auth()->user()?->can('downloadPrivate', $record) && ($record->primaryFile !== null || $record->files()->exists())),

            DocumentWorkflowActions::makeSubmitAction(fixedRecord: $record),
            DocumentWorkflowActions::makeApproveAction(fixedRecord: $record),
            DocumentWorkflowActions::makeRequestRevisionAction(fixedRecord: $record),
            DocumentWorkflowActions::makeRejectAction(fixedRecord: $record),
            DocumentWorkflowActions::makeResubmitAction(fixedRecord: $record),
            DocumentWorkflowActions::makeStampAction(fixedRecord: $record),

            EditAction::make(),
            DeletionGuard::preventIfRestricted(DeleteAction::make()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        /** @var Document $record */
        $record = $this->getRecord();

        return $schema
            ->components([
                SchemaView::make('filament.components.document-approval-stepper')
                    ->viewData([
                        'document' => $record,
                    ]),

                Tabs::make('document_details_tabs')
                    ->tabs([
                        Tab::make('Informasi Naskah Dinas & Berkas')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                SchemaView::make('filament.components.document-metadata-view')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),

                        Tab::make('Riwayat Alur Persetujuan')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->badge(fn (): ?int => $record->approvals()->count() ?: null)
                            ->schema([
                                SchemaView::make('filament.components.document-approval-history')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),

                        Tab::make('Catatan Keamanan & Aktivitas')
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->badge(function () use ($record): ?int {
                                $count = ActivityLog::where('subject_type', Document::class)
                                    ->where('subject_id', $record->id)
                                    ->count();

                                return $count ?: null;
                            })
                            ->schema([
                                SchemaView::make('filament.components.document-activity-log')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),

                        Tab::make('Pengingat Masa Berlaku')
                            ->icon(Heroicon::OutlinedBellAlert)
                            ->visible(fn (): bool => (bool) $record->has_expiry)
                            ->badge(fn (): ?int => $record->reminderLogs()->count() ?: null)
                            ->schema([
                                SchemaView::make('filament.components.document-reminder-history')
                                    ->viewData([
                                        'document' => $record,
                                    ]),
                            ]),
                    ]),

                $this->getRelationManagersContentComponent(),
            ]);
    }
}
