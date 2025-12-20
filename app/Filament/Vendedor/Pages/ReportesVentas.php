<?php

namespace App\Filament\Vendedor\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use App\Exports\VentasExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class ReportesVentas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.vendedor.pages.reportes-ventas';

    public $tipo = 'diario';
    public $fechaInicio;
    public $fechaFin;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('tipo')
                ->label('Tipo de Reporte')
                ->options(['diario' => 'Diario', 'ventasfecha' => 'Por Rango de Fechas'])
                ->default('diario')
                ->reactive(),
            Forms\Components\DatePicker::make('fechaInicio')
                ->label('Fecha Inicio')
                ->default(now()->startOfDay())
                ->required()
                ->visible(fn($get) => $get('tipo') === 'ventasfecha'),
            Forms\Components\DatePicker::make('fechaFin')
                ->label('Fecha Fin')
                ->default(now()->endOfDay())
                ->required()
                ->visible(fn($get) => $get('tipo') === 'ventasfecha')
                ->afterStateUpdated(function ($state, callable $set, $get) {
                    if ($get('fechaInicio') && Carbon::parse($get('fechaInicio'))->gt(Carbon::parse($state))) {
                        Notification::make()
                            ->warning()
                            ->title('Fecha inválida')
                            ->body('La fecha de fin debe ser posterior a la fecha de inicio.')
                            ->send();
                        $set('fechaFin', null);
                    }
                }),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('export')
                    ->label('Generar Reporte')
                    ->action('exportarReporte')
                    ->color('primary'),
            ]),
        ];
    }

    public function exportarReporte()
    {
        try {
            $fileName = 'Reporte_Ventas_' . Carbon::now()->format('Y-m-d') . '.xlsx';
            Notification::make()
                ->success()
                ->title('Éxito')
                ->body('El reporte se generó correctamente.')
                ->send();
            return Excel::download(new VentasExport($this->tipo, $this->fechaInicio, $this->fechaFin), $fileName);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se pudo generar el reporte: ' . $e->getMessage())
                ->send();
            throw $e;
        }
    }
}
