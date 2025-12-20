<?php

namespace App\Filament\Inventario\Resources\CompraResource\Pages;

use App\Filament\Inventario\Resources\CompraResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateCompra extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['idAlmacen'])) {
            Notification::make()
                ->title('Error')
                ->body('No se puede crear la compra sin un almacén asignado.')
                ->danger()
                ->send();
            $this->halt();
        }
        
        $data['idUsuario'] = Auth::id();
        
        if ($data['estado'] === 'pendiente') {
            Notification::make()
                ->title('Compra pendiente')
                ->body('La compra se ha registrado como pendiente. No se actualizará el stock hasta que se marque como completada.')
                ->warning()
                ->send();
        }
        
        return $data;
    }
/*edite aca procedimiento aca*/
    protected function afterCreate(): void
    {
        if ($this->record->estado === 'completada') {
            try {
                DB::statement('CALL sp_registrar_compra_completada(?, ?, ?)', [
                    $this->record->idCompra,
                    Auth::id(),
                    $this->record->idAlmacen
                ]);

                Notification::make()
                    ->title('Compra completada')
                    ->body('El stock ha sido actualizado exitosamente.')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                $this->record->delete();
                
                Notification::make()
                    ->title('Error al completar compra')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->send();
                
                throw $e;
            }
        }

        Notification::make()
            ->title('Compra registrada exitosamente')
            ->success()
            ->send();
    }
}