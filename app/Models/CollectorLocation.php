<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorLocation extends Model
{
    protected $fillable = [
        'collector_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
