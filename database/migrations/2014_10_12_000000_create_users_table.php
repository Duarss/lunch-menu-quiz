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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('barcode_num')->unique()->nullable();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role')->default('karyawan'); // admin, bm, karyawan, vendor
            $table->string('company_code')->nullable();
            $table->foreign('company_code')->references('code')->on('companies')->onDelete('cascade');
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
