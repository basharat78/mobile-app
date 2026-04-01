<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierDocument extends Model
{
    protected $fillable = [
        'carrier_id',
        'type',
        'file_path',
        'status',
    ];
}
