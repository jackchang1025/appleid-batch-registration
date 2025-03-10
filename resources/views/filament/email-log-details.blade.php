<div class="space-y-4">
    <div>
        <h3 class="text-lg font-bold">日志信息</h3>
        <p>{{ $log->message }}</p>
    </div>

    <div>
        <h3 class="text-lg font-bold">创建时间</h3>
        <p>{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
    </div>

    <div>
        <h3 class="text-lg font-bold">关联邮箱</h3>
        <p>{{ $log->email }}</p>
    </div>

    @if(!empty($log->data))
        <div>
            <h3 class="text-lg font-bold">详细数据</h3>
            <div class="overflow-x-auto">
                <pre class="p-4  rounded-lg">{{ json_encode($log->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    @endif
</div> 