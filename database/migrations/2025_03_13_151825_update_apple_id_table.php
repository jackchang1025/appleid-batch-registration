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

            //将 email_uri phone_uri 字段改为 text
            $table->text('email_uri')->change();
            $table->text('phone_uri')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appleids', function (Blueprint $table) {
            $table->string('email_uri')->change();
            $table->string('phone_uri')->change();
        });
    }
};
