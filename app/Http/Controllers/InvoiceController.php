<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\Almacen;
use App\Models\Factura;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function download(Request $request, $idVenta)
    {
        Log::debug('Attempting to download invoice for venta', ['idVenta' => $idVenta]);

        $record = Venta::with(['clienteUsuario', 'clienteExterno', 'detalles', 'pagos', 'vendedor', 'envio'])->findOrFail($idVenta);

        if ($record->estado !== 'completada') {
            Log::warning('Venta not completed', ['idVenta' => $idVenta]);
            Notification::make()
                ->title('Error')
                ->body('La factura solo se puede generar para ventas completadas.')
                ->danger()
                ->send();
            return redirect()->back();
        }

        $cliente = $record->clienteUsuario ?? $record->clienteExterno;
        $detalles = $record->detalles->map(function ($detalle) {
            $producto = Producto::with('promocion')->find($detalle->idProducto);
            $descuento = 0;
            if ($producto->promocion && $producto->promocion->estado === 'activo' && now()->between($producto->promocion->fecha_inicio, $producto->promocion->fecha_fin)) {
                $descuento = $producto->promocion->descuento * $detalle->cantidad;
            }
            return [
                'descripcion' => $producto->nombre,
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => $detalle->precio_unitario,
                'descuento' => $descuento,
            ];
        })->toArray();
        $pago = $record->pagos->first();
        $totalDescuentos = array_sum(array_column($detalles, 'descuento'));
        $data = [
            'nit' => config('app.nit'),
            'razon_social' => config('app.razon_social'),
            'sucursal' => Almacen::find($record->detalles->first()->idAlmacen)?->nombre ?? 'Sucursal Principal',
            'clave_secreta' => config('app.arkfacture_clave_secreta'),
            'codigo_autorizacion' => 'AUTORIZA-001',
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_limite_emision' => now()->addDays(30)->format('Y-m-d'),
            'codigo_control' => 'CTRL-' . $record->idVenta,
            'tipo_emision' => 'en línea',
            'modalidad' => 'electrónica en línea',
            'estado_envio' => $record->envio?->estado_envio ?? 'pendiente',
            'total' => $record->total,
            'id_venta' => $record->idVenta,
            'numero_factura' => 'INV-' . $record->idVenta,
            'usuario_atendio' => $record->vendedor?->name ?? Auth::user()->name,
            'encargado_empresa' => config('app.encargado_empresa'),
            'telefono_empresa' => config('app.telefono_empresa'),
            'cliente_nombre' => $cliente->name ?? $cliente->nombre ?? null,
            'cliente_nit_ci' => $cliente->documento_identidad ?? null,
            'cliente_direccion' => $cliente->direccion ?? ($record->envio?->direccion_envio ?? null),
            'cliente_telefono' => $cliente->telefono ?? null,
            'detalle_venta' => $detalles,
            'descuentos' => $totalDescuentos,
            'impuestos' => config('app.impuestos'),
            'monto_pagado' => $pago->monto ?? $record->total,
            'moneda' => 'BOB',
            'metodo_pago' => $pago->metodo ?? 'efectivo',
            'fecha_impresion' => now()->format('Y-m-d'),
            'notas' => 'Factura generada desde el sistema de ventas',
            'tipo_entrega' => $record->tipo_entrega === 'envio' ? 'delivery' : 'retiro en tienda',
            'numero_atencion_cliente' => 'ATN-' . $record->idVenta,
        ];

        try {
            Log::debug('Sending request to Arkfacture API', ['url' => config('app.arkfacture_api_url'), 'data' => $data]);
            $response = Http::post(config('app.arkfacture_api_url'), $data);
            Log::debug('Arkfacture API response', ['status' => $response->status(), 'body' => substr($response->body(), 0, 100)]);

            if ($response->status() === 200) {
                $numeroFactura = $response->header('X-Numero-Factura') ?? 'factura_' . $record->idVenta;
                Factura::create([
                    'idVenta' => $record->idVenta,
                    'numero_factura' => $numeroFactura,
                    'fecha' => now(),
                    'nit_emisor' => config('app.nit'),
                    'nit_cliente' => $cliente->documento_identidad ?? null,
                    'razon_social_cliente' => $cliente->name ?? $cliente->nombre ?? null,
                    'total' => $record->total,
                ]);

                $pdfContent = $response->body();
                return response()->streamDownload(
                    fn() => print($pdfContent),
                    'factura_' . $numeroFactura . '.pdf',
                    ['Content-Type' => 'application/pdf']
                );
            } else {
                $errorMessage = $response->json()['error'] ?? 'Error desconocido';
                $status = $response->status();
                $body = match ($status) {
                    401 => 'Clave secreta inválida.',
                    404 => 'Empresa no encontrada.',
                    429 => 'Sistema saturado. Intente de nuevo más tarde.',
                    500 => 'Error interno al procesar la factura: ' . $errorMessage,
                    504 => 'Tiempo de espera excedido.',
                    default => $errorMessage,
                };
                Log::error('Arkfacture API error', ['status' => $status, 'message' => $body]);
                Notification::make()
                    ->title('Error al generar factura (Código: ' . $status . ')')
                    ->body($body)
                    ->danger()
                    ->send();
                return redirect()->back();
            }
        } catch (\Exception $e) {
            Log::error('Exception in Arkfacture API call', ['message' => $e->getMessage()]);
            Notification::make()
                ->title('Error al conectar con la API')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return redirect()->back();
        }
    }
}
