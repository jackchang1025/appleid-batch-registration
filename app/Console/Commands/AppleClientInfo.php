<?php

namespace App\Console\Commands;

use App\Services\AppleClientIdService;
use Illuminate\Console\Command;
use App\Services\AppleId\AppleIdBatchRegistration;
class AppleClientInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apple-client-info
                            {--user-agent= : 自定义用户代理}
                            {--language= : 浏览器语言}
                            {--time-zone= : 时区}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成 Apple 客户端 ID 信息';

    /**
     * AppleClientIdService 实例
     */
    protected AppleClientIdService $clientIdService;

    /**
     * 构造函数
     */
    public function __construct(AppleClientIdService $clientIdService)
    {
        parent::__construct();
        $this->clientIdService = $clientIdService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('正在生成 Apple 客户端 ID...');

        $language = AppleIdBatchRegistration::countryTimeZoneIdentifiers('CAN');
        $xAppleITimeZone = AppleIdBatchRegistration::getCountryTimezone('CAN');

        //{"U":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36","L":"en-CA","Z":"GMT-06:00","V":"1.1","F":"kla44j1e3NlY5BNlY5BSs5uQ32SCVgcHmkxF91.1Qs8QkmbFVDJhCixGMuJjkW5BRhkeNH0VdIcJb9WJQSwEOyPKz13NlY5BNp55BNlan0Os5Apw.AZ7"}
        // 从命令行参数获取浏览器信息
        $browserInfo = [
            'userAgent' => $this->option('user-agent') ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'language' => $this->option('language') ?:  $language,
            'timeZone' => $this->option('time-zone') ?: $xAppleITimeZone,
            'plugins' => []
        ];

        // dd($browserInfo,$language,$xAppleITimeZone);

        try {
            // 使用服务类获取客户端 ID
            $result = $this->clientIdService->getClientId($browserInfo);

            // 输出结果
            $this->info('Client ID: ' . $result['clientId']);
            $this->info('Full Data: ' . $result['fullData']);

        } catch (\Exception $e) {
            $this->error('获取 Apple 客户端 ID 失败: ' . $e->getMessage());
        }
    }
}
