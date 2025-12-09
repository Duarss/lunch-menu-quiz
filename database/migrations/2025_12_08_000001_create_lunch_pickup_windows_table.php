<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lunch_pickup_windows', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week')->unique();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
        });

        DB::table('lunch_pickup_windows')->insert([
            ['day_of_week' => 'monday', 'start_time' => null, 'end_time' => null, 'created_at' => now(), 'updated_at' => now()],
            ['day_of_week' => 'tuesday', 'start_time' => null, 'end_time' => null, 'created_at' => now(), 'updated_at' => now()],
            ['day_of_week' => 'wednesday', 'start_time' => null, 'end_time' => null, 'created_at' => now(), 'updated_at' => now()],
            ['day_of_week' => 'thursday', 'start_time' => null, 'end_time' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lunch_pickup_windows');
    }
};
