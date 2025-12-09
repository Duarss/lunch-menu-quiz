<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // Unique month-based week code, e.g. "2025-11-W3" (week_in_month resets each month)
            $table->string('code')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedTinyInteger('week_in_month'); // 1-5 (some months spill into 5th/6th)
            // Composite uniqueness as an extra guard (code already unique but makes querying easier)
            $table->unique(['year','month','week_in_month']);
            $table->index(['year','month']);
            $table->timestamp("exported_at")->nullable();
            $table->string("exported_by")->nullable();
            $table->foreign("exported_by")->references("username")->on("users")->onDelete("set null");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports');
    }
};
