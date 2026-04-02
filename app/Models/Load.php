<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Load extends Model
{
    protected $fillable = [
        'dispatcher_id',
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
    ];

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
