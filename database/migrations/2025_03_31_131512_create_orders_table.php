<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Solo almacena el ID
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('date');
            $table->unsignedBigInteger('state_id'); // Solo almacena el ID
            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('order_type_wastes');
        Schema::dropIfExists('orders');
    }
};
