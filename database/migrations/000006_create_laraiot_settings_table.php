<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laraiot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('application_mode', 20)
                ->default('polling');
            $table->unsignedSmallInteger('polling_interval')
                ->default(10);
            $table->string('timezone', 100)
                ->default('UTC');
            $table->string('date_format', 30)
                ->default('d M Y');
            $table->string('time_format', 30)
                ->default('H:i:s');
            $table->timestamps();
        });

        DB::table('laraiot_settings')->insert([
            'id' => 1,
            'application_mode' => 'polling',
            'polling_interval' => 10,
            'timezone' => 'UTC',
            'date_format' => 'd M Y',
            'time_format' => 'H:i:s',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('laraiot_settings');
    }
};
