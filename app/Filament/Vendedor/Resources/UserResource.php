<?php

namespace App\Filament\Vendedor\Resources;

use App\Filament\Vendedor\Resources\UserResource\Pages;
use App\Filament\Vendedor\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Clientes Registrados';
    protected static ?string $pluralLabel = 'Clientes Registrados';
    protected static ?string $navigationGroup = 'Ventas';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereNotIn('id', function ($subquery) {
            $subquery->select('model_id')
                ->from('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', ['Inventario', 'Vendedor', 'Super Admin'])
                ->where('model_has_roles.model_type', \App\Models\User::class);
        })->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'documento_identidad', 'telefono', 'email', 'direccion'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required(),

                Forms\Components\TextInput::make('apellido')
                    ->label('Apellido'),

                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (User::where('email', $state)->exists()) {
                            Notification::make()
                                ->title('Este correo ya está registrado.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Este correo electrónico ya está registrado.',
                    ]),
                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono'),

                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección'),

                Forms\Components\TextInput::make('ciudad')
                    ->label('Ciudad'),

                Forms\Components\TextInput::make('documento_identidad')
                    ->label('C.I.'),

                Forms\Components\DatePicker::make('fecha_nacimiento')
                    ->label('Fecha de nacimiento'),

                Forms\Components\Select::make('estado')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ])
                    ->required()
                    ->default('activo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('apellido')
                    ->label('Apellido')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('documento_identidad')
                    ->label('C.I.')
                    ->searchable(),

                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección'),

                Tables\Columns\TextColumn::make('ciudad')
                    ->label('Ciudad'),

                Tables\Columns\TextColumn::make('fecha_nacimiento')
                    ->label('Fecha de Nacimiento')
                    ->date(),

                Tables\Columns\TextColumn::make('ventas_count')
                    ->label('Total Compras hechas')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn($record) => $record->ventas()->where('estado', 'completada')->count())
                    ->sortable(),

                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn($record) => $record->estado)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
