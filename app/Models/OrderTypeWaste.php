<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderTypeWaste extends Model
{
    protected $fillable = [
        'type_waste_id',
        'order_id',
        'weight',
        'points',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
