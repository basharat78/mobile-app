<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Load extends Model
{
    protected $fillable = [
        'id', // Allow cloud ID to be preserved during sync
        'dispatcher_id',
        'dispatcher_phone',
        'carrier_id',
        'pickup_location',
        'drop_location',
        'miles',
        'rate',
        'equipment_type',
        'notes',
        'pickup_time',
        'drop_off_time',
        'deadhead',
        'total_miles',
        'rpm',
        'weight',
        'broker_name',
        'status',
        'is_notified',
    ];

    protected static function booted()
    {
        static::saving(function ($load) {
            // Mirror dispatcher phone directly from the users table for 100% reliability
            if ($load->dispatcher_id && empty($load->dispatcher_phone)) {
                $load->dispatcher_phone = \App\Models\User::where('id', $load->dispatcher_id)->value('phone');
            }
        });
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }
    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function requests()
    {
        return $this->hasMany(LoadRequest::class);
    }
}
