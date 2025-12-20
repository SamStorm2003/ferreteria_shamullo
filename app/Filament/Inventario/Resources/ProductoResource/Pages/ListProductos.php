<?php

namespace App\Filament\Inventario\Resources\ProductoResource\Pages;

use App\Filament\Inventario\Resources\ProductoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Producto;
use Carbon\Carbon;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Producto'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProductoResource\Widgets\Productosgrafinv::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('Todos')
                ->badge(Producto::count())
                ->badgeColor('gray'),

            'sin_proveedor' => Tab::make('Sin proveedor')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('idProveedor'))
                ->badge(Producto::whereNull('idProveedor')->count())
                ->badgeColor('danger'),

            'activos' => Tab::make('Activos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'activo'))
                ->badge(Producto::where('estado', 'activo')->count())
                ->badgeColor('success'),

            'inactivos' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado', 'inactivo'))
                ->badge(Producto::where('estado', 'inactivo')->count())
                ->badgeColor('danger'),

            'ingresados_hoy' => Tab::make('Ingresados hoy')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereDate('fecha_ingreso', Carbon::today())
                )
                ->badge(Producto::whereDate('fecha_ingreso', Carbon::today())->count())
                ->badgeColor('secondary'),

            'ingresados_semana' => Tab::make('Ingresados esta semana')
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->whereBetween('fecha_ingreso', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                )
                ->badge(Producto::whereBetween('fecha_ingreso', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count())
                ->badgeColor('info'),
        ];
    }
}
