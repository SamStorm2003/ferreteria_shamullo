<?php

namespace App\Filament\Inventario\Resources\CompraResource\Pages;

use App\Filament\Inventario\Resources\CompraResource;
use App\Filament\Inventario\Resources\CompraResource\Widgets\ComprasWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCompras extends ListRecords
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Compra'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(\App\Models\Compra::count())
                ->badgeColor('primary'),
            'completada' => Tab::make('Completadas')
                ->badge(\App\Models\Compra::where('estado', 'completada')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'completada')),
            'pendiente' => Tab::make('Pendientes')
                ->badge(\App\Models\Compra::where('estado', 'pendiente')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'pendiente')),
            'cancelada' => Tab::make('Canceladas')
                ->badge(\App\Models\Compra::where('estado', 'cancelada')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'cancelada')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ComprasWidget::class,
        ];
    }
}