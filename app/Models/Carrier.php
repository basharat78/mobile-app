<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'preferred_origin',
        'preferred_destination',
        'preferred_equipment',
        'min_rate',
        'signature_path',
        'dispatcher_id',
    ];
}
