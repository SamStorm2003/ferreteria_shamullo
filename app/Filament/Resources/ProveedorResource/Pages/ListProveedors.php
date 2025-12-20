<?php

namespace App\Filament\Resources\ProveedorResource\Pages;

use App\Filament\Resources\ProveedorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Proveedor;
use Carbon\Carbon;

class ListProveedors extends ListRecords
{
    protected static string $resource = ProveedorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Proovedor'),
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('Todos')
                ->badge(Proveedor::count())
                ->badgeColor('gray'),

            'sin_telefono' => Tab::make('Sin teléfono')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('telefono'))
                ->badge(Proveedor::whereNull('telefono')->count())
                ->badgeColor('danger'),

            'activos' => Tab::make('Activos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'activo'))
                ->badge(Proveedor::where('estado', 'activo')->count())
                ->badgeColor('success'),

            'inactivos' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'inactivo'))
                ->badge(Proveedor::where('estado', 'inactivo')->count())
                ->badgeColor('danger'),

            'registrados_hoy' => Tab::make('Registrados hoy')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereDate('fecha_registro', Carbon::today())
                )
                ->badge(Proveedor::whereDate('fecha_registro', Carbon::today())->count())
                ->badgeColor('secondary'),

            'registrados_semana' => Tab::make('Registrados esta semana')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereBetween('fecha_registro', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                )
                ->badge(Proveedor::whereBetween('fecha_registro', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count())
                ->badgeColor('info'),
        ];
    }
}
