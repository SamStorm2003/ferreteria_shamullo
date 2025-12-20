<?php

namespace App\Filament\Vendedor\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClientesRegistrados extends BaseWidget
{
    protected function getStats(): array
    {
        $idsExcluidos = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['Super Admin', 'Vendedor', 'Inventario'])
            ->where('model_has_roles.model_type', User::class)
            ->pluck('model_has_roles.model_id');

        return [
            Stat::make('Total Clientes Registrados', User::whereNotIn('id', $idsExcluidos)->count())
                ->description('Sin Super Admin, Vendedor ni Inventario')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Clientes Activos', User::whereNotIn('id', $idsExcluidos)->where('estado', 'activo')->count())
                ->description('Usuarios en estado activo')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Clientes Inactivos', User::whereNotIn('id', $idsExcluidos)->where('estado', 'inactivo')->count())
                ->description('Usuarios en estado inactivo')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
