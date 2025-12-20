<?php

namespace App\Filament\Resources\AlmacenResource\Pages;

use App\Filament\Resources\AlmacenResource;
use App\Models\Almacen;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;


class ListAlmacens extends ListRecords
{
    protected static string $resource = AlmacenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Almacen'),
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('Todos')
                ->badge(Almacen::count())
                ->badgeColor('gray'),

            'registrados_hoy' => Tab::make('Registrados hoy')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereDate('fecha_registro', Carbon::today())
                )
                ->badge(Almacen::whereDate('fecha_registro', Carbon::today())->count())
                ->badgeColor('success'),

            'registrados_esta_semana' => Tab::make('Registrados esta semana')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereBetween('fecha_registro', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                )
                ->badge(Almacen::whereBetween('fecha_registro', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count())
                ->badgeColor('info'),

            'sin_ubicacion' => Tab::make('Sin ubicación')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('ubicacion'))
                ->badge(Almacen::whereNull('ubicacion')->count())
                ->badgeColor('danger'),
        ];
    }
}
