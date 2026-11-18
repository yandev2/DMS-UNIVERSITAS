<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DeletionGuard
{
    /**
     * @template TAction of DeleteAction|ForceDeleteAction
     *
     * @param  TAction  $action
     * @return TAction
     */
    public static function preventIfRestricted(DeleteAction|ForceDeleteAction $action): DeleteAction|ForceDeleteAction
    {
        return $action->before(function (Model $record, DeleteAction|ForceDeleteAction $action): void {
            if (method_exists($record, 'getDeletionRestrictionReason') && ($reason = $record->getDeletionRestrictionReason())) {
                $name = $record->name ?? $record->title ?? $record->code ?? ('ID #'.$record->getKey());

                Notification::make()
                    ->danger()
                    ->title("Data [{$name}] Tidak Dapat Dihapus!")
                    ->body("Data master ini tidak dapat dihapus karena masih digunakan oleh: {$reason}. Hapus atau pindahkan data terkait terlebih dahulu untuk menghindari data yatim piatu.")
                    ->persistent()
                    ->send();

                $action->cancel();
            }
        });
    }

    /**
     * @template TBulkAction of DeleteBulkAction|ForceDeleteBulkAction
     *
     * @param  TBulkAction  $action
     * @return TBulkAction
     */
    public static function preventBulkIfRestricted(DeleteBulkAction|ForceDeleteBulkAction $action): DeleteBulkAction|ForceDeleteBulkAction
    {
        return $action->before(function (Collection $records, DeleteBulkAction|ForceDeleteBulkAction $action): void {
            $blocked = [];

            foreach ($records as $record) {
                if (method_exists($record, 'getDeletionRestrictionReason') && ($reason = $record->getDeletionRestrictionReason())) {
                    $name = $record->name ?? $record->title ?? $record->code ?? ('ID #'.$record->getKey());
                    $blocked[] = "• [{$name}]: masih digunakan oleh {$reason}";
                }
            }

            if (! empty($blocked)) {
                Notification::make()
                    ->danger()
                    ->title('Penghapusan Massal Dibatalkan!')
                    ->body("Beberapa data master tidak dapat dihapus karena masih memiliki data terkait:\n".implode("\n", $blocked)."\n\nSilakan hapus atau pindahkan relasi data tersebut terlebih dahulu.")
                    ->persistent()
                    ->send();

                $action->cancel();
            }
        });
    }
}
