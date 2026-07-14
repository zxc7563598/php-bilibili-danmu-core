<?php

declare(strict_types=1);

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
     * @param mixed $data 数据包
     * 
     * @return array 
     */
    public static function parseResponsePayload(mixed $data): array
    {
        $result = [];
        $body = Processing::unpack($data);
        if (isset($body['protocol_version'])) {
            $result = match ((int) $body['protocol_version']) {
                0 => [ // 普通包 (正文不使用压缩)
                    'type' => '普通包 (正文不使用压缩)',
                    'payload' => [array_merge($body, ['payload' => json_decode($body['payload'], true)])],
                ],
                1 => [ // 心跳及认证包 (正文不使用压缩)
                    'type' => '心跳及认证包 (正文不使用压缩)',
                    'payload' => [],
                ],
                2 => [ // 普通包 (正文使用 zlib 压缩)
                    'type' => '加密包 (正文使用 zlib 压缩)',
                    'payload' => Processing::zlib($body['payload']),
                ],
                3 => [ // 普通包 (使用 brotli 压缩的多个带文件头的普通包)
                    'type' => '加密包 (正文使用 brotli 压缩)',
                    'payload' => Processing::brotli($body['payload']),
                ],
                default => $result,
            };
        }
        return $result;
    }
}
