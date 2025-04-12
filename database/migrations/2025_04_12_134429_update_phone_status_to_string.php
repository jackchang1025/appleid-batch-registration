<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PhoneStatus;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    
        Schema::table('phones', function (Blueprint $table) {
            $table->string('status')->default(PhoneStatus::NORMAL)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phones', function (Blueprint $table) {
            $table->enum('status', ['normal', 'invalid', 'bound','Binding'])->default(PhoneStatus::NORMAL)->change();
        });
    }
};
