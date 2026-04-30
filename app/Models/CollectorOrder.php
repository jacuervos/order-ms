<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorOrder extends Model
{
    protected $fillable = [
        'collector_id',
        'order_id',
    ];
}
