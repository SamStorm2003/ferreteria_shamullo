<?php

namespace App\Filament\Inventario\Resources\MovimientoInventarioResource\Pages;

use App\Filament\Inventario\Resources\MovimientoInventarioResource;
use App\Models\MovimientoInventario;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;


class ListMovimientoInventarios extends ListRecords
{
    protected static string $resource = MovimientoInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Movimiento de Inventario'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MovimientoInventarioResource\Widgets\Movimientoinv::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('Todos')
                ->badge(MovimientoInventario::count())
                ->badgeColor('gray'),

            'entradas' => Tab::make('Entradas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('tipo', 'entrada'))
                ->badge(MovimientoInventario::where('tipo', 'entrada')->count())
                ->badgeColor('success'),

            'salidas' => Tab::make('Salidas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('tipo', 'salida'))
                ->badge(MovimientoInventario::where('tipo', 'salida')->count())
                ->badgeColor('danger'),

            'ajustes' => Tab::make('Ajustes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('tipo', 'ajuste'))
                ->badge(MovimientoInventario::where('tipo', 'ajuste')->count())
                ->badgeColor('warning'),
        ];
    }
}
