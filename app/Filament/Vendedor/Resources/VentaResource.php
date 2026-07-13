<?php

namespace App\Filament\Vendedor\Resources;

use App\Filament\Vendedor\Resources\VentaResource\Pages;
use App\Filament\Vendedor\Resources\VentaResource\RelationManagers;
use App\Models\Almacen;
use App\Models\ClienteExterno;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Promocion;
use App\Models\User;
use App\Models\Venta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use App\Filament\Exports\VentasExporter;
use App\Services\Facturacion\FacturaVentaService;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'Realizar Ventas';
    protected static ?string $pluralLabel = 'Ventas';
    protected static ?string $navigationGroup = 'Ventas';

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
                Forms\Components\Section::make('Información del Cliente')
                    ->schema([
                        Forms\Components\Placeholder::make('cliente_info')
                            ->label('Detalles del Cliente')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '';
                                }
                                if ($record->idUsuarioCliente) {
                                    $user = User::find($record->idUsuarioCliente);
                                    return $user ?
                                        "Nombre: {$user->name}\nTeléfono: " . ($user->telefono ?? 'No registrado') . "\nCorreo: " . ($user->email ?? 'No registrado')
                                        : 'Cliente registrado no encontrado';
                                }
                                if ($record->idClienteExterno) {
                                    $cliente = ClienteExterno::find($record->idClienteExterno);
                                    return $cliente ?
                                        "Nombre: {$cliente->nombre}\nTeléfono: " . ($cliente->telefono ?? 'No registrado') . "\nCorreo: " . ($cliente->correo ?? 'No registrado')
                                        : 'Cliente externo no encontrado';
                                }
                                return 'No se encontró información del cliente';
                            })
                            ->columnSpan('full'),
                    ])
                    ->visible(fn($record) => $record !== null)
                    ->collapsible(),
                Forms\Components\Select::make('cliente_type')
                    ->label('Tipo de Cliente')
                    ->options([
                        'registrado' => 'Cliente Registrado',
                        'externo' => 'Cliente Externo',
                    ])
                    ->reactive()
                    ->required()
                    ->default('registrado')
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('idUsuarioCliente', null);
                        $set('idClienteExterno', null);
                        $set('nombre_cliente_externo', null);
                        $set('documento_identidad_externo', null);
                        $set('telefono_externo', null);
                        $set('correo_externo', null);
                        $set('direccion_externo', null);
                        $set('apellido_faltante', false);
                        $set('telefono_faltante', false);
                        $set('direccion_faltante', false);
                        $set('ciudad_faltante', false);
                        $set('documento_identidad_faltante', false);
                        $set('fecha_nacimiento_faltante', false);
                    })
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['reserva', 'pendiente']);
                    })
                    ->visible(fn($record) => $record === null),

                Forms\Components\Select::make('idUsuarioCliente')
                    ->label('Cliente Registrado')
                    ->options(function () {
                        return User::where('estado', 'activo')
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => $user->name . ' (' . $user->email . ')'];
                            })->toArray();
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search) {
                        return User::where('estado', 'activo')
                            ->where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->limit(10)
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => $user->name . ' (' . $user->email . ')'];
                            })->toArray();
                    })
                    ->getOptionLabelUsing(function ($value) {
                        $user = User::find($value);
                        return $user ? $user->name . ' (' . $user->email . ')' : null;
                    })
                    ->visible(fn($get) => $get('cliente_type') === 'registrado')
                    ->required(fn($get) => $get('cliente_type') === 'registrado')
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $user = User::find($state);
                        if ($user) {
                            $set('apellido_faltante', !$user->apellido);
                            $set('telefono_faltante', !$user->telefono);
                            $set('direccion_faltante', !$user->direccion);
                            $set('ciudad_faltante', !$user->ciudad);
                            $set('documento_identidad_faltante', !$user->documento_identidad);
                            $set('fecha_nacimiento_faltante', !$user->fecha_nacimiento);
                        } else {
                            $set('apellido_faltante', false);
                            $set('telefono_faltante', false);
                            $set('direccion_faltante', false);
                            $set('ciudad_faltante', false);
                            $set('documento_identidad_faltante', false);
                            $set('fecha_nacimiento_faltante', false);
                        }
                    })
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['reserva', 'pendiente']);
                    }),

                Forms\Components\TextInput::make('apellido')
                    ->label('Apellido')
                    ->visible(fn($get) => $get('cliente_type') === 'registrado' && $get('apellido_faltante'))
                    ->maxLength(100),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono')
                    ->visible(fn($get) => $get('cliente_type') === 'registrado' && $get('telefono_faltante'))
                    ->maxLength(20)
                    ->tel(),
                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->visible(fn($get) => $get('cliente_type') === 'registrado' && $get('direccion_faltante'))
                    ->maxLength(255),
                Forms\Components\TextInput::make('ciudad')
                    ->label('Ciudad')
                    ->visible(fn($get) => $get('cliente_type') === 'registrado' && $get('ciudad_faltante'))
                    ->maxLength(100),
                Forms\Components\TextInput::make('documento_identidad')
                    ->label('Documento de Identidad')
                    ->visible(fn($get) => $get('cliente_type') === 'registrado' && $get('documento_identidad_faltante'))
                    ->maxLength(50),
                Forms\Components\DatePicker::make('fecha_nacimiento')
                    ->label('Fecha de Nacimiento')
                    ->visible(fn($get) => $get('cliente_type') === 'registrado' && $get('fecha_nacimiento_faltante')),

                Forms\Components\Hidden::make('idClienteExterno')
                    ->dehydrated(true),
                Forms\Components\Placeholder::make('buscar_documento_info')
                    ->label('')
                    ->content('Busque por número de identidad')
                    ->visible(fn($get) => $get('cliente_type') === 'externo'),
                Forms\Components\TextInput::make('documento_identidad_externo')
                    ->label('Documento de Identidad Externo')
                    ->visible(fn($get) => $get('cliente_type') === 'externo')
                    ->maxLength(50)
                    ->hint('Busque por número de CI del cliente')
                    ->reactive()
                    ->required(fn($get) => $get('cliente_type') === 'externo')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $cliente = ClienteExterno::where('documento_identidad', $state)->first();
                            if ($cliente) {
                                $set('idClienteExterno', $cliente->idClienteExterno);
                                $set('nombre_cliente_externo', $cliente->nombre);
                                $set('telefono_externo', $cliente->telefono);
                                $set('correo_externo', $cliente->correo);
                                $set('direccion_externo', $cliente->direccion);
                            } else {
                                $set('idClienteExterno', null);
                                $set('nombre_cliente_externo', null);
                                $set('telefono_externo', null);
                                $set('correo_externo', null);
                                $set('direccion_externo', null);
                            }
                        } else {
                            $set('idClienteExterno', null);
                            $set('nombre_cliente_externo', null);
                            $set('telefono_externo', null);
                            $set('correo_externo', null);
                            $set('direccion_externo', null);
                        }
                    }),
                Forms\Components\TextInput::make('nombre_cliente_externo')
                    ->label('Nombre del Cliente Externo')
                    ->required(fn($get) => $get('cliente_type') === 'externo')
                    ->visible(fn($get) => $get('cliente_type') === 'externo')
                    ->maxLength(100),
                Forms\Components\TextInput::make('telefono_externo')
                    ->label('Teléfono Externo')
                    ->visible(fn($get) => $get('cliente_type') === 'externo')
                    ->maxLength(20)
                    ->tel(),
                Forms\Components\TextInput::make('correo_externo')
                    ->label('Correo Externo')
                    ->visible(fn($get) => $get('cliente_type') === 'externo')
                    ->email()
                    ->maxLength(100),
                Forms\Components\TextInput::make('direccion_externo')
                    ->label('Dirección Externo')
                    ->visible(fn($get) => $get('cliente_type') === 'externo')
                    ->maxLength(255),

                Forms\Components\Repeater::make('detalles')
                    ->label('Detalles de la Venta')
                    ->relationship('detalles')
                    ->columnSpan('full')
                    ->addActionLabel('Agregar Producto')
                    ->schema([
                        Forms\Components\Select::make('idProducto')
                            ->label('Producto')
                            ->options(function ($get, $state, $component) {
                                $repeaterItems = $get('../../detalles') ?? [];
                                $existingProductIds = collect($repeaterItems)
                                    ->filter(fn($item, $key) => $key !== $component->getStatePath(true)) // Excluir el elemento actual
                                    ->pluck('idProducto')
                                    ->filter()
                                    ->toArray();

                                return Producto::where('estado', 'activo')
                                    ->whereNotIn('idProducto', $existingProductIds)
                                    ->whereExists(function ($query) {
                                        $query->select(DB::raw(1))
                                            ->from('stock_almacens')
                                            ->whereColumn('stock_almacens.idProducto', 'productos.idProducto')
                                            ->where('stock_almacens.cantidad', '>', 0)
                                            ->when(!Auth::user()->hasRole('Super Admin'), function ($query) {
                                                $query->where('stock_almacens.idAlmacen', Auth::user()->idAlmacen);
                                            });
                                    })
                                    ->pluck('nombre', 'idProducto')
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $producto = Producto::with('categoria')->find($state);
                                if ($producto) {
                                    $set('descripcion_producto', $producto->descripcion);
                                    $set('categoria_producto', $producto->categoria?->nombre ?? 'Sin categoría');
                                    $set('idAlmacen', Auth::user()->hasRole('Super Admin') ? null : Auth::user()->idAlmacen);
                                    $set('precio_unitario', null);
                                    $set('cantidad', null);
                                }
                            }),

                        Forms\Components\Placeholder::make('descripcion_producto')
                            ->label('Descripción')
                            ->content(function ($get, $record) {
                                $idProducto = $get('idProducto');
                                if ($idProducto && !$get('descripcion_producto')) {
                                    $producto = Producto::find($idProducto);
                                    return $producto->descripcion ?? '';
                                }
                                return $get('descripcion_producto') ?? '';
                            }),

                        Forms\Components\Placeholder::make('categoria_producto')
                            ->label('Categoría')
                            ->content(function ($get, $record) {
                                $idProducto = $get('idProducto');
                                if ($idProducto && !$get('categoria_producto')) {
                                    $producto = Producto::with('categoria')->find($idProducto);
                                    return $producto->categoria?->nombre ?? 'Sin categoría';
                                }
                                return $get('categoria_producto') ?? '';
                            }),

                        Forms\Components\Placeholder::make('almacen_nombre')
                            ->label('Almacén')
                            ->content(function ($get) {
                                if (!Auth::user()->hasRole('Super Admin')) {
                                    return Almacen::find(Auth::user()->idAlmacen)?->nombre ?? 'Sin almacén asignado';
                                }
                                return '';
                            })
                            ->visible(fn() => !Auth::user()->hasRole('Super Admin')),

                        Forms\Components\Hidden::make('idAlmacen')
                            ->default(fn() => Auth::user()->hasRole('Super Admin') ? null : Auth::user()->idAlmacen)
                            ->dehydrated(true)
                            ->required(fn() => !Auth::user()->hasRole('Super Admin'))
                            ->visible(fn() => !Auth::user()->hasRole('Super Admin')),

                        Forms\Components\Select::make('idAlmacen')
                            ->label('Almacén')
                            ->options(function ($get) {
                                $idProducto = $get('idProducto');
                                if ($idProducto && Auth::user()->hasRole('Super Admin')) {
                                    return StockAlmacen::where('idProducto', $idProducto)
                                        ->where('cantidad', '>', 0)
                                        ->with('almacen')
                                        ->get()
                                        ->mapWithKeys(function ($stock) {
                                            return [$stock->idAlmacen => $stock->almacen->nombre . ' (Stock: ' . $stock->cantidad . ')'];
                                        })
                                        ->toArray();
                                }
                                return [];
                            })
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, $get) {
                                $idProducto = $get('idProducto');
                                if ($idProducto && Auth::user()->hasRole('Super Admin')) {
                                    return StockAlmacen::where('idProducto', $idProducto)
                                        ->where('cantidad', '>', 0)
                                        ->with('almacen')
                                        ->whereHas('almacen', function ($query) use ($search) {
                                            $query->where('nombre', 'like', "%{$search}%");
                                        })
                                        ->get()
                                        ->mapWithKeys(function ($stock) {
                                            return [$stock->idAlmacen => $stock->almacen->nombre . ' (Stock: ' . $stock->cantidad . ')'];
                                        })
                                        ->toArray();
                                }
                                return [];
                            })
                            ->required(fn() => Auth::user()->hasRole('Super Admin'))
                            ->reactive()
                            ->visible(fn() => Auth::user()->hasRole('Super Admin'))
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $idProducto = $get('idProducto');
                                $idAlmacen = $state;
                                if ($idProducto && $idAlmacen) {
                                    $stock = StockAlmacen::where('idProducto', $idProducto)
                                        ->where('idAlmacen', $idAlmacen)
                                        ->first();
                                    if ($stock) {
                                        $precio = $stock->precio_venta;
                                        $promocion = Promocion::where('idProducto', $idProducto)
                                            ->where('estado', 'activa')
                                            ->where('fecha_inicio', '<=', now())
                                            ->where('fecha_fin', '>=', now())
                                            ->first();
                                        $precio = $promocion ? $precio * (1 - $promocion->descuento / 100) : $precio;
                                        $set('precio_unitario', $precio);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('cantidad')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(function ($get) {
                                $idProducto = $get('idProducto');
                                $idAlmacen = $get('idAlmacen');
                                if ($idProducto && $idAlmacen) {
                                    $stock = StockAlmacen::where('idProducto', $idProducto)
                                        ->where('idAlmacen', $idAlmacen)
                                        ->first();
                                    return $stock ? $stock->cantidad : 0;
                                }
                                return 0;
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $idProducto = $get('idProducto');
                                $idAlmacen = $get('idAlmacen');
                                if ($idProducto && $idAlmacen && $state) {
                                    $stock = StockAlmacen::where('idProducto', $idProducto)
                                        ->where('idAlmacen', $idAlmacen)
                                        ->first();
                                    if ($stock) {
                                        if ($state > $stock->cantidad) {
                                            Notification::make()
                                                ->title('Error')
                                                ->body('La cantidad ingresada (' . $state . ') supera el stock disponible (' . $stock->cantidad . ') en el almacén.')
                                                ->danger()
                                                ->send();
                                            $set('cantidad', $stock->cantidad);
                                        }
                                        $precio = $stock->precio_venta;
                                        $promocion = Promocion::where('idProducto', $idProducto)
                                            ->where('estado', 'activa')
                                            ->where('fecha_inicio', '<=', now())
                                            ->where('fecha_fin', '>=', now())
                                            ->first();
                                        $precio = $promocion ? $precio * (1 - $promocion->descuento / 100) : $precio;
                                        $set('precio_unitario', $precio);
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('precio_unitario')
                            ->label('Precio Unitario (Bs)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(4)
                    ->itemLabel(fn(array $state): ?string => $state['idProducto'] ? Producto::find($state['idProducto'])?->nombre : null)
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['reserva', 'pendiente', 'completada', 'cancelada']);
                    }),

                Forms\Components\Placeholder::make('total')
                    ->label('Total a Pagar (Bs)')
                    ->helperText('Por favor revise el total calculado automáticamente antes de guardar.')
                    ->hint('Este valor se calcula automáticamente.')
                    ->content(function ($get) {
                        $detalles = $get('detalles');
                        $total = 0;
                        foreach ($detalles as $detalle) {
                            $cantidad = (int) ($detalle['cantidad'] ?? 0);
                            $precio = (float) ($detalle['precio_unitario'] ?? 0);
                            $total += $cantidad * $precio;
                        }
                        return number_format($total, 2) . ' Bs';
                    }),

                Forms\Components\Repeater::make('pagos')
                    ->label('Pagos')
                    ->relationship('pagos')
                    ->columnSpan('full')
                    ->addActionLabel('Agregar Pago')
                    ->schema([
                        Forms\Components\Placeholder::make('monto')
                            ->label('Monto (Bs)')
                            ->content(function ($get, $record) {
                                if (!$record) return '—';
                                $venta = $record->venta;
                                $total = 0;
                                if ($venta && $venta->detalles) {
                                    foreach ($venta->detalles as $detalle) {
                                        $total += $detalle->cantidad * $detalle->precio_unitario;
                                    }
                                }

                                return number_format($total, 2) . ' Bs';
                            })
                            ->visible(fn($record) => $record !== null),
                        Forms\Components\Select::make('metodo')
                            ->label('Método de Pago')
                            ->options([
                                'tarjeta' => 'Tarjeta',
                                'efectivo' => 'Efectivo',
                                'online' => 'Online',
                                'transferencia' => 'Transferencia',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('referencia_pago')
                            ->label('Referencia de Pago')
                            ->maxLength(100),
                        Forms\Components\Select::make('estado')
                            ->label('Estado del Pago')
                            ->options([
                                'aprobado' => 'Aprobado',
                                'pendiente' => 'Pendiente',
                                'rechazado' => 'Rechazado',
                            ])
                            ->required()
                            ->default('aprobado'),
                    ])
                    ->columns(4)
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['reserva', 'pendiente', 'completada', 'cancelada']);
                    }),

                Forms\Components\Select::make('tipo_entrega')
                    ->label('Tipo de Entrega')
                    ->options([
                        'envio' => 'Envío',
                        'recogida' => 'Recogida en local',
                    ])
                    ->required()
                    ->reactive()
                    ->default('recogida')
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['reserva', 'pendiente', 'completada', 'cancelada']);
                    }),
                Forms\Components\Section::make('Detalles de Envío')
                    ->relationship('envio')
                    ->schema([
                        Forms\Components\TextInput::make('direccion_envio')
                            ->label('Dirección de Envío')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('metodo_envio')
                            ->label('Método de Envío')
                            ->options([
                                'estandar' => 'Estándar',
                                'express' => 'Express',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('numero_seguimiento')
                            ->label('Número de Seguimiento')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('fecha_entrega_estimada')
                            ->label('Fecha de Entrega Estimada'),
                    ])
                    ->visible(fn($get) => $get('tipo_entrega') === 'envio')
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['reserva', 'pendiente', 'completada', 'cancelada']);
                    })
                    ->collapsible(),

                Forms\Components\Select::make('estado')
                    ->label('Estado de la Venta')
                    ->options([
                        'completada' => 'Completada',
                    ])
                    ->required()
                    ->default('completada')
                    ->disabled(function ($record) {
                        return $record && in_array($record->estado, ['completada', 'cancelada']);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idVenta')
                    ->label('ID Venta')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->getStateUsing(function ($record) {
                        if ($record->clienteUsuario) {
                            return $record->clienteUsuario->name . ' (' . ($record->clienteUsuario->email ?? 'Sin correo') . ')';
                        }
                        if ($record->clienteExterno) {
                            return $record->clienteExterno->nombre . ' (' . ($record->clienteExterno->correo ?? 'Sin correo') . ')';
                        }
                        return 'Sin cliente';
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('cliente_contacto')
                    ->label('Contacto Cliente')
                    ->getStateUsing(function ($record) {
                        if ($record->clienteUsuario) {
                            return 'Tel: ' . ($record->clienteUsuario->telefono ?? 'N/A');
                        }
                        if ($record->clienteExterno) {
                            return 'Tel: ' . ($record->clienteExterno->telefono ?? 'N/A');
                        }
                        return 'N/A';
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BOB')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BOB')),
                Tables\Columns\TextColumn::make('detalles')
                    ->label('Productos')
                    ->getStateUsing(function ($record) {
                        $detalles = $record->detalles;

                        if ($detalles->isEmpty()) {
                            return 'Sin productos';
                        }

                        $primerDetalle = $detalles->first();
                        $producto = \App\Models\Producto::find($primerDetalle->idProducto);
                        $almacen = \App\Models\Almacen::find($primerDetalle->idAlmacen);

                        $textoPrimero = "{$producto?->nombre} (Cant: {$primerDetalle->cantidad}, Precio: " . number_format($primerDetalle->precio_unitario, 2) . " Bs, Almacén: {$almacen?->nombre})";
                        $otros = $detalles->slice(1);
                        $otrosTexto = $otros->count() ? " + {$otros->count()} más" : '';

                        return $textoPrimero . $otrosTexto;
                    })
                    ->tooltip(function ($record) {
                        return $record->detalles->map(function ($detalle) {
                            $producto = \App\Models\Producto::find($detalle->idProducto);
                            $almacen = \App\Models\Almacen::find($detalle->idAlmacen);

                            return "{$producto?->nombre} (Cant: {$detalle->cantidad}, Precio: " . number_format($detalle->precio_unitario, 2) . " Bs, Almacén: {$almacen?->nombre})";
                        })->join("\n");
                    })
                    ->wrap()
                    ->searchable()
                    ->extraAttributes([
                        'style' => 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 300px; max-width: 600px;',
                    ]),
                Tables\Columns\TextColumn::make('Pagos')
                    ->getStateUsing(fn($record) => $record->pagos->map(function ($pago) {
                        return number_format($pago->monto, 2) . ' Bs (' . ucfirst($pago->metodo) . ', ' . ucfirst($pago->estado) . ', Ref: ' . ($pago->referencia_pago ?? 'N/A') . ')';
                    }))
                    ->listWithLineBreaks()
                    ->limitList(1)
                    ->expandableLimitedList()
                    ->tooltip(
                        fn($record): string =>
                        $record->pagos->map(function ($pago) {
                            return number_format($pago->monto, 2) . ' Bs (' . ucfirst($pago->metodo) . ', ' . ucfirst($pago->estado) . ', Ref: ' . ($pago->referencia_pago ?? 'N/A') . ')';
                        })->join('<br>')
                    ),

                Tables\Columns\TextColumn::make('direccion_envio')
                    ->label('Dirección Envío')
                    ->getStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? 'Recogida' : ($record->envio?->direccion_envio ?? 'N/A'))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('metodo_envio')
                    ->label('Método Envío')
                    ->getStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? '—' : ucfirst($record->envio?->metodo_envio ?? 'N/A'))
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('numero_seguimiento')
                    ->label('Tracking')
                    ->getStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? '—' : ($record->envio?->numero_seguimiento ?? 'N/A'))
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('fecha_entrega_estimada')
                    ->label('Est. Entrega')
                    ->getStateUsing(function ($record) {
                        if ($record->tipo_entrega === 'recogida') return '—';
                        return $record->envio?->fecha_entrega_estimada
                            ? Carbon::parse($record->envio->fecha_entrega_estimada)->format('d/m/Y')
                            : 'N/A';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('estado_envio')
                    ->label('Estado Envío')
                    ->getStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? '—' : ucfirst($record->envio?->estado_envio ?? 'N/A'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pendiente' => 'warning',
                        'En camino' => 'info',
                        'Entregado' => 'success',
                        default => 'gray'
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->getStateUsing(function ($record) {
                        return $record->vendedor ? $record->vendedor->name . ' (' . ($record->vendedor->email ?? 'N/A') . ')' : 'Sin vendedor';
                    }),
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'completada' => 'success',
                        'pendiente' => 'warning',
                        'reservada' => 'info',
                        'cancelada' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'completada' => 'Completada',
                        'pendiente' => 'Pendiente',
                        'reservada' => 'Reservada',
                        'cancelada' => 'Cancelada',
                    ]),
                Tables\Filters\SelectFilter::make('tipo_entrega')
                    ->options([
                        'envio' => 'Envío',
                        'recogida' => 'Recogida',
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
                Tables\Actions\Action::make('generate_invoice')
                    ->label('Generar Factura')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn($record) => $record->estado === 'completada')
                    ->action(function ($record) {
                        return app(FacturaVentaService::class)->generarDesdeVenta($record);
                    })
                    ->visible(fn($record) => $record->estado === 'completada'),
                //Tables\Actions\EditAction::make()
                //    ->visible(fn() => Auth::user()->hasRole('Super Admin')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //   Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(VentasExporter::class)
                    ->label('Exportar Compras')
            ]);;
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
            'index' => Pages\ListVentas::route('/'),
            'create' => Pages\CreateVenta::route('/create'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
        ];
    }
}
