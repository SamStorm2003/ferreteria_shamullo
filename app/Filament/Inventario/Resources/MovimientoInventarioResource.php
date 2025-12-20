<?php

namespace App\Filament\Inventario\Resources;

use App\Filament\Inventario\Resources\MovimientoInventarioResource\Pages;
use App\Filament\Inventario\Resources\MovimientoInventarioResource\RelationManagers;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Almacen;
use App\Models\StockAlmacen;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ExportAction;
use App\Filament\Exports\MovimientoInventarioExporter;


class MovimientoInventarioResource extends Resource
{
    protected static ?string $model = MovimientoInventario::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Movimientos de Inventario';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['tipo'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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

                Forms\Components\Select::make('idProducto')
                    ->label('Producto')
                    ->options(function ($get, $record) {
                        $idAlmacen = $get('idAlmacen') ?? ($record ? $record->idAlmacen : null);
                        if (!$idAlmacen) {
                            Notification::make()
                                ->title('Error')
                                ->body('No tienes un almacén asignado. No puedes seleccionar productos.')
                                ->danger()
                                ->send();
                            return [];
                        }
                        return Producto::whereHas('stockAlmacenes', function ($query) use ($idAlmacen) {
                            $query->where('idAlmacen', $idAlmacen);
                        })->pluck('nombre', 'idProducto');
                    })
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->disabled(function ($context, $record) {
                        return $context === 'edit' && $record && $record->tipo === 'entrada';
                    })
                    ->afterStateUpdated(function (callable $set, $state, $get) {
                        $idAlmacen = $get('idAlmacen');
                        if ($state && $idAlmacen) {
                            $stock = StockAlmacen::where('idProducto', $state)
                                ->where('idAlmacen', $idAlmacen)
                                ->orderBy('created_at', 'desc')
                                ->first();
                            $set('costo_unitario', $stock ? $stock->costo_unitario : 0);
                            $set('cantidad_disponible', $stock ? $stock->cantidad : 0);
                            $set('precio_venta', $stock ? $stock->precio_venta : 0);
                        } else {
                            $set('costo_unitario', 0);
                            $set('cantidad_disponible', 0);
                            $set('precio_venta', 0);
                        }
                    }),

                Forms\Components\Section::make('Información de Stock')
                    ->schema([
                        Forms\Components\Placeholder::make('cantidad_disponible')
                            ->label('Cantidad Disponible')
                            ->content(function ($get) {
                                return $get('cantidad_disponible') . ' unidades';
                            }),
                        Forms\Components\Placeholder::make('precio_venta')
                            ->label('Precio de Venta (Bs.)')
                            ->content(function ($get) {
                                return 'Bs. ' . number_format($get('precio_venta'), 2);
                            }),
                        Forms\Components\Placeholder::make('costo_unitario_display')
                            ->label('Costo Unitario (Bs.)')
                            ->content(function ($get) {
                                return 'Bs. ' . number_format($get('costo_unitario'), 2);
                            }),
                    ]),

                Forms\Components\Select::make('tipo')
                    ->label('Tipo de Movimiento')
                    ->required()
                    ->disabled(function ($context, $record) {
                        return $context === 'edit' && $record->tipo === 'entrada';
                    })
                    ->options([
                        'entrada' => 'Entrada',
                        'salida'  => 'Salida',
                        'ajuste'  => 'Ajuste',
                    ]),

                Forms\Components\TextInput::make('cantidad')
                    ->label('Cantidad')
                    ->numeric()
                    ->required()
                    ->disabled(function ($context, $record) {
                        return $context === 'edit' && $record->tipo === 'entrada';
                    })
                    ->minValue(fn($get) => $get('tipo') === 'ajuste' ? null : 1),

                Forms\Components\TextInput::make('costo_unitario')
                    ->label('Costo Unitario (Bs.)')
                    ->numeric()
                    ->prefix('Bs.')
                    ->disabled()
                    ->dehydrated(true)
                    ->default(0),

                Forms\Components\DateTimePicker::make('fecha')
                    ->label('Fecha')
                    ->required()
                    ->disabled(function ($context, $record) {
                        return $context === 'edit' && $record->tipo === 'entrada';
                    })
                    ->default(now()),

