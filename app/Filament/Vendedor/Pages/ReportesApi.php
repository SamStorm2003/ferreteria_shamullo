<?php

namespace App\Filament\Vendedor\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportesApi extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.vendedor.pages.reportes-api';

    public $fecha_inicio;

    public function generateReport()
    {
        $apiUrl = config('app.arkfacturereport_api_url');
        $nit = config('app.nit');
        $claveSecreta = config('app.arkfacture_clave_secreta');

        if (!$apiUrl || !$nit || !$claveSecreta) {
            Notification::make()
                ->title('Error')
                ->body('Configuración de API incompleta.')
                ->danger()
                ->send();
            return;
        }

        $data = [
            'nit' => $nit,
            'clave_secreta' => $claveSecreta,
        ];

        if ($this->fecha_inicio) {
            $data['fecha_inicio'] = $this->fecha_inicio;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($apiUrl, $data);

            Log::info('Reporte API Status: ' . $response->status());
            Log::info('Reporte API Body: ' . $response->body());

            if ($response->successful()) {
                $fileName = 'facturas_' . $nit . '_' . now()->format('YmdHis') . '.xlsx';
                Storage::disk('public')->put($fileName, $response->body());

                Notification::make()
                    ->title('Éxito')
                    ->body('Reporte generado correctamente.')
                    ->success()
                    ->send();

                return response()->download(Storage::disk('public')->path($fileName))->deleteFileAfterSend();
            }
            $errorBody = $response->header('Content-Type') === 'application/json'
                ? ($response->json()['error'] ?? 'Error desconocido')
                : 'Error HTTP ' . $response->status();

            Notification::make()
                ->title('Error')
                ->body($errorBody)
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error al generar reporte: ' . $e->getMessage());

            Notification::make()
                ->title('Error')
                ->body('Error de conexión con la API. Verifica los logs.')
                ->danger()
                ->send();
        }
    }
}
