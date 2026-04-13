<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    protected $fillable = [
        'user_id',
        'remote_id',
        'status',
        'preferred_origin',
        'preferred_destination',
        'preferred_equipment',
        'min_rate',
        'signature_path',
        'dispatcher_id',
    ];

    public function managedCarriers()
    {
        return $this->hasMany(Carrier::class, 'dispatcher_id');
    }

    public function loads()
    {
        return $this->hasMany(Load::class, 'dispatcher_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(CarrierDocument::class);
    }

    public function loadRequests()
    {
        return $this->hasMany(LoadRequest::class);
    }

    public function hasMinimumDocuments()
    {
        $types = $this->documents->pluck('type')->toArray();
        return in_array('mc_authority', $types) &&
            in_array('insurance', $types) &&
            in_array('w9', $types);
    }

    public function hasPreferences()
    {
        return !empty($this->preferred_equipment) && !empty($this->preferred_origin);
    }
}