                Forms\Components\TextInput::make('usuario_name')
                    ->label('Responsable')
                    ->disabled()
                    ->dehydrated(false)
                    ->default(fn() => Auth::user()?->name)
                    ->formatStateUsing(fn($state, $record) => $record?->usuario?->name ?? Auth::user()?->name),

                Forms\Components\Hidden::make('idUsuario')
                    ->default(fn() => Auth::id()),

                Forms\Components\Textarea::make('motivo')
                    ->label('Motivo')
                    ->rows(3)
                    ->disabled(function ($context, $record) {
                        return $context === 'edit' && $record->tipo === 'entrada';
                    })
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('almacen.nombre')
                    ->label('Almacén')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'entrada' => 'success',
                        'salida' => 'danger',
                        'ajuste' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total Cantidad'),
                    ]),

                Tables\Columns\TextColumn::make('costo_unitario')
                    ->label('Costo Unitario (Bs.)')
                    ->money('BOB', true)
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total Costos'),
                    ]),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),

                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Responsable')
                    ->sortable()
                    ->searchable()
                    ->default('Sin responsable'),

                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->motivo),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo de Movimiento')
                    ->options([
                        'entrada' => 'Entrada',
                        'salida' => 'Salida',
                        'ajuste' => 'Ajuste',
                    ]),

                Tables\Filters\SelectFilter::make('idProducto')
                    ->label('Producto')
                    ->options(Producto::pluck('descripcion', 'idProducto'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('idAlmacen')
                    ->label('Almacén')
                    ->options(Almacen::pluck('nombre', 'idAlmacen'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('idUsuario')
                    ->label('Responsable')
                    ->options(User::pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['fecha_desde'], fn($query, $date) => $query->whereDate('fecha', '>=', $date))
                            ->when($data['fecha_hasta'], fn($query, $date) => $query->whereDate('fecha', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['fecha_desde']) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['fecha_desde'])->format('d/m/Y');
                        }
                        if ($data['fecha_hasta']) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['fecha_hasta'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function () {
                        return Auth::user()->hasRole('Super Admin') || Auth::user()->idAlmacen !== null;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function () {
                        return Auth::user()->hasRole('Super Admin');
                    })
                    ->before(function ($record) {
                        $stock = StockAlmacen::where('idProducto', $record->idProducto)
                            ->where('idAlmacen', $record->idAlmacen)
                            ->lockForUpdate()
                            ->first();
                        if ($stock) {
                            $newQuantity = match ($record->tipo) {
                                'entrada' => $stock->cantidad - $record->cantidad,
                                'salida' => $stock->cantidad + $record->cantidad,
                                'ajuste' => $stock->cantidad - $record->cantidad,
                                default => $stock->cantidad,
                            };
                            if ($newQuantity < 0) {
                                Notification::make()
                                    ->danger()
                                    ->title('Error')
                                    ->body('El stock no puede ser negativo.')
                                    ->send();
                                throw new \Exception('Stock cannot be negative.');
                            }
                            $stock->update(['cantidad' => $newQuantity]);
                        }
                    }),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')
                    ->label('Fecha de Registro')
                    ->date()
                    ->collapsible(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $stock = StockAlmacen::where('idProducto', $record->idProducto)
                                    ->where('idAlmacen', $record->idAlmacen)
                                    ->lockForUpdate()
                                    ->first();
                                if ($stock) {
                                    $newQuantity = match ($record->tipo) {
                                        'entrada' => $stock->cantidad - $record->cantidad,
                                        'salida' => $stock->cantidad + $record->cantidad,
                                        'ajuste' => $stock->cantidad - $record->cantidad,
                                        default => $stock->cantidad,
                                    };
                                    if ($newQuantity < 0) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Error')
                                            ->body('El stock no puede ser negativo.')
                                            ->send();
                                        throw new \Exception('Stock cannot be negative.');
                                    }
                                    $stock->update(['cantidad' => $newQuantity]);
                                }
                            }
                        }),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Movimientos')
                    ->exporter(MovimientoInventarioExporter::class),
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
            'index' => Pages\ListMovimientoInventarios::route('/'),
            'create' => Pages\CreateMovimientoInventario::route('/create'),
            'edit' => Pages\EditMovimientoInventario::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
