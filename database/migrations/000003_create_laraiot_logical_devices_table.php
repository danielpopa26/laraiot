<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laraiot_logical_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('physical_device_id')
                ->constrained('laraiot_physical_devices')
                ->restrictOnDelete();

            $table->foreignId('device_type_id')
                ->constrained('laraiot_device_types')
                ->restrictOnDelete();

            $table->string('identifier')->unique();
            $table->string('name');
            $table->json('last_value')->nullable();
            $table->string('unit')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laraiot_logical_devices');
    }
};
