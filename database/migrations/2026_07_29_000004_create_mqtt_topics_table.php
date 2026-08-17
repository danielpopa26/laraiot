<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laraiot_mqtt_topics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('logical_device_id')
                ->constrained('laraiot_logical_devices')
                ->cascadeOnDelete();

            $table->string('purpose');
            $table->string('topic');
            $table->json('payload_mapping')->nullable();
            $table->unsignedTinyInteger('qos')->default(0);
            $table->boolean('retain')->default(false);
            $table->boolean('is_enabled')->default(true);

            $table->longText('last_payload')->nullable();
            $table->json('last_value')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laraiot_mqtt_topics');
    }
};
