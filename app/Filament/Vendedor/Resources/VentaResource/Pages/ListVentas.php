<?php

namespace App\Filament\Vendedor\Resources\VentaResource\Pages;

use App\Filament\Vendedor\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Venta;
use Carbon\Carbon;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Venta'),
        ];
    }

     protected function getHeaderWidgets(): array
    {
        return [
           VentaResource\Widgets\Ventaswidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(Venta::count())
                ->badgeColor('primary'),

            'completada' => Tab::make('Completadas')
                ->badge(Venta::where('estado', 'completada')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'completada')),

            'pendiente' => Tab::make('Pendientes')
                ->badge(Venta::where('estado', 'pendiente')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'pendiente')),

            'cancelada' => Tab::make('Canceladas')
                ->badge(Venta::where('estado', 'cancelada')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'cancelada')),
        ];
    }
}
