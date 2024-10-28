
## 发行说明

B站直播 WebSocket 连接的核心组件库，提供简洁的接口实现，包括登录、直播间信息流加密/解密、以及相关的关键方法。适合集成到需要对接 Bilibili 直播间的项目中（弹幕监控，礼物答谢、定时广告、关注感谢，自动回复等）


## 安装指南

使用 Composer 安装：

```shell
composer require hejunjie/bililive
```


## 当前支持的方法列表

|类|说明|
|:----|:----|
| Login | 登录相关方法 |
| Live | 直播间相关方法 |
| WebSocket | 直播间信息流相关方法 |

### 登录相关方法

|方法|说明|
|:----|:----|
| Login::getQrcode() | 获取扫描二维码 |
| Login::checkQrcode() | 验证登录信息 |


### 直播间相关方法

|方法|说明|
|:----|:----|
| Live::getRealRoomId() | 获取真实房间号 |
| Live::getInitialWebSocketUrl() | 获取直播间连接信息 |
| Live::sendMsg() | 发送弹幕 |
| Live::reportLiveHeartbeat() | web端直播心跳上报(60秒一次) |

### WebSocket

|方法|说明|
|:----|:----|
| WebSocket::buildAuthPayload() | 构建认证包数据 |
| WebSocket::buildHeartbeatPayload() | 构建心跳包数据 |
| WebSocket::parseResponsePayload() | 解构响应数据包 |

---

在实时通讯和弹幕系统的开发中，常见的实现方案多基于 Java、Python 或 Go 语言，但少有采用 PHP 的项目。

传统的 PHP-FPM 架构确实不太适合即时通讯一类的方向，但随着 Workerman、Swoole 等优秀常驻进程方案的出现，PHP 在这一领域的潜力逐渐显现。

基于此，我决定创建这样一个库，以 PHP 的方式来实现 B站的弹幕连接，希望给需要用 PHP 做类似弹幕姬项目的朋友提供一个简单方便的工具

---

### Workerman实现B站直播信息流的监听

> 基础实例，代码自行调整

> 弹幕监控，礼物答谢、定时广告、关注感谢，自动回复等功能于 ` onMessageReceived ` 方法中自行实现

```php
<?php

namespace app\server;

use Hejunjie\Bililive;
use Workerman\Timer;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Protocols\Ws;

class Bilibili
{
    private int $reconnectInterval = 5; // 重连间隔时间（秒）
    private string $cookie;
    private int $roomId;

    public function __construct()
    {
        // 设置cookie和房间ID（可替换成配置项或参数传入）
        $this->cookie = ''; // 浏览器中复制的cookie内容，或者通过 Login::getQrcode() 获取登录地址在哔哩哔哩中打开（自行转换二维码扫码）后通过 Login::checkQrcode() 获取存储到本地
        $this->roomId = ''; // 房间号
    }

    public function onWorkerStart()
    {
        $this->connectToWebSocket();
    }

    /**
     * 连接到 WebSocket 服务器
     */
    private function connectToWebSocket()
    {
        // 获取真实房间号和WebSocket连接信息
        $realRoomId = Bililive\Live::getRealRoomId($this->roomId, $this->cookie);
        $wsData = Bililive\Live::getInitialWebSocketUrl($realRoomId, $this->cookie);

        $wsUrl = 'ws://' . $wsData['host'] . ':' . $wsData['wss_port'] . '/sub';
        $token = $wsData['token'];

        // 创建 WebSocket 连接
        $con = new AsyncTcpConnection($wsUrl);
        $this->setupConnection($con, $realRoomId, $token);
        $con->connect();
    }

    /**
     * 设置 WebSocket 连接的参数和回调
     * 
     * @param AsyncTcpConnection $con
     * @param int $roomId
     * @param string $token
     */
    private function setupConnection(AsyncTcpConnection $con, int $roomId, string $token)
    {
        // 设置 SSL 和自定义 HTTP 头
        $con->transport = 'ssl';
        $con->headers = $this->buildHeaders();

        // 设置WebSocket为二进制类型
        $con->websocketType = Ws::BINARY_TYPE_ARRAYBUFFER;

        // 设置连接成功回调
        $con->onConnect = function (AsyncTcpConnection $con) use ($roomId, $token) {
            $this->onConnected($con, $roomId, $token);
        };

        // 设置消息接收回调
        $con->onMessage = function (AsyncTcpConnection $con, $data) {
            $this->onMessageReceived($data);
        };

        // 设置连接关闭回调
        $con->onClose = function () {
            echo "Connection closed, attempting to reconnect...\n";
            $this->scheduleReconnect();
        };

        // 设置连接错误回调
        $con->onError = function ($connection, $code, $msg) {
            echo "Error: $msg (code: $code), attempting to reconnect...\n";
            $this->scheduleReconnect();
        };
    }

    /**
     * 构建 WebSocket 请求的自定义 HTTP 头
     * 
     * @return array
     */
    private function buildHeaders(): array
    {
        return [
            "User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin" => "https://live.bilibili.com",
            "Connection" => "Upgrade",
            "Pragma" => "no-cache",
            "Cache-Control" => "no-cache",
            "Upgrade" => "websocket",
            "Sec-WebSocket-Version" => "13",
            "Accept-Encoding" => "gzip, deflate, br, zstd",
            "Accept-Language" => "zh-CN,zh;q=0.9",
            'Sec-WebSocket-Key' => base64_encode(random_bytes(16)),
            "Sec-WebSocket-Extensions" => "permessage-deflate; client_max_window_bits",
            'Cookie' => $this->cookie
        ];
    }

    /**
     * WebSocket连接成功时的处理
     *
     * @param AsyncTcpConnection $con
     * @param int $roomId
     * @param string $token
     */
    private function onConnected(AsyncTcpConnection $con, int $roomId, string $token)
    {
        echo "已连接到WebSocket,房间号:" . $roomId . "\n";
        // 发送认证包
        $con->send(Bililive\WebSocket::buildAuthPayload($roomId, $token, $this->cookie));

        // 设置 websocket 心跳包发送定时器，每30秒发送一次
        Timer::add(30, function () use ($con) {
            if ($con->getStatus() === AsyncTcpConnection::STATUS_ESTABLISHED) {
                $con->send(Bililive\WebSocket::buildHeartbeatPayload());
            }
        });
        
        // 设置 http 心跳包发送定时器，每60秒发送一次
        Timer::add(60, function () use ($con, $roomId) {
            if ($con->getStatus() === AsyncTcpConnection::STATUS_ESTABLISHED) {
                $con->send(Bililive\Live::reportLiveHeartbeat($roomId, $this->cookie));
            }
        });
    }

    /**
     * 接收 WebSocket 消息时的处理
     *
     * @param mixed $data
     */
    private function onMessageReceived($data)
    {
        // 解析消息内容
        $message = Bililive\WebSocket::parseResponsePayload($data);
        foreach ($message['payload'] as $payload) {
            if (isset($payload['payload']['cmd'])) {
                switch ($payload['payload']['cmd']) {
                    case 'DANMU_MSG': // 弹幕

                        break;
                    case 'SEND_GIFT': // 礼物

                        break;
                    case 'INTERACT_WORD': // 关注

                        break;
                }
            }
        }
    }

    /**
     * 设置重连的定时任务
     */
    private function scheduleReconnect()
    {
        Timer::add($this->reconnectInterval, function () {
            $this->onWorkerStart();
        }, [], false);
    }
}

```