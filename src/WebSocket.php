<?php

namespace Hejunjie\Bililive;

use Exception;
use Hejunjie\Bililive\Service\HttpClient;
use Hejunjie\Bililive\Service\Processing;

/**
 * websocket 处理类
 * @package Hejunjie\Bililive
 */
class WebSocket
{

    /**
     * 构建认证包数据
     * 
     * @param int $room_id 房间号
     * @param string $token init方法返回的认证密钥
     * @param string $cookie 用户cookie
     * 
     * @return string 需要发送的数据
     */
    public static function buildAuthPayload(int $room_id, string $token, string $cookie): string
    {
        // 通过cookie获取用户信息
        $uid = Processing::getUidFromCookie($cookie);
        $buvid3 = Processing::getBuvid3FromCookie($cookie);
        // 构建数据
        $packet = json_encode([
            'uid' => $uid,
            'roomid' => $room_id,
            'protover' => 3, //2-zlib，3-brotli
            'buvid' => $buvid3,
            'platform' => 'web',
            'type' => 2,
            'key' => $token
        ]);
        // 获取头部
        $buildHeader = Processing::buildHeader(strlen($packet), 1, 7);
        return $buildHeader . $packet;
    }

    /**
     * 构建心跳包数据
     * 
     * @return string 需要发送的数据
     */
    public static function buildHeartbeatPayload(): string
    {
        $packet = '[object Object]';
        $buildHeader = Processing::buildHeader(strlen($packet), 1, 2);
        return $buildHeader . $packet;
    }

    /**
     * 解构响应数据包
     * 
     * @param mixed $data 
     * 
     * @return array 
     */
    public static function parseResponsePayload($data): array
    {
        $body = Processing::unpack($data);
        if (isset($body['protocol_version'])) {
            switch ($body['protocol_version']) {
                case 0: // 普通包 (正文不使用压缩)
                    $result['type'] = '普通包 (正文不使用压缩)';
                    $body['payload'] = json_decode($body['payload'], true);
                    $result['payload'] = [$body];
                    break;
                case 1: // 心跳及认证包 (正文不使用压缩)
                    $result['type'] = '心跳及认证包 (正文不使用压缩)';
                    $result['payload'] = [];
                    break;
                case 2: // 普通包 (正文使用 zlib 压缩)
                    $result['type'] = '加密包 (正文使用 zlib 压缩)';
                    $result['payload'] = Processing::zlib($body['payload']);
                    break;
                case 3: // 普通包 (使用 brotli 压缩的多个带文件头的普通包)
                    // 3. 解压 Brotli 压缩的正文
                    $result['type'] = '加密包 (正文使用 brotli 压缩)';
                    $result['payload'] = Processing::brotli($body['payload']);
                    break;
            }
        }
        return $result;
    }
}
