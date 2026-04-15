<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadRequest extends Model
{
    protected $fillable = [
        'load_id',
        'carrier_id',
        'status',
        'is_notified',
    ];

    public function loadJob()
    {
        return $this->belongsTo(Load::class, 'load_id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }
}
