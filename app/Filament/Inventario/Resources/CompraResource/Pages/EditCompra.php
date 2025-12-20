<?php

namespace App\Filament\Inventario\Resources\CompraResource\Pages;

use App\Filament\Inventario\Resources\CompraResource;
use App\Models\Compra;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class EditCompra extends EditRecord
{
    protected static string $resource = CompraResource::class;

    public function mount($record): void
    {
        parent::mount($record);
        
        if ($this->record->estado !== 'pendiente') {
            Notification::make()
                ->title('Esta compra no se puede editar.')
                ->danger()
                ->send();

            $this->redirect(CompraResource::getUrl('index'));
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['idUsuario'] = Auth::id();
        return $data;
    }
/*edite aca procedimiento aca*/
    /**
     * @param  Model  $record
     * @param  array<string, mixed>  $data
     * @return Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $compra = $record instanceof Compra ? $record : Compra::findOrFail($record->idCompra);
        
        DB::beginTransaction();
        
        try {
            $originalEstado = $compra->estado;
            
            $compra->update($data);

            if ($data['estado'] === 'completada' && $originalEstado !== 'completada') {
                if ($compra->detalles()->count() === 0) {
                    throw new \Exception('No se encontraron detalles para esta compra.');
                }

                DB::statement('CALL sp_completar_compra(?, ?)', [
                    $compra->idCompra,
                    Auth::id()
                ]);

                Notification::make()
                    ->title('Compra completada')
                    ->body('El stock ha sido actualizado correctamente.')
                    ->success()
                    ->send();
            } 
            elseif ($data['estado'] === 'cancelada' && $originalEstado !== 'cancelada') {
                if ($compra->detalles()->count() === 0) {
                    throw new \Exception('No se encontraron detalles para esta compra.');
                }

                DB::statement('CALL sp_cancelar_compra(?, ?)', [
                    $compra->idCompra,
                    Auth::id()
                ]);

                Notification::make()
                    ->title('Compra cancelada')
                    ->body('La compra ha sido marcada como cancelada.')
                    ->warning()
                    ->send();
            }

            DB::commit();
            return $compra;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->title('Error')
                ->body('Ocurrió un error al actualizar la compra: ' . $e->getMessage())
                ->danger()
                ->send();
                
            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}