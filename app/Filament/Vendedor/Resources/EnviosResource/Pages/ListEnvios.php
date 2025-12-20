<?php

namespace App\Filament\Vendedor\Resources\EnviosResource\Pages;

use App\Filament\Vendedor\Resources\EnviosResource;
use App\Models\Envios;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListEnvios extends ListRecords
{
    protected static string $resource = EnviosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //   Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EnviosResource\Widgets\Envioswidget::class,
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = Envios::query();
        if (!Auth::user()->hasRole('Super Admin')) {
            $idAlmacen = Auth::user()->idAlmacen;
            if ($idAlmacen) {
                $baseQuery->whereHas('venta.detalles', function (Builder $q) use ($idAlmacen) {
                    $q->where('idAlmacen', $idAlmacen);
                });
            } else {
                $baseQuery->whereRaw('1 = 0');
            }
        }

        return [
            'all' => Tab::make('Todas')
                ->badge((clone $baseQuery)->where('estado_envio', '!=', 'entregado')->count())
                ->badgeColor('primary'),
            'pendiente' => Tab::make('Pendientes')
                ->badge((clone $baseQuery)->where('estado_envio', 'pendiente')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado_envio', 'pendiente')),
            'enviado' => Tab::make('Enviados')
                ->badge((clone $baseQuery)->where('estado_envio', 'enviado')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado_envio', 'enviado')),
            'entregado' => Tab::make('Entregados')
                ->badge((clone $baseQuery)->where('estado_envio', 'entregado')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('estado_envio', 'entregado')),
        ];
    }
}
