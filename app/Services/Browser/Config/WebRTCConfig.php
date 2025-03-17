<?php

namespace App\Services\Browser\Config;

class WebRTCConfig
{
    public function __construct(
        public readonly bool $enabled = true,
        public readonly ?string $webRTCDatas = null,
        public readonly ?string $webRTCVersion = null,
        public readonly ?string $webRTCPlatform = null
    ) {}
    
    /**
     * 获取禁用WebRTC的JavaScript代码
     */
    public function getDisablerScript(): ?string
    {
        if ($this->enabled) {
            return null;
        }
        
        return <<<'JS'
        // 替换原生的WebRTC方法
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia = function() {
                return new Promise(function(resolve, reject) {
                    reject(new DOMException('Permission denied', 'NotAllowedError'));
                });
            };
        }
        
        // 禁用RTCPeerConnection
        if (window.RTCPeerConnection) {
            window.RTCPeerConnection = function() {
                throw new Error('WebRTC is disabled');
            };
        }
        
        // 禁用旧版API
        if (navigator.getUserMedia) {
            navigator.getUserMedia = function() {
                throw new Error('WebRTC is disabled');
            };
        }
        JS;
    }
}