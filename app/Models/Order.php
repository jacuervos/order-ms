<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'date',
        'state_id',
    ];

    public function orderTypeWastes()
    {
        return $this->hasMany(OrderTypeWaste::class);
    }
}
