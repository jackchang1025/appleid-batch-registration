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
        Schema::create('proxy_ip_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('ip_uri')->nullable()->comment('代理服务提供者提供的IP');
            $table->ipAddress('real_ip')->nullable()->comment('连接代理后的真实IP');
            $table->string('proxy_provider')->nullable()->comment('代理服务提供者');
            $table->string('country_code')->nullable()->comment('真实IP国家代码');
            $table->text('exception_message')->nullable()->comment('异常信息');
            $table->boolean('is_success')->default(false)->comment('本次注册是否成功');
            $table->json('ip_info')->nullable()->comment('IP信息');
            $table->foreignId('email_id')->nullable()->constrained('emails')->onDelete('set null')->comment('关联的邮箱ID');
            $table->timestamps();

            $table->index('ip_uri');
            $table->index('real_ip');
            $table->index('country_code');
            $table->index('is_success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxy_ip_statistics');
    }
};
