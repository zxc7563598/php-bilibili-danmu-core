# Hejunjie\Bililive

[English](./README.md) ｜ 简体中文

B 站直播 WebSocket 核心组件库，提供登录、直播间操作、弹幕信息流加解密等接口。配合 Workerman 等常驻进程方案，可快速搭建弹幕监控、礼物答谢、定时广告、自动回复等直播间应用。

[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-blue?style=flat-square)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

> [!WARNING]
> 本项目仅供学习交流使用，禁止用于商业或非法用途。

**想快速了解本项目？** 已由 [Zread](https://zread.ai/zxc7563598/php-bilibili-danmu-core) 完成代码解析。

## 特性

- 完整的 B 站二维码登录流程，自动组装 Cookie
- 直播间常用操作：获取房间信息、发送弹幕、禁言管理、在线榜、大航海数量等
- WebSocket 数据包构建与解析，支持 Brotli 和 Zlib 加密数据解包
- 部分接口（如获取用户基本信息）支持无 Cookie 调用
- 所有方法均为静态调用，无需实例化

## 环境要求

- PHP >= 8.0
- [ext-brotli](https://www.php.net/manual/zh/book.brotli.php)
- Composer

## 安装

```shell
composer require hejunjie/bililive
```

## 快速开始

以下是一个最小化的登录 → 获取房间信息 → 连接 WebSocket 流程：

```php
<?php

use Hejunjie\Bililive\Live;
use Hejunjie\Bililive\Login;

// 1. 获取登录二维码
$qrcode = Login::getQrcode();
// 将 $qrcode['url'] 生成二维码，让用户使用 B 站客户端扫码
// 轮询检查扫码状态
while (true) {
    $result = Login::checkQrcode($qrcode['qrcode_key']);
    if ($result['code'] == 0) {
        $cookie = $result['cookie'];
        break;
    }
    sleep(1);
}

// 2. 获取直播间真实房间号
$realRoomId = Live::getRealRoomId(12345, $cookie);

// 3. 获取 WebSocket 连接信息
$wsData = Live::getInitialWebSocketUrl($realRoomId, $cookie);
// $wsData['token']  // 认证密钥
// $wsData['host']   // 服务器地址
// $wsData['wss_port'] // WSS 端口
```

## API 概览

### Login — 登录相关

| 方法 | 说明 |
| :--- | :--- |
| `Login::getQrcode()` | 获取登录二维码 |
| `Login::checkQrcode()` | 轮询扫码状态，登录成功时返回 Cookie |
| `Login::getUserInfo()` | 获取当前登录用户的基本信息 |

### Live — 直播间相关

| 方法 | 说明 |
| :--- | :--- |
| `Live::getRealRoomId()` | 获取真实房间号 |
| `Live::getRealRoomInfo()` | 获取直播间基本信息 |
| `Live::getInitialWebSocketUrl()` | 获取 WebSocket 连接信息 |
| `Live::getUserBarrageMsg()` | 获取用户在目标房间的弹幕发送权限 |
| `Live::sendMsg()` | 发送弹幕 |
| `Live::reportLiveHeartbeat()` | Web 端直播心跳上报（60 秒一次） |
| `Live::getOnlineGoldRank()` | 获取直播间在线榜 |
| `Live::addSilentUser()` | 添加直播间禁言用户 |
| `Live::getSilentUserList()` | 获取直播间禁言用户列表 |
| `Live::delSilentUser()` | 解除直播间禁言 |
| `Live::getVipNumbers()` | 获取直播间大航海数量 |
| `Live::getStreamerInfo()` | 获取用户基本信息 |
| `Live::getMasterInfo()` | 无 Cookie 获取指定 UID 基本信息 |
| `Live::getUserInfo()` | ~~获取用户基本信息~~（已弃用，请使用 `getStreamerInfo()`） |

### WebSocket — 数据包处理

| 方法 | 说明 |
| :--- | :--- |
| `WebSocket::buildAuthPayload()` | 构建认证包数据 |
| `WebSocket::buildHeartbeatPayload()` | 构建心跳包数据 |
| `WebSocket::parseResponsePayload()` | 解析响应数据包（自动处理 Brotli/Zlib 解密） |

## 使用示例：基于 Workerman 的弹幕监控

以下示例演示如何在 Workerman 中连接 B 站直播间，监听弹幕、礼物、关注等事件。你可以在 `onMessageReceived` 方法中实现自己的业务逻辑。

```php
<?php

namespace app\server;

use Hejunjie\Bililive;
use Workerman\Timer;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Protocols\Ws;

class Bilibili
{
    private int $reconnectInterval = 5;
    private string $cookie;
    private int $roomId;

    public function __construct()
    {
        $this->cookie = ''; // 浏览器复制的 cookie，或通过 Login 扫码获取
        $this->roomId = ''; // 房间号
    }

    public function onWorkerStart()
    {
        $this->connectToWebSocket();
    }

    private function connectToWebSocket()
    {
        $realRoomId = Bililive\Live::getRealRoomId($this->roomId, $this->cookie);
        $wsData = Bililive\Live::getInitialWebSocketUrl($realRoomId, $this->cookie);

        $wsUrl = 'ws://' . $wsData['host'] . ':' . $wsData['wss_port'] . '/sub';
        $token = $wsData['token'];

        $con = new AsyncTcpConnection($wsUrl);
        $this->setupConnection($con, $realRoomId, $token);
        $con->connect();
    }

    private function setupConnection(AsyncTcpConnection $con, int $roomId, string $token)
    {
        $con->transport = 'ssl';
        $con->headers = $this->buildHeaders();
        $con->websocketType = Ws::BINARY_TYPE_ARRAYBUFFER;

        $con->onConnect = function (AsyncTcpConnection $con) use ($roomId, $token) {
            echo "已连接 WebSocket，房间号：" . $roomId . "\n";

            // 发送认证包
            $con->send(Bililive\WebSocket::buildAuthPayload($roomId, $token, $this->cookie));

            // WebSocket 心跳，30 秒一次
            Timer::add(30, function () use ($con) {
                if ($con->getStatus() === AsyncTcpConnection::STATUS_ESTABLISHED) {
                    $con->send(Bililive\WebSocket::buildHeartbeatPayload());
                }
            });

            // HTTP 心跳，60 秒一次
            Timer::add(60, function () use ($con, $roomId) {
                if ($con->getStatus() === AsyncTcpConnection::STATUS_ESTABLISHED) {
                    Bililive\Live::reportLiveHeartbeat($roomId, $this->cookie);
                }
            });
        };

        $con->onMessage = function (AsyncTcpConnection $con, $data) {
            $this->onMessageReceived($data);
        };

        $con->onClose = function () {
            echo "连接断开，尝试重连...\n";
            $this->scheduleReconnect();
        };

        $con->onError = function ($connection, $code, $msg) {
            echo "连接错误: $msg (code: $code)\n";
            $this->scheduleReconnect();
        };
    }

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

    private function onMessageReceived($data)
    {
        $message = Bililive\WebSocket::parseResponsePayload($data);
        foreach ($message['payload'] as $payload) {
            if (isset($payload['payload']['cmd'])) {
                switch ($payload['payload']['cmd']) {
                    case 'DANMU_MSG':     // 弹幕消息
                        // 在此实现弹幕处理逻辑
                        break;
                    case 'SEND_GIFT':     // 礼物消息
                        // 在此实现礼物答谢逻辑
                        break;
                    case 'INTERACT_WORD': // 关注消息
                        // 在此实现关注感谢逻辑
                        break;
                }
            }
        }
    }

    private function scheduleReconnect()
    {
        Timer::add($this->reconnectInterval, function () {
            $this->onWorkerStart();
        }, [], false);
    }
}
```

## 配套项目

| 项目 | 说明 |
| :--- | :--- |
| [php-bilibili-danmu-core](https://github.com/zxc7563598/php-bilibili-danmu-core) | B 站交互核心模块（本项目） |
| [php-bilibili-danmu-docker](https://github.com/zxc7563598/php-bilibili-danmu-docker) | Docker 一键部署容器 |
| [php-bilibili-danmu](https://github.com/zxc7563598/php-bilibili-danmu) | 项目本体 |
| [vue-bilibili-danmu-admin](https://github.com/zxc7563598/vue-bilibili-danmu-admin) | 前端：管理后台 |
| [vue-bilibili-danmu-shop](https://github.com/zxc7563598/vue-bilibili-danmu-shop) | 前端：移动端积分商城 |

## 注意事项

> [!WARNING]
> 本项目仅供学习交流使用，禁止用于商业或非法用途。

- 传统 PHP-FPM 架构由于请求-响应模型的限制，不太适合长连接场景。建议配合 [Workerman](https://github.com/walkor/workerman) 或 [Swoole](https://github.com/swoole/swoole-src) 等常驻进程方案使用。
- 需要启用 `ext-brotli` 扩展来解密 WebSocket 数据包，否则收到的弹幕消息无法正确解析。
- `Live::getUserInfo()` 已标记为弃用，请使用 `Live::getStreamerInfo()` 替代。
