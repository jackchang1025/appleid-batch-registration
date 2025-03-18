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
        Schema::table('appleids', function (Blueprint $table) {

            $table->string('phone')->nullable()->change();
            $table->string('phone_uri')->nullable()->change();
            $table->string('phone_country_code')->nullable()->change();
            $table->string('phone_country_dial_code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appleids', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
            $table->text('phone_uri')->nullable(false)->change();
            $table->string('phone_country_code')->nullable(false)->change();
            $table->string('phone_country_dial_code')->nullable(false)->change();
            
        });
    }
};
