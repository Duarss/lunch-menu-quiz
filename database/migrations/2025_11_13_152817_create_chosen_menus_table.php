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
        Schema::create('chosen_menus', function (Blueprint $table) {
            $table->id();
            $table->string('menu_code');
            $table->foreign('menu_code')->references('code')->on('menus')->onDelete('cascade');
            $table->unsignedBigInteger('chosen_by');
            $table->foreign('chosen_by')->references('id')->on('users')->onDelete('cascade');
            $table->date('chosen_for_day');
            $table->boolean("is_locked")->default(false);
            $table->timestamp('chosen_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            // Prevent duplicate selection per user per day
            $table->unique(['chosen_by', 'chosen_for_day'], 'chosen_menus_user_day_unique');
            // Speed up report queries (Mon-Thu ranges and lock state)
            $table->index('chosen_for_day', 'chosen_menus_day_index');
            $table->index(['chosen_for_day', 'is_locked'], 'chosen_menus_day_locked_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chosen_menus');
    }
};
