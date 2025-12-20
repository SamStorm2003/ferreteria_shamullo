<?php

namespace App\Filament\Vendedor\Resources\UserResource\Pages;

use App\Filament\Vendedor\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Registrar Cliente'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserResource\Widgets\ClientesRegistrados::class,
        ];
    }
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                UserResource::getEloquentQuery()
                    ->whereNotIn('id', function ($subquery) {
                        $subquery->select('model_id')
                            ->from('model_has_roles')
                            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                            ->whereIn('roles.name', ['Inventario', 'Vendedor', 'Super Admin'])
                            ->where('model_has_roles.model_type', \App\Models\User::class);
                    })
            )
            ->columns(UserResource::table($table)->getColumns())
            ->filters(UserResource::table($table)->getFilters())
            ->actions(UserResource::table($table)->getActions())
            ->bulkActions(UserResource::table($table)->getBulkActions());
    }
}
