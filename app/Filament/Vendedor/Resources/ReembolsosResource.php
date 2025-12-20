<?php

namespace App\Filament\Vendedor\Resources;

use App\Filament\Vendedor\Resources\ReembolsosResource\Pages;
use App\Filament\Vendedor\Resources\ReembolsosResource\RelationManagers;
use App\Models\Reembolsos;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Venta;

class ReembolsosResource extends Resource
{
    protected static ?string $model = Reembolsos::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationGroup = 'Ventas';
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['idReembolso', 'venta.idVenta', 'monto', 'fecha', 'motivo', 'estado'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('idVenta')
                    ->label('Venta')
                    ->options(function () {
                        return Venta::query()
                            ->whereIn('estado', ['completada', 'pendiente'])
                            ->with('detalles.producto')
                            ->get()
                            ->mapWithKeys(function ($venta) {
                                $productos = $venta->detalles->map(function ($detalle) {
                                    return "{$detalle->producto->nombre} (Cant: {$detalle->cantidad})";
                                })->implode(', ');
                                return [$venta->idVenta => "Venta #{$venta->idVenta} - {$productos}"];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $venta = Venta::with('detalles.producto')->find($state);
                            $set('monto', $venta ? $venta->total : 0);
                            $productos = $venta && $venta->detalles
                                ? $venta->detalles->map(function ($detalle) {
                                    return "{$detalle->producto->nombre} (Cant: {$detalle->cantidad}, Almacén: {$detalle->almacen->nombre})";
                                })->implode(', ')
                                : 'Sin productos';
                            $set('productos_info', $productos);
                        }
                    }),
                Forms\Components\Placeholder::make('productos_info')
                    ->label('Productos de la Venta')
                    ->content(function (callable $get) {
                        return $get('productos_info') ?? 'Seleccione una venta para ver los productos';
                    }),
                Forms\Components\TextInput::make('monto')
                    ->label('Monto a reembolsar')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(function (callable $get) {
                        $venta = Venta::find($get('idVenta'));
                        return $venta ? $venta->total : 0;
                    })
                    ->suffix('Bs.')
                    ->reactive(),
                Forms\Components\Textarea::make('motivo')
                    ->label('Motivo del reembolso')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\Select::make('estado')
                    ->label('Estado')
                    ->options([
                        'aprobado' => 'Aprobado',
                    ])
                    ->default('aprobado')
                    ->required(),
                Forms\Components\Hidden::make('idUsuario')
                    ->default(auth()->id()),
                Forms\Components\DateTimePicker::make('fecha')
                    ->label('Fecha')
                    ->default(now())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idReembolso')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('venta.idVenta')
                    ->label('Venta')
                    ->formatStateUsing(fn($state) => "Venta #{$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('productos')
                    ->label('Productos Reembolsados')
                    ->getStateUsing(function ($record) {
                        $detalles = $record->venta->detalles()->with('producto')->get();
                        return $detalles->map(function ($detalle) {
                            return "{$detalle->producto->nombre} (Cant: {$detalle->cantidad}, Almacén: {$detalle->almacen->nombre})";
                        })->implode(', ');
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        $detalles = $record->venta->detalles()->with('producto')->get();
                        return $detalles->map(function ($detalle) {
                            return "{$detalle->producto->nombre} (Cant: {$detalle->cantidad}, Almacén: {$detalle->almacen->nombre})";
                        })->implode(', ');
                    }),
                Tables\Columns\TextColumn::make('monto')
                    ->label('Monto')
                    ->money('Bs.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(50),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'warning' => 'pendiente',
                        'success' => 'aprobado',
                        'danger' => 'rechazado',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Usuario')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                //Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListReembolsos::route('/'),
            'create' => Pages\CreateReembolsos::route('/create'),
            // 'edit' => Pages\EditReembolsos::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
