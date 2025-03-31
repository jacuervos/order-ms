<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('collectors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Solo almacena el ID
            $table->unsignedBigInteger('state_id'); // Solo almacena el ID
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('collectors');
    }
};

