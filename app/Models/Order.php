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
        'service_started_at',
    ];

    public function orderTypeWastes()
    {
        return $this->hasMany(OrderTypeWaste::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function collectorOrder()
    {
        return $this->hasOne(CollectorOrder::class, 'order_id');
    }
}
