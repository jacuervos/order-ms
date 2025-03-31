<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('collector_orders', function (Blueprint $table) {
            $table->foreignId('collector_id')->constrained('collectors');
            $table->foreignId('order_id')->constrained('orders');
            $table->primary(['collector_id', 'order_id']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('collector_orders');
    }
};
