@php
    // 获取邮箱对象的所有日志
    $logs = $getRecord()->logs()->orderBy('id', 'desc')->get();
@endphp

<div class="space-y-6">
    @if($logs->isEmpty())
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-center">
            <p class="text-gray-500 dark:text-gray-400">暂无日志记录</p>
        </div>
    @else
        @foreach($logs as $index => $log)
            <div class="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 relative">
 
                
                <!-- 日志内容 -->
                <div class="px-4 py-4 sm:px-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="space-y-5">
                        <!-- 日志消息 -->
                        <div>
                            
                            <div class="pl-5 border-l-2 border-gray-200 dark:border-gray-700">
                                <div class="text-sm text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-900 p-3 rounded">
                                    {{ $log->message }} {{ $log->created_at}}
                                </div>
                            </div>
                        </div>
                        
                        <!-- 日志数据 -->
                        @if(!empty($log->data))
                            <div>
                                <div class="pl-5 border-l-2 border-gray-200 dark:border-gray-700">
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto">
                                        <div class="space-y-3">
                                        <div class="pb-2 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                                                    <div class="font-medium text-gray-700 dark:text-gray-300 mb-1"></div>
                                                    <div class="pl-3">
                                                        @if(is_array($log->data))
                                                            <pre class="whitespace-pre-wrap text-xs bg-white dark:bg-gray-800 p-2 rounded border border-gray-100 dark:border-gray-700">{{ json_encode($log->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                        @else
                                                            <span class="text-gray-800 dark:text-gray-200">{{ $log->data }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div> 