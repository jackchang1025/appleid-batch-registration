<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appleids', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('email_uri');
            $table->string('phone');
            $table->string('phone_uri');
            $table->string('phone_country_code');
            $table->string('phone_country_dial_code');
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('country')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appleids');
    }
};
