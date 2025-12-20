<?php

namespace App\Filament\Vendedor\Resources;

use App\Filament\Vendedor\Resources\ClienteExternoResource\Pages;
use App\Filament\Vendedor\Resources\ClienteExternoResource\RelationManagers;
use App\Models\ClienteExterno;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class ClienteExternoResource extends Resource
{
    protected static ?string $model = ClienteExterno::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';
    protected static ?string $navigationLabel = 'Clientes Externos';
    protected static ?string $pluralLabel = 'Clientes Externos';
    protected static ?string $navigationGroup = 'Ventas';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'documento_identidad', 'telefono', 'correo', 'direccion'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('documento_identidad')
                    ->label('Documento de Identidad')
                    ->maxLength(50)
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (ClienteExterno::where('documento_identidad', $state)->exists()) {
                            Notification::make()
                                ->title('Este documento de identidad ya está registrado.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Este documento de identidad ya está registrado.',
                    ]),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\TextInput::make('correo')
                    ->label('Correo')
                    ->email()
                    ->maxLength(100),
                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('documento_identidad')
                    ->label('Documento de Identidad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('correo')
                    ->label('Correo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ventas_count')
                    ->label('Total Compras hechas')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn($record) => $record->ventas()->where('estado', 'completada')->count())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado_venta')
                    ->label('Estado de Venta')
                    ->options([
                        'completada' => 'Completada',
                        'pendiente' => 'Pendiente',
                        'cancelada' => 'Cancelada',
                    ])
                    ->query(fn(Builder $query, array $data) => $data['value']
                        ? $query->whereHas('ventas', fn(Builder $q) => $q->where('estado', $data['value']))
                        : $query),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListClienteExternos::route('/'),
            'create' => Pages\CreateClienteExterno::route('/create'),
            'edit' => Pages\EditClienteExterno::route('/{record}/edit'),
        ];
    }
}
