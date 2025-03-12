<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AppleClientIdService
{
    /**
     * Node.js 路径
     */
    protected string $nodePath;

    /**
     * 原始 X-Apple-I-FD-Client-Info.js 文件路径
     */
    protected string $appleJsPath;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->nodePath = env('NODE_PATH', '/usr/bin/node');
        $this->appleJsPath = resource_path('js/X-Apple-I-FD-Client-Info.js');
    }

    /**
     * 获取 Apple 客户端 ID
     *
     * @param array $browserInfo 浏览器环境信息
     * @return array 包含 clientId 和完整数据的数组
     * @throws \Exception
     */
    public function getClientId(array $browserInfo = []): array
    {
        try {
            // 设置默认浏览器信息
            $defaultBrowserInfo = [
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'language' => 'zh-CN',
                'timeZone' => 'GMT+08:00',
                'plugins' => [],
            ];

            // 合并默认与传入的浏览器信息
            $mergedBrowserInfo = array_merge($defaultBrowserInfo, $browserInfo);

            // 创建临时 JavaScript 文件
            $tempJs = tempnam(sys_get_temp_dir(), 'js_');

            // 生成 JavaScript 代码
            $jsCode = $this->generateJavaScript($mergedBrowserInfo);

            // 写入临时文件
            file_put_contents($tempJs, $jsCode);

            // 执行 Node.js 进程
            $process = new Process([
                $this->nodePath,
                $tempJs
            ]);

            $process->run();

            // 删除临时文件
            @unlink($tempJs);

            // 检查进程是否成功执行
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // 获取输出并解析 JSON
            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('无法解析 Node.js 脚本的输出: ' . json_last_error_msg());
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('获取 Apple 客户端 ID 失败', [
                'error' => $e->getMessage(),
                'browserInfo' => $browserInfo,
            ]);

            throw $e;
        }
    }

    /**
     * 生成用于获取客户端 ID 的 JavaScript 代码
     *
     * @param array $browserInfo 浏览器环境信息
     * @return string JavaScript 代码
     * @throws \JsonException
     */
    protected function generateJavaScript(array $browserInfo): string
    {
        $userAgent = addslashes($browserInfo['userAgent']);
        $language = addslashes($browserInfo['language']);
        $timeZone = addslashes($browserInfo['timeZone']);


        // 构建插件数组
        $pluginsJson = !empty($browserInfo['plugins']) ? json_encode($browserInfo['plugins'], JSON_THROW_ON_ERROR) : '[]';

        return <<<JS
const fs = require("fs");
const vm = require("vm");

// 创建浏览器环境
const context = {
    window: {},
    navigator: {
        userAgent: "{$userAgent}",
        language: "{$language}",
        plugins: {
            length: 0
        },
        product: "Gecko"
    },
    document: {
        createElement: () => ({
            innerHTML: '',
            style: {},
            appendChild: () => {}
        }),
        forms: [],
        getElementById: () => null,
        getElementsByName: () => [],
        body: {
            appendChild: () => {},
            removeChild: () => {}
        }
    },
    ActiveXObject: function() {
        return {
            getVariable: () => "",
            ShockwaveVersion: () => ""
        };
    },
    console: console,
    RegExp: RegExp,
    Date: Date,
    Math: Math,
    JSON: JSON,
    escape: escape,
    unescape: unescape
};

// 设置全局对象
context.window = context;

// 处理插件
const plugins = {$pluginsJson};
if (plugins && plugins.length) {
    context.navigator.plugins = { length: plugins.length };
    plugins.forEach((plugin, index) => {
        context.navigator.plugins[index] = {
            name: plugin.name || '',
            description: plugin.description || ''
        };
    });
}

// 初始化 tmp 对象
context.tmp = {
    "U": context.navigator.userAgent,
    "L": context.navigator.language,
    "Z": "{$timeZone}",
    "V": "1.1"
};

// 读取 Apple JS 文件
const jsCode = fs.readFileSync("{$this->appleJsPath}", "utf8");

// 在上下文中执行 Apple JS
vm.createContext(context);
vm.runInContext(jsCode, context);

// 获取 client_id 和 fullData
const clientId = context.r ? context.r() : (context.getClientId ? context.getClientId().clientId : Date.now().toString());
const fullData = context.get_data(clientId);

// 输出结果
console.log(JSON.stringify({
    clientId: clientId,
    fullData: fullData
}));
JS;
    }

    /**
     * 自定义 Node.js 路径和 Apple JS 文件路径
     *
     * @param string|null $nodePath Node.js 路径
     * @param string|null $appleJsPath Apple JS 文件路径
     * @return static
     */
    public function config(?string $nodePath = null, ?string $appleJsPath = null):static
    {
        if ($nodePath !== null) {
            $this->nodePath = $nodePath;
        }

        if ($appleJsPath !== null) {
            $this->appleJsPath = $appleJsPath;
        }

        return $this;
    }
}
