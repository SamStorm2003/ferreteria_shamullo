<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\Filter;
use App\Filament\Exports\UserExporter;
use Filament\Tables\Actions\ExportAction;
use Filament\Forms\Components\TextInput;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'telefono', 'direccion', 'ciudad', 'documento_identidad'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100)
                    ->unique(User::class, 'name', ignoreRecord: true),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->label('Contraseña')
                    ->minLength(8)
                    ->maxLength(255)
                    ->autocomplete('off')
                    ->extraAttributes(['autocomplete' => 'off'])
                    ->dehydrateStateUsing(fn($state) => filled($state) ? $state : null)
                    ->required(fn(Forms\Get $get) => $get('id') === null)
                    ->visible(fn(Forms\Get $get) => $get('id') === null),
                Forms\Components\TextInput::make('new_password')
                    ->password()
                    ->label('Nueva Contraseña')
                    ->minLength(8)
                    ->maxLength(255)
                    ->autocomplete('off')
                    ->extraAttributes(['autocomplete' => 'off'])
                    ->dehydrateStateUsing(fn($state) => filled($state) ? $state : null)
                    ->required(false)
                    ->visible(fn(Forms\Get $get) => $get('id') !== null)
                    ->placeholder('Dejar en blanco para mantener la contraseña actual')
                    ->helperText('Solo escribe aquí si deseas cambiar la contraseña.'),
                Forms\Components\TextInput::make('apellido')
                    ->label('Apellido')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('telefono')
                    ->label('Telefono')
                    ->maxLength(20)
                    ->required()
                    ->unique(User::class, 'telefono', ignoreRecord: true),
                Forms\Components\TextInput::make('direccion')
                    ->label('Direccion')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ciudad')
                    ->label('Ciudad')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('documento_identidad')
                    ->label('Documento Identidad')
                    ->maxLength(50)
                    ->required()
                    ->unique(User::class, 'documento_identidad', ignoreRecord: true),
                Forms\Components\DatePicker::make('fecha_nacimiento')
                    ->label('Fecha Nacimiento')
                    ->maxDate(now())
                    ->required(fn(Forms\Get $get) => $get('id') === null),
                Forms\Components\CheckboxList::make('roles')
                    ->relationship('roles', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('estado')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ])
                    ->default('activo')
                    ->required(),
                Forms\Components\Select::make('idAlmacen')
                    ->label('Almacén')
                    ->nullable()
                    ->options(function () {
                        return \App\Models\Almacen::query()
                            ->orderBy('nombre')
                            ->pluck('nombre', 'idAlmacen')
                            ->toArray();
                    })
                    ->searchable()
                    ->placeholder('Selecciona un almacén'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->colors([
                        'gray' => 'panel_user',
                        'primary' => 'Vendedor',
                        'warning' => 'Inventario',
                        'success' => 'Super Admin',
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('almacen.nombre')
                    ->label('Almacén')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('telefono')
                    ->label(' Telefono')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirreccion')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ciudad')
                    ->label('Ciudad')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('documento_identidad')
                    ->label('Documento Identidad')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('fecha_nacimiento')
                    ->label('Fecha Nacimiento')
                    ->date()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->size(40),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'inactivo',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_status')
                    ->label('Estado Eliminación')
                    ->getStateUsing(fn($record) => $record->deleted_at ? 'Eliminado' : 'Activo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Eliminado' => 'danger',
                        default => 'success',
                    })
                    ->sortable(),
            ])
            ->filters([
                Filter::make('con_telefono')
                    ->label('Con Teléfono')
                    ->query(fn(Builder $query) => $query->whereNotNull('telefono')),

                Filter::make('sin_telefono')
                    ->label('Sin Teléfono')
                    ->query(fn(Builder $query) => $query->whereNull('telefono')),

                Filter::make('de_la_paz')
                    ->label('De La Paz')
                    ->query(fn(Builder $query) => $query->where('ciudad', 'La Paz')),
                Filter::make('Eliminados')
                    ->query(fn(Builder $query) => $query->onlyTrashed())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        $record->update(['estado' => 'inactivo']);
                    }),
                Tables\Actions\RestoreAction::make()
                    ->before(function ($record) {
                        $record->update(['estado' => 'activo']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Usuarios')
                    ->exporter(UserExporter::class),
            ])
            ->defaultSort('id', 'asc');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
