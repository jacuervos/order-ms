<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_type_wastes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_waste_id'); // Solo almacena el ID, sin relación foránea
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->decimal('weight', 10, 2);
            $table->integer('points');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('order_type_wastes');
    }
};
