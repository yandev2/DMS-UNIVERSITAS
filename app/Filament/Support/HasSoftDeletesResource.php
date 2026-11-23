<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

trait HasSoftDeletesResource
{
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function canAccessTrash(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $subject = Str::snake(class_basename(static::getModel()));

        return $user->can("restore:{$subject}")
            || $user->can("restore_any:{$subject}")
            || $user->can("force_delete:{$subject}")
            || $user->can("force_delete_any:{$subject}");
    }

    public static function trashAction(): Action
    {
        return Action::make('trash')
            ->label('Trash')
            ->button()
            ->icon(Heroicon::OutlinedTrash)
            ->tooltip('Keranjang Sampah')
            ->color('gray')
            ->extraAttributes(['class' => 'ui-header-action-trash'])
            ->visible(fn (): bool => static::canAccessTrash())
            ->url(fn (): string => static::getUrl('trash'));
    }
}
