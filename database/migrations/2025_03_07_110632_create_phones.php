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
        Schema::create('phones', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('phone')->comment('手机号');
            $table->string('phone_address')->comment('手机号地址');
            $table->string('country_code')->comment('国家码')->default('US');
            $table->string('country_dial_code')->comment('区号')->default('+1');

            $table->enum('status', ['normal', 'invalid', 'bound','Binding'])->default('normal')->comment('{normal:正常,invalid:失效,bound:已绑定,Binding:绑定中}');

            // 创建一个复合唯一索引
            $table->unique(['phone', 'country_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phones');
    }
};
