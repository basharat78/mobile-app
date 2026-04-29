<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;


class User extends Authenticatable implements FilamentUser
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'company_name',
        'fcm_token'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function carrier()
    {
        return $this->hasOne(Carrier::class);
    }

    public function managedCarriers()
    {
        return $this->hasMany(Carrier::class, 'dispatcher_id');
    }

    public function loads()
    {
        return $this->hasMany(Load::class, 'dispatcher_id');
    }

    public function isCarrier()
    {
        return $this->role === 'carrier';
    }

    public function isApproved()
    {
        if (!$this->isCarrier()) return true;
        return $this->carrier && $this->carrier->status === 'approved';
    }

    public function onboarded()
    {
        if (!$this->isCarrier()) return true;
        $carrier = $this->carrier;
        if (!$carrier) return false;

        return $carrier->hasMinimumDocuments() && $carrier->hasPreferences();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }
}
