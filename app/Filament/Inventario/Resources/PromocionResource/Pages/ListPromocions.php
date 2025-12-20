<?php

namespace App\Filament\Inventario\Resources\PromocionResource\Pages;

use App\Filament\Inventario\Resources\PromocionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPromocions extends ListRecords
{
    protected static string $resource = PromocionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva Promoción'),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return [
            PromocionResource\Widgets\Promocionesinv::class,
        ];
    }
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(\App\Models\Promocion::count())
                ->badgeColor('primary'),
            'activa' => Tab::make('Activas')
                ->badge(\App\Models\Promocion::where('estado', 'activa')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'activa')),
            'inactiva' => Tab::make('Inactivas')
                ->badge(\App\Models\Promocion::where('estado', 'inactiva')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'inactiva')),
        ];
    }
}
