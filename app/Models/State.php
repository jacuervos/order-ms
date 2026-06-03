<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{

    protected $fillable = [
        'name',
        'color',
    ];

    //User
    const ORDER_CREATION = 'Creación de orden';
    const ASSIGNED_TO_COLLECTOR = 'Asignada a recolector';
    const SERVICE_START = 'Inicio de servicio';
    const PICKED_UP = 'Recogido';
    const ORDER_COMPLETED = 'Orden finalizada';

    const CANCEL = 'Cancelada';

    public function orders(){
        return $this->hasMany(Order::class);
    }
}
