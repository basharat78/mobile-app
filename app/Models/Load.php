<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Load extends Model
{
    protected $fillable = [
        'dispatcher_id',
        'pickup_location',
        'drop_location',
        'miles',
        'rate',
        'equipment_type',
        'notes',
        'status',
    ];

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }
    // public function requests()
    // {
    //     return $this->hasMany(Load::class);
    // }
}
