<?php

namespace App\Filament\Inventario\Resources;

use App\Filament\Inventario\Resources\CompraResource\Pages;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Almacen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ExportAction;
use App\Filament\Exports\CompraExporter;
use Illuminate\Support\Facades\Auth;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Compras';
    protected static ?string $pluralLabel = 'Compras';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['total', 'estado'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Compra')
                    ->schema([
                        Forms\Components\Select::make('idProveedor')
                            ->relationship('proveedor', 'nombre', fn($query) => $query->where('estado', 'activo'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Proveedor')
                            ->disabled(fn($record) => $record && (!Auth::user()->hasRole('Super Admin') || $record->estado !== 'pendiente')),
                        Forms\Components\DateTimePicker::make('fecha')
                            ->default(now())
                            ->required()
                            ->disabled(fn($record) => $record && (!Auth::user()->hasRole('Super Admin') || $record->estado !== 'pendiente'))
                            ->label('Fecha'),
                        Forms\Components\Select::make('estado')
                            ->options([
                                'pendiente' => 'Pendiente',
                                'completada' => 'Completada',
                                'cancelada' => 'Cancelada',
                            ])
                            ->default('pendiente')
                            ->required()
                            ->disabled(fn($record) => $record && (
                                (Auth::user()->hasRole('Super Admin') && $record->estado === 'completada') ||
                                (!Auth::user()->hasRole('Super Admin') && $record->estado !== 'pendiente')
                            ))
                            ->label('Estado'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Almacén')
                    ->schema(function () {
                        if (Auth::user()->hasRole('Super Admin')) {
                            return [
                                Forms\Components\Select::make('idAlmacen')
                                    ->label('Almacén')
                                    ->relationship('almacen', 'nombre')
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->preload()
                                    ->disabled(fn($record) => $record && $record->estado !== 'pendiente'),
                            ];
                        }
                        $idAlmacen = Auth::user()->idAlmacen;
                        if (!$idAlmacen) {
                            return [
                                Forms\Components\Placeholder::make('almacen_error')
                                    ->label('Error')
                                    ->content('No tienes un almacén asignado. Contacta al administrador.'),
                            ];
                        }
                        return [
                            Forms\Components\Hidden::make('idAlmacen')
                                ->default($idAlmacen)
                                ->dehydrated(true),
                            Forms\Components\Placeholder::make('almacen_nombre')
                                ->label('Almacén')
                                ->content(Almacen::find($idAlmacen)?->nombre ?? 'Sin almacén asignado'),
                        ];
                    }),
                Forms\Components\Section::make('Detalles de la Compra')
                    ->schema([
                        Forms\Components\Repeater::make('detalles')
                            ->relationship('detalles')
                            ->schema([
                                Forms\Components\Select::make('idProducto')
                                    ->label('Producto')
                                    ->relationship('producto', 'nombre', function ($query, $get) {
                                        $idAlmacen = Auth::user()->hasRole('Super Admin') ? $get('../../idAlmacen') : Auth::user()->idAlmacen;
                                        return $query->where('estado', 'activo')
                                            ->whereIn('idProducto', StockAlmacen::where('idAlmacen', $idAlmacen)->pluck('idProducto'));
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, $get) {
                                        $idAlmacen = Auth::user()->hasRole('Super Admin') ? $get('../../idAlmacen') : Auth::user()->idAlmacen;
                                        if ($idAlmacen && $state) {
                                            $stock = StockAlmacen::where('idProducto', $state)
                                                ->where('idAlmacen', $idAlmacen)
                                                ->first();
                                            $set('costo_unitario', $stock ? $stock->costo_unitario : 0);
                                            $set('precio_venta', $stock ? $stock->precio_venta : 0);
                                        }
                                    })
                                    ->disableOptionWhen(function ($value, $get, $state, $operation) {
                                        $repeaterItems = $get('../../detalles') ?? [];
                                        $selectedIds = collect($repeaterItems)
                                            ->pluck('idProducto')
                                            ->filter()
                                            ->reject(fn($id) => $id === $state);
                                        return $selectedIds->contains($value);
                                    })
                                    ->disabled(fn($record, $operation) => $record && $operation === 'edit' && (!Auth::user()->hasRole('Super Admin') || $record->estado !== 'pendiente'))
                                    ->rules([
                                        fn($get, $operation) => function ($attribute, $value, $fail) use ($get, $operation) {
                                            $repeaterItems = $get('../../detalles') ?? [];
                                            $productoIds = collect($repeaterItems)->pluck('idProducto')->filter();
                                            if ($operation === 'edit' && $productoIds->duplicates()->isNotEmpty()) {
                                                $producto = Producto::find($productoIds->duplicates()->first());
                                                $fail("El producto '{$producto->nombre}' ya está agregado. Actualice la cantidad en la línea existente.");
                                            }
                                        },
                                    ]),
                                Forms\Components\TextInput::make('cantidad')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->label('Cantidad')
                                    ->live(onBlur: true)
                                    ->disabled(fn($record) => $record && (!Auth::user()->hasRole('Super Admin') || $record->estado !== 'pendiente')),
                                Forms\Components\TextInput::make('costo_unitario')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->suffix('Bs')
                                    ->label('Costo Unitario')
                                    ->live(onBlur: true)
                                    ->disabled(fn($record) => $record && (!Auth::user()->hasRole('Super Admin') || $record->estado !== 'pendiente')),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->addActionLabel('Agregar Producto')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $total = collect($state)->sum(function ($item) {
                                    $cantidad = floatval($item['cantidad'] ?? 0);
                                    $costoUnitario = floatval($item['costo_unitario'] ?? 0);
                                    return $cantidad * $costoUnitario;
                                });
                                $set('total', $total);
                            })
                            ->required()
                            ->disabled(fn($record) => $record && (!Auth::user()->hasRole('Super Admin') || $record->estado !== 'pendiente')),
                        Forms\Components\TextInput::make('total')
                            ->label('Total Compra')
                            ->suffix('Bs')
                            ->disabled()
                            ->dehydrated(true)
                            ->default(0)
                            ->minValue(1)
                            ->formatStateUsing(fn($state) => number_format($state, 2, '.', ''))
                            ->helperText('Por favor revise el total calculado automáticamente antes de guardar.')
                            ->hint('Este valor se calcula automáticamente.')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idCompra')
                    ->label('ID Compra')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('detalles.producto.descripcion')
                    ->label('Productos')
                    ->getStateUsing(function ($record) {
                        $detalles = $record->detalles ?? collect();
                        if ($detalles->isEmpty()) {
                            return '<span class="text-gray-400">Sin productos</span>';
                        }
                        $mostrarHasta = 2;
                        $contenido = $detalles->take($mostrarHasta)->map(function ($detalle) use ($record) {
                            $nombreProducto = optional($detalle->producto)->nombre ?: 'Producto eliminado';
                            $stock = \App\Models\StockAlmacen::where('idProducto', $detalle->idProducto)
                                ->where('idAlmacen', $record->idAlmacen)
                                ->first();
                            $stockInfo = $stock
                                ? "Costo Unitario: {$stock->costo_unitario} Bs | Precio Venta: {$stock->precio_venta} Bs"
                                : "Sin datos de stock";
                            $compraInfo = "{$detalle->cantidad} x {$detalle->costo_unitario} Bs = " . ($detalle->cantidad * $detalle->costo_unitario) . " Bs";
                            return "
                <div class='mb-2'>
                    <strong>{$nombreProducto}</strong><br>
                    <span class='text-sm text-gray-500'>{$stockInfo}<br>{$compraInfo}</span>
                </div>";
                        })->join('');
                        if ($detalles->count() > $mostrarHasta) {
                            $restantes = $detalles->count() - $mostrarHasta;
                            $contenido .= "<span class='text-xs text-blue-500 font-medium cursor-default mt-1 block'>+ {$restantes} más</span>";
                        }
                        return $contenido;
                    })
                    ->html()
                    ->extraAttributes([
                        'class' => 'whitespace-normal max-h-20 overflow-hidden align-top',
                    ])
                    ->tooltip(function ($record) {
                        $detalles = $record->detalles ?? collect();
                        if ($detalles->isEmpty()) {
                            return 'Sin productos';
                        }
                        return $detalles->map(function ($detalle) use ($record) {
                            $nombreProducto = optional($detalle->producto)->nombre ?: 'Producto eliminado';
                            $stock = \App\Models\StockAlmacen::where('idProducto', $detalle->idProducto)
                                ->where('idAlmacen', $record->idAlmacen)
                                ->first();
                            $stockInfo = $stock
                                ? "Costo Unitario: {$stock->costo_unitario} Bs, Precio Venta: {$stock->precio_venta} Bs"
                                : "Sin datos de stock";
                            return "{$nombreProducto} ({$detalle->cantidad} x {$detalle->costo_unitario} Bs, {$stockInfo})";
                        })->join('<br>');
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->money('BOB')
                    ->sortable()
                    ->label('Total')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('BOB'),
                    ]),
                Tables\Columns\TextColumn::make('detalles_count')
                    ->counts('detalles')
                    ->label('Items')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make(),
                    ]),
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Registrado por')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn($record) => $record->trashed() ? 'gray' : match ($record->estado) {
                        'completada' => 'success',
                        'pendiente' => 'warning',
                        'cancelada' => 'danger',
                        default => 'gray',
                    })
                    ->label('Estado')
                    ->formatStateUsing(fn($state, $record) => $record->trashed() ? 'Eliminado' : ucfirst($state)),
                Tables\Columns\TextColumn::make('fecha')
                    ->dateTime()
                    ->sortable()
                    ->label('Fecha'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->date()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'completada' => 'Completada',
                        'pendiente' => 'Pendiente',
                        'cancelada' => 'Cancelada',
                    ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')
                    ->label('Fecha de Registro')
                    ->date()
                    ->collapsible(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => (
                        $record->estado !== 'cancelada' &&
                        $record->estado === 'pendiente' && (
                            Auth::user()->hasRole('Super Admin') ||
                            Auth::user()->idAlmacen
                        )
                    )),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => (
                        in_array($record->estado, ['pendiente', 'cancelada'])
                    )),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => Auth::user()->hasRole('Super Admin')),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(CompraExporter::class)
                    ->label('Exportar Compras')
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
            'index' => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompra::route('/create'),
            'edit' => Pages\EditCompra::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
        if (!Auth::user()->hasRole('Super Admin')) {
            $query->where('idAlmacen', Auth::user()->idAlmacen);
        }
        return $query;
    }
}
