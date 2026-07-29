<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laraiot_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();

            $table->foreignId('logical_device_id')
                ->nullable()
                ->constrained('laraiot_logical_devices')
                ->nullOnDelete();

            $table->foreignId('mqtt_topic_id')
                ->nullable()
                ->constrained('laraiot_mqtt_topics')
                ->nullOnDelete();

            $table->nullableMorphs('actor');

            $table->string('title');
            $table->text('description')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('happened_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laraiot_activity_logs');
    }
};
