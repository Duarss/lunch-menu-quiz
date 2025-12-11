<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lunch_pickup_windows', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();
        });

        // Seed data intentionally omitted; pickup windows are configured per-date by the user.
    }

    public function down(): void
    {
        Schema::dropIfExists('lunch_pickup_windows');
    }
};
