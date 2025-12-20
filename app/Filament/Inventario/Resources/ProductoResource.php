<?php

namespace App\Filament\Inventario\Resources;

use App\Filament\Inventario\Resources\ProductoResource\Pages;
use App\Filament\Inventario\Resources\ProductoResource\RelationManagers;
use App\Models\Producto;
use App\Models\Categoria;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Repeater;
use App\Filament\Exports\ProductoExporter;
use Filament\Tables\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre del Producto')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $existingProduct = Producto::where('codigo', $state)->first();
                                    if ($existingProduct && $get('record_id') != $existingProduct->idProducto) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Código existente')
                                            ->body('El código ya está registrado para el producto: ' . $existingProduct->nombre . '. se actualizará el stock automaticamente.')
                                            ->warning()
                                            ->send();
                                        $set('existing_product_id', $existingProduct->idProducto);
                                    } else {
                                        $set('existing_product_id', null);
                                    }
                                }
                            })
                            ->disabled(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),

                        Forms\Components\TextInput::make('descripcion')
                            ->label('Descripción')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('idCategoria')
                            ->label('Categoría')
                            ->relationship('categoria', 'nombre')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre de la Categoría')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(Categoria::class, 'nombre'),
                            ])
                            ->createOptionAction(function (Action $action) {
                                return $action
                                    ->modalHeading('Crear nueva categoría')
                                    ->modalSubmitActionLabel('Crear categoría')
                                    ->modalWidth('lg');
                            }),

                        Forms\Components\TextInput::make('marca')
                            ->label('Marca')
                            ->maxLength(100)
                            ->required(),

                        Forms\Components\Placeholder::make('imagen_actual')
                            ->label('Imagen Actual')
                            ->content(function ($record) {
                                if ($record && $record->url_imagen) {
                                    return new HtmlString('<img src="' . $record->url_imagen . '" alt="Imagen actual" class="max-w-xs rounded-lg shadow">');
                                }
                                return 'Sin imagen disponible';
                            })
                            ->columnSpan('full')
                            ->visible(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),

                        Forms\Components\FileUpload::make('url_imagen')
                            ->label('Imagen del Producto')
                            ->disk('s3')
                            ->directory('productos')
                            ->image()
                            ->visibility('public')
                            ->maxSize(4096)
                            ->imageEditor()
                            ->imageEditorMode(2)
                            ->columnSpan('full')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                            ->maxFiles(1)
                            ->multiple(false)
                            ->nullable()
                            ->rules(['nullable', 'image', 'max:4096']),

                        // Forms\Components\TextInput::make('url_imagen')
                        //     ->label('URL de Imagen (opcional)')
                        //     ->url()
                        //     ->columnSpan('full')
                        //     ->nullable(),

                        Forms\Components\Select::make('idProveedor')
                            ->label('Proveedor')
                            ->relationship(
                                name: 'proveedor',
                                titleAttribute: 'nombre',
                                modifyQueryUsing: fn($query) => $query->where('estado', 'activo')
                            )
                            ->preload()
                            ->searchable()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre del Proveedor')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(\App\Models\Proveedor::class, 'nombre'),
                                Forms\Components\TextInput::make('contacto')
                                    ->label('Contacto')
                                    ->maxLength(100)
                                    ->required(),
                                Forms\Components\TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->maxLength(20)
                                    ->required()
                                    ->rules(['nullable', 'regex:/^[0-9]{7,20}$/']),
                                Forms\Components\TextInput::make('correo')
                                    ->label('Correo')
                                    ->email()
                                    ->maxLength(100)
                                    ->required()
                                    ->rules(['nullable', 'email']),
                                Forms\Components\TextInput::make('direccion')
                                    ->label('Dirección')
                                    ->maxLength(255)
                                    ->nullable(),
                                Forms\Components\Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'activo' => 'Activo',
                                        'inactivo' => 'Inactivo',
                                    ])
                                    ->default('activo')
                                    ->required(),
                            ])
                            ->createOptionAction(function (Action $action) {
                                return $action
                                    ->modalHeading('Crear nuevo proveedor')
                                    ->modalSubmitActionLabel('Crear proveedor')
                                    ->modalWidth('lg');
                            }),

                        Forms\Components\Section::make('Stock Inicial')
                            ->schema(function () {
                                if (Auth::user()->hasRole('Super Admin')) {
                                    return [
                                        Forms\Components\Select::make('idAlmacen')
                                            ->label('Almacén')
                                            ->options(fn() => \App\Models\Almacen::pluck('nombre', 'idAlmacen')->toArray())
                                            ->searchable()
                                            ->required()
                                            ->reactive()
                                            ->preload(),
                                        Forms\Components\TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('costo_unitario')
                                            ->label('Costo Unitario')
                                            ->numeric()
                                            ->required()
                                            ->prefix('Bs.')
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('precio_venta')
                                            ->label('Precio de Venta')
                                            ->numeric()
                                            ->required()
                                            ->prefix('Bs.')
                                            ->minValue(0),
                                    ];
                                } else {
                                    $idAlmacen = Auth::user()->idAlmacen;
                                    return [
                                        Forms\Components\Hidden::make('idAlmacen')
                                            ->default($idAlmacen)
                                            ->dehydrated(true),
                                        Forms\Components\Placeholder::make('almacen_nombre')
                                            ->label('Almacén')
                                            ->content(\App\Models\Almacen::find($idAlmacen)?->nombre ?? 'Sin almacén asignado'),
                                        Forms\Components\TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('costo_unitario')
                                            ->label('Costo Unitario')
                                            ->numeric()
                                            ->required()
                                            ->prefix('Bs.')
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('precio_venta')
                                            ->label('Precio de Venta')
                                            ->numeric()
                                            ->required()
                                            ->prefix('Bs.')
                                            ->minValue(0),
                                    ];
                                }
                            })
                            ->columns(2)
                            ->columnSpan('full'),
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'activo' => 'Activo',
                                'inactivo' => 'Inactivo',
                            ])
                            ->required()
                            ->default('activo'),
                    ])
                    ->columnSpan(['lg' => fn(?Producto $record) => $record === null ? 3 : 2]),
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Creado el')
                            ->content(fn(Producto $record): ?string => $record->created_at?->diffForHumans()),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Última modificación')
                            ->content(fn(Producto $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn(?Producto $record) => $record === null),
            ])
            ->columns([
                'sm' => 1,
                'md' => 2,
                'lg' => 3,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn() => Producto::with(['stockAlmacenes.almacen'])->withTrashed())
            ->columns([

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre del Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\ImageColumn::make('url_imagen')
                    ->label('Imagen')
                    ->square()
                    ->size(50)
                    ->sortable(false)
                    ->searchable(false),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('marca')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($record) => $record->trashed() ? 'gray' : ($record->estado === 'activo' ? 'success' : 'danger'))
                    ->formatStateUsing(fn($state, $record) => $record->trashed() ? 'Eliminado' : ($state === 'activo' ? 'Activo' : 'Inactivo'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('stockAlmacenes')
                    ->label('Stock en Almacenes')
                    ->formatStateUsing(function ($record) {
                        if ($record->stockAlmacenes->isEmpty()) {
                            return 'Sin Stock';
                        }
                        $stockCount = $record->stockAlmacenes->count();
                        if ($stockCount <= 2) {
                            return $record->stockAlmacenes->map(function ($stock) {
                                $almacen = $stock->almacen->nombre ?? 'Sin Almacén';
                                $ubicacion = $stock->almacen->ubicacion ?? 'Sin Ubicación';
                                $cantidad = $stock->cantidad ?? 0;
                                $costo = $stock->costo_unitario ?? 0;
                                $precio = $stock->precio_venta ?? 0;
                                return "<strong>{$almacen}</strong> ({$ubicacion}): {$cantidad} unidades<br>Costo: Bs. {$costo} | Venta: Bs. {$precio}";
                            })->implode('<hr>');
                        }
                        return "{$stockCount} almacenes";
                    })
                    ->tooltip(function ($record) {
                        if ($record->stockAlmacenes->isEmpty()) {
                            return 'Sin stock en almacenes.';
                        }
                        return $record->stockAlmacenes->map(function ($stock) {
                            $almacen = $stock->almacen->nombre ?? 'Sin Almacén';
                            $ubicacion = $stock->almacen->ubicacion ?? 'Sin Ubicación';
                            $cantidad = $stock->cantidad ?? 0;
                            $costo = $stock->costo_unitario ?? 0;
                            $precio = $stock->precio_venta ?? 0;
                            return "{$almacen} ({$ubicacion}): {$cantidad} unidades\nCosto: Bs. {$costo} | Venta: Bs. {$precio}";
                        })->implode("\n\n");
                    })
                    ->html()
                    ->sortable(false)
                    ->searchable(false)
                    ->wrap(),

                Tables\Columns\TextColumn::make('promociones')
                    ->label('Promociones')
                    ->getStateUsing(function ($record) {
                        $promociones = \App\Models\Promocion::where('idProducto', $record->idProducto)
                            ->where('estado', 'activa')
                            ->get();
                        if ($promociones->isEmpty()) {
                            return 'No tiene promoción';
                        }
                        return $promociones->map(function ($promo) {
                            $nombre = $promo->nombre;
                            $descuento = number_format($promo->descuento, 2) . '%';
                            $inicio = \Carbon\Carbon::parse($promo->fecha_inicio)->format('d/m/Y');
                            $fin = \Carbon\Carbon::parse($promo->fecha_fin)->format('d/m/Y');
                            return "{$nombre}: {$descuento} (Inicio: {$inicio}, Fin: {$fin})";
                        })->implode(' | ');
                    })
                    ->sortable(false)
                    ->searchable(false),

                Tables\Columns\TextColumn::make('fecha_ingreso')
                    ->label('Fecha de Ingreso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_actualizacion')
                    ->label('Fecha de Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),

                Tables\Filters\SelectFilter::make('idProveedor')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre'),

                Filter::make('fecha_ingreso')
                    ->label('Fecha de Ingreso')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_ingreso_from')->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_ingreso_until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['fecha_ingreso_from'], fn($q) => $q->whereDate('fecha_ingreso', '>=', $data['fecha_ingreso_from']))
                            ->when($data['fecha_ingreso_until'], fn($q) => $q->whereDate('fecha_ingreso', '<=', $data['fecha_ingreso_until']));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => Auth::user()->hasRole('Super Admin')),
                Tables\Actions\RestoreAction::make(),
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
                        ->visible(fn() => Auth::user()->hasRole('Super Admin')),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Productos')
                    ->exporter(ProductoExporter::class),
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
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
