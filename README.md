## 发行说明

⚠️ 本项目仅供学习交流使用，禁止用于商业或非法用途。

B 站直播 WebSocket 连接的核心组件库，提供简洁的接口实现，包括登录、直播间信息流加密/解密、以及相关的关键方法。适合集成到需要对接 Bilibili 直播间的项目中（弹幕监控，礼物答谢、定时广告、关注感谢，自动回复等）

## 安装指南

使用 Composer 安装：

```shell
composer require hejunjie/bililive
```

## 当前支持的方法列表

| 类        | 说明                 |
| :-------- | :------------------- |
| Login     | 登录相关方法         |
| Live      | 直播间相关方法       |
| WebSocket | 直播间信息流相关方法 |

### 登录相关方法

| 方法                 | 说明             |
| :------------------- | :--------------- |
| Login::getQrcode()   | 获取扫描二维码   |
| Login::checkQrcode() | 验证登录信息     |
| Login::getUserInfo() | 获取用户基本信息 |

### 直播间相关方法

| 方法                           | 说明                            |
| :----------------------------- | :------------------------------ |
| Live::getRealRoomId()          | 获取真实房间号                  |
| Live::getRealRoomInfo()        | 获取直播间基本信息              |
| Live::getInitialWebSocketUrl() | 获取直播间连接信息              |
| Live::sendMsg()                | 发送弹幕                        |
| Live::reportLiveHeartbeat()    | web 端直播心跳上报(60 秒一次)   |
| Live::getOnlineGoldRank()      | 获取直播间在线榜                |
| Live::addSilentUser()          | 直播间禁言用户                  |
| Live::getSilentUserList()      | 获取直播间禁言用户列表          |
| Live::delSilentUser()          | 解除直播间禁言                  |
| Live::getVipNumbers()          | 获取直播间大航海数量            |
| Live::getStreamerInfo()        | 获取用户基本信息                |
| Live::getMasterInfo()          | 无 cookie 获取指定 uid 基本信息 |

### WebSocket

| 方法                               | 说明           |
| :--------------------------------- | :------------- |
| WebSocket::buildAuthPayload()      | 构建认证包数据 |
| WebSocket::buildHeartbeatPayload() | 构建心跳包数据 |
| WebSocket::parseResponsePayload()  | 解构响应数据包 |

---

在实时通讯和弹幕系统的开发中，常见的实现方案多基于 Java、Python 或 Go 语言，但少有采用 PHP 的项目。

传统的 PHP-FPM 架构确实不太适合即时通讯一类的方向，但随着 Workerman、Swoole 等优秀常驻进程方案的出现，PHP 在这一领域的潜力逐渐显现。

基于此，我决定创建这样一个库，以 PHP 的方式来实现 B 站的弹幕连接，希望给需要用 PHP 做类似弹幕姬项目的朋友提供一个简单方便的工具

---

## 🧩 配套项目

[![Core](https://img.shields.io/badge/php--bilibili--danmu--core-B站交互核心模块-blueviolet?style=for-the-badge&logo=php)](https://github.com/zxc7563598/php-bilibili-danmu-core)
[![Docker](https://img.shields.io/badge/php--bilibili--danmu--docker-Docker一键部署容器-2496ed?style=for-the-badge&logo=docker)](https://github.com/zxc7563598/php-bilibili-danmu-docker)
[![API](https://img.shields.io/badge/php--bilibili--danmu-项目本体-007acc?style=for-the-badge&logo=php)](https://github.com/zxc7563598/php-bilibili-danmu)
[![Admin](https://img.shields.io/badge/vue--bilibili--danmu--admin-前端：管理后台-42b883?style=for-the-badge&logo=vue.js)](https://github.com/zxc7563598/vue-bilibili-danmu-admin)
[![Shop](https://img.shields.io/badge/vue--bilibili--danmu--shop-前端：移动端积分商城-3eaf7c?style=for-the-badge&logo=vue.js)](https://github.com/zxc7563598/vue-bilibili-danmu-shop)

---

### Workerman 实现 B 站直播信息流的监听

> 基础实例，代码自行调整

> 弹幕监控，礼物答谢、定时广告、关注感谢，自动回复等功能于 `onMessageReceived` 方法中自行实现

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
