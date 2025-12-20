<?php

namespace App\Filament\Vendedor\Resources;

use App\Filament\Vendedor\Resources\EnviosResource\Pages;
use App\Filament\Vendedor\Resources\EnviosResource\RelationManagers;
use App\Models\Envios;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class EnviosResource extends Resource
{
    protected static ?string $model = Envios::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Envíos Registrados';
    protected static ?string $pluralLabel = 'Envíos Registrados';
    protected static ?string $navigationGroup = 'Ventas';

    public static function getNavigationBadge(): ?string
    {
        $query = static::getModel()::query();
        $query->where('estado_envio', '!=', 'entregado');
        if (!Auth::user()->hasRole('Super Admin')) {
            $idAlmacen = Auth::user()->idAlmacen;
            if ($idAlmacen) {
                $query->whereHas('venta.detalles', function (Builder $q) use ($idAlmacen) {
                    $q->where('idAlmacen', $idAlmacen);
                });
            } else {
                return '0';
            }
        }

        return (string) $query->count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'venta.clienteUsuario',
            'venta.clienteExterno',
            'venta.vendedor',
            'venta.detalles.producto.categoria',
            'venta.pagos'
        ]);
        if (!Auth::user()->hasRole('Super Admin')) {
            $idAlmacen = Auth::user()->idAlmacen;
            if ($idAlmacen) {
                $query->whereHas('venta.detalles', function (Builder $q) use ($idAlmacen) {
                    $q->where('idAlmacen', $idAlmacen);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles de la Venta')
                    ->schema([
                        Forms\Components\Placeholder::make('idVenta')
                            ->label('ID Venta')
                            ->content(fn($record) => $record->idVenta ? "Venta #{$record->idVenta}" : '-'),
                        Forms\Components\Placeholder::make('cliente')
                            ->label('Cliente')
                            ->content(fn($record) => $record->venta
                                ? ($record->venta->clienteUsuario
                                    ? $record->venta->clienteUsuario->name . ' ' . ($record->venta->clienteUsuario->apellido ?? '')
                                    : ($record->venta->clienteExterno
                                        ? $record->venta->clienteExterno->nombre
                                        : '-'))
                                : '-'),
                        Forms\Components\Placeholder::make('vendedor')
                            ->label('Vendedor')
                            ->content(fn($record) => $record->venta && $record->venta->vendedor ? $record->venta->vendedor->name : '-'),
                        Forms\Components\Placeholder::make('fecha')
                            ->label('Fecha Venta')
                            ->content(fn($record) => $record->venta && $record->venta->fecha ? $record->venta->fecha->format('d/m/Y H:i') : '-'),
                        Forms\Components\Placeholder::make('total')
                            ->label('Total')
                            ->content(fn($record) => $record->venta && is_numeric($record->venta->total) ? number_format($record->venta->total, 2) : '-'),
                        Forms\Components\Placeholder::make('estado')
                            ->label('Estado Venta')
                            ->content(fn($record) => $record->venta && $record->venta->estado ? ucfirst($record->venta->estado) : '-'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->venta !== null),

                Forms\Components\Section::make('Detalles de Productos')
                    ->schema([
                        Forms\Components\Placeholder::make('detalles')
                            ->label('Productos')
                            ->content(function ($record) {
                                if (!$record->venta || !$record->venta->detalles || $record->venta->detalles->isEmpty()) {
                                    return '-';
                                }
                                $html = '<table class="w-full border-collapse border border-gray-600">';
                                $html .= '<thead><tr class="bg-gray-600">';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Producto</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Categoría</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Almacén</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Cantidad</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Precio Unitario</th>';
                                $html .= '</tr></thead><tbody>';
                                foreach ($record->venta->detalles as $detalle) {
                                    $html .= '<tr>';
                                    $html .= '<td class="border border-gray-400 px-4 py-2">' . ($detalle->producto ? htmlspecialchars($detalle->producto->nombre) : '-') . '</td>';
                                    $html .= '<td class="border border-gray-400 px-4 py-2">' . ($detalle->producto && $detalle->producto->categoria ? htmlspecialchars($detalle->producto->categoria->nombre) : '-') . '</td>';
                                    $html .= '<td class="border border-gray-400 px-4 py-2">' . ($detalle->almacen ? htmlspecialchars($detalle->almacen->nombre) : '-') . '</td>';
                                    $html .= '<td class="border border-gray-400 px-4 py-2">' . ($detalle->cantidad ?? '-') . '</td>';
                                    $html .= '<td class="border border-gray-400 px-4 py-2">' . (is_numeric($detalle->precio_unitario) ? number_format($detalle->precio_unitario, 2) : '-') . '</td>';
                                    $html .= '</tr>';
                                }
                                $html .= '</tbody></table>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn($record) => $record->venta && $record->venta->detalles->isNotEmpty()),
                    ]),

                Forms\Components\Section::make('Detalles de Pago')
                    ->schema([
                        Forms\Components\Placeholder::make('pagos')
                            ->label('Pagos')
                            ->content(function ($record) {
                                if (!$record->venta || !$record->venta->pagos || $record->venta->pagos->isEmpty()) {
                                    return '-';
                                }
                                $html = '<table class="w-full border-collapse border border-gray-600">';
                                $html .= '<thead><tr class="bg-gray-600">';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Monto</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Método</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Fecha</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Estado</th>';
                                $html .= '<th class="border border-gray-600 px-4 py-2 text-left">Referencia</th>';
                                $html .= '</tr></thead><tbody>';
                                foreach ($record->venta->pagos as $pago) {
                                    $html .= '<tr>';
                                    $html .= '<td class="border border-gray-200 px-4 py-2">' . (is_numeric($pago->monto) ? number_format($pago->monto, 2) : '-') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-2">' . ($pago->metodo ? htmlspecialchars(ucfirst($pago->metodo)) : '-') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-2">' . ($pago->fecha ? $pago->fecha->format('d/m/Y H:i') : '-') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-2">' . ($pago->estado ? htmlspecialchars(ucfirst($pago->estado)) : '-') . '</td>';
                                    $html .= '<td class="border border-gray-200 px-4 py-2">' . ($pago->referencia_pago ? htmlspecialchars($pago->referencia_pago) : '-') . '</td>';
                                    $html .= '</tr>';
                                }
                                $html .= '</tbody></table>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn($record) => $record->venta && $record->venta->pagos->isNotEmpty()),
                    ]),

                Forms\Components\Section::make('Detalles de Envío')
                    ->schema([
                        Forms\Components\TextInput::make('direccion_envio')
                            ->label('Dirección de Envío')
                            ->required(),
                        Forms\Components\Select::make('metodo_envio')
                            ->label('Método de Envío')
                            ->options([
                                'estandar' => 'Estándar',
                                'express' => 'Express',
                                'recogida' => 'Recogida en Tienda',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('numero_seguimiento')
                            ->label('Número de Seguimiento'),
                        Forms\Components\Select::make('estado_envio')
                            ->label('Estado de Envío')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'enviado' => 'Enviado',
                                'entregado' => 'Entregado',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('fecha_envio')
                            ->label('Fecha de Envío'),
                        Forms\Components\DatePicker::make('fecha_entrega_estimada')
                            ->label('Fecha de Entrega Estimada'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idEnvio')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('idVenta')
                    ->label('Venta')
                    ->formatStateUsing(fn($state) => "Venta #{$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('productos')
                    ->label('Productos')
                    ->getStateUsing(function ($record) {
                        $detalles = $record->venta->detalles()->with('producto', 'almacen')->get();
                        return $detalles->map(function ($detalle) {
                            return ($detalle->producto ? htmlspecialchars($detalle->producto->nombre) : '-') .
                                " (Cant: {$detalle->cantidad}, Almacén: " . ($detalle->almacen ? htmlspecialchars($detalle->almacen->nombre) : '-') . ")";
                        })->implode(', ');
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        $detalles = $record->venta->detalles()->with('producto', 'almacen')->get();
                        return $detalles->map(function ($detalle) {
                            return ($detalle->producto ? htmlspecialchars($detalle->producto->nombre) : '-') .
                                " (Cant: {$detalle->cantidad}, Almacén: " . ($detalle->almacen ? htmlspecialchars($detalle->almacen->nombre) : '-') . ")";
                        })->implode(', ');
                    }),
                Tables\Columns\TextColumn::make('direccion_envio')
                    ->label('Dirección')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('metodo_envio')
                    ->label('Método')
                    ->formatStateUsing(fn($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('numero_seguimiento')
                    ->label('N° Seguimiento')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('estado_envio')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pendiente' => 'warning',
                        'enviado' => 'info',
                        'entregado' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('fecha_envio')
                    ->label('Fecha Envío')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('fecha_entrega_estimada')
                    ->label('Entrega Estimada')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado_envio')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'enviado' => 'Enviado',
                        'entregado' => 'Entregado',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')
                    ->label('Fecha de Registro')
                    ->date()
                    ->collapsible(),

                Tables\Grouping\Group::make('estado_envio')
                    ->label('Estado de Envío')
                    ->getTitleFromRecordUsing(fn($record) => ucfirst($record->estado_envio))
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnvios::route('/'),
            //'create' => Pages\CreateEnvios::route('/create'),
            'edit' => Pages\EditEnvios::route('/{record}/edit'),
        ];
    }
}
