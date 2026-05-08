<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    public $timestamps = false;

    protected $connection = null;

    public const ADMIN = 'Administrador';
    public const USER = 'Usuario';
    public const RECYCLER = 'Recolector';
}
