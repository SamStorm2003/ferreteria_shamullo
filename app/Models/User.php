<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasPanelShield, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'workos_id',
        'avatar',
        'password',
        'apellido',
        'telefono',
        'direccion',
        'ciudad',
        'documento_identidad',
        'fecha_nacimiento',
        'idAlmacen',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'idAlmacen');
    }

    public function ventas()
    {
        return $this->hasMany(\App\Models\Venta::class, 'idUsuarioCliente', 'id');
    }

    protected static function booted(): void
    {
        if (config('filament-shield.panel_user.enabled', false)) {
            static::created(function ($user) {
                try {
                    $roleName = config('filament-shield.panel_user.name', 'panel_user');
                    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                    if (!$user->hasRole($roleName)) {
                        $user->assignRole($roleName);
                    }
                    if (!$user->password || !$user->email_verified_at) {
                        $user->password = $user->password ?: bcrypt('default_password');
                        $user->email_verified_at = $user->email_verified_at ?: now();
                        $user->save();
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to assign panel_user role', [
                        'error' => $e->getMessage(),
                        'user' => $user->toArray(),
                    ]);
                }
            });

            static::deleting(function ($user) {
                try {
                    $roleName = config('filament-shield.panel_user.name', 'panel_user');
                    $user->removeRole($roleName);
                } catch (\Exception $e) {
                    Log::error('Failed to remove panel_user role', [
                        'error' => $e->getMessage(),
                        'user' => $user->toArray(),
                    ]);
                }
            });
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->estado !== 'activo') {
            return false;
        }
        if ($this->hasRole('Super Admin')) {
            return true;
        }
        return match ($panel->getId()) {
            'admin' => $this->hasRole('Super Admin'),
            'vendedor' => $this->hasRole('Vendedor'),
            'inventario' => $this->hasRole('Inventario'),
            default => false,
        };
    }
}
