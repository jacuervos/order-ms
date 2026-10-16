<?php

namespace Tests\Feature\Support;

use App\Models\State;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesOrderMsSchema
{
    protected function prepareOrderMsSchema(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        Schema::dropAllTables();

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('date')->nullable();
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_type_wastes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('type_waste_id');
            $table->decimal('weight', 8, 2)->default(0);
            $table->integer('points')->default(0);
            $table->timestamps();
        });

        Schema::create('collector_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collector_id');
            $table->unsignedBigInteger('order_id');
            $table->timestamps();
        });

        Schema::create('collector_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collector_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });

        DB::table('states')->insert([
            ['name' => State::ORDER_CREATION, 'color' => 'blue'],
            ['name' => State::ASSIGNED_TO_COLLECTOR, 'color' => 'purple'],
            ['name' => State::SERVICE_START, 'color' => 'orange'],
            ['name' => State::PICKED_UP, 'color' => 'teal'],
            ['name' => State::ORDER_COMPLETED, 'color' => 'green'],
            ['name' => State::CANCEL, 'color' => 'red'],
            ['name' => State::RESCHEDULED, 'color' => 'yellow'],
        ]);
    }

    protected function buildFakeJwt(int $userId, string $role = 'Usuario'): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $userId, 'user_id' => $userId, 'role' => $role])), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode('test-signature'), '+/', '-_'), '=');

        return "{$header}.{$payload}.{$signature}";
    }
}

