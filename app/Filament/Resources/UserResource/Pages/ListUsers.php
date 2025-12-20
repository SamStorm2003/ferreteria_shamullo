<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Carbon\Carbon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Usuario'),
        ];
    }
    public function getTabs(): array
    {
        return [
            null => Tab::make('Todos')
                ->badge(User::count())
                ->badgeColor('gray'),
            'registrados_hoy' => Tab::make('Registrados hoy')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereDate('created_at', Carbon::today())
                )
                ->badge(User::whereDate('created_at', Carbon::today())->count())
                ->badgeColor('secondary'),
            'registrados_semana' => Tab::make('Registrados esta semana')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                )
                ->badge(User::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count())
                ->badgeColor('info'),
        ];
    }
}
