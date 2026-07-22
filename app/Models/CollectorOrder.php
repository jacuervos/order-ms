<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorOrder extends Model
{
    protected $fillable = [
        'collector_id',
        'order_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
