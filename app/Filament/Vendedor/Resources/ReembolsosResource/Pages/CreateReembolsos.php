<?php

namespace App\Filament\Vendedor\Resources\ReembolsosResource\Pages;

use App\Filament\Vendedor\Resources\ReembolsosResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Reembolsos;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\StockAlmacen;
use App\Models\MovimientoInventario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class CreateReembolsos extends CreateRecord
{
    protected static string $resource = ReembolsosResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        DB::beginTransaction();
        try {

            $reembolso = Reembolsos::create($data);
            if ($reembolso->estado === 'aprobado') {
                $venta = Venta::find($reembolso->idVenta);
                if (!$venta) {
                    throw new \Exception('La venta asociada no fue encontrada.');
                }
                $venta->update(['estado' => 'cancelada']);
                $detalles = DetalleVenta::where('idVenta', $venta->idVenta)->get();
                foreach ($detalles as $detalle) {
                    $stock = StockAlmacen::where('idProducto', $detalle->idProducto)
                        ->where('idAlmacen', $detalle->idAlmacen)
                        ->first();
                    if (!$stock) {
                        throw new \Exception("No se encontró stock para el producto #{$detalle->idProducto} en el almacén #{$detalle->idAlmacen}.");
                    }
                    $stock->cantidad += $detalle->cantidad;
                    $stock->save();
                    MovimientoInventario::create([
                        'idProducto' => $detalle->idProducto,
                        'idAlmacen' => $detalle->idAlmacen,
                        'tipo' => 'entrada',
                        'cantidad' => $detalle->cantidad,
                        'costo_unitario' => $detalle->precio_unitario,
                        'fecha' => now(),
                        'idUsuario' => auth()->id(),
                        'motivo' => "Reembolso de venta #{$venta->idVenta}",
                    ]);
                }
                Notification::make()
                    ->title('Reembolso Aprobado')
                    ->body('El reembolso ha sido procesado correctamente. El stock y la venta han sido actualizados.')
                    ->success()
                    ->send();
            }
            DB::commit();
            return $reembolso;
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Error en el Reembolso')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
            throw $e;
        }
    }
}
