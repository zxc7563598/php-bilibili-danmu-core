# Hejunjie\Bililive

English ｜ [简体中文](./README.zh-CN.md)

A core PHP library for Bilibili live streaming WebSocket connections, providing interfaces for login, room operations, and danmu (bullet chat) stream encryption/decryption. Paired with long-running process solutions like Workerman, you can quickly build live room applications such as danmu monitoring, gift acknowledgments, scheduled ads, and auto-replies.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-blue?style=flat-square)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE)

> [!WARNING]
> This project is for learning and communication purposes only. Commercial or illegal use is strictly prohibited.

**Want a quick overview?** The codebase has been parsed by [Zread](https://zread.ai/zxc7563598/php-bilibili-danmu-core).

## Features

- Complete Bilibili QR code login flow with automatic cookie assembly
- Common live room operations: room info, danmu sending, mute management, online rankings, VIP count, and more
- WebSocket packet construction and parsing, with support for Brotli and Zlib encrypted data decryption
- Cookie-free support for select APIs (e.g., fetching basic user info)
- All methods are static — no instantiation required

## Requirements

- PHP >= 8.0
- [ext-brotli](https://www.php.net/manual/en/book.brotli.php)
- Composer

## Installation

```shell
composer require hejunjie/bililive
```

## Quick Start

A minimal login → room info → WebSocket connection flow:

```php
<?php

use Hejunjie\Bililive\Live;
use Hejunjie\Bililive\Login;

// 1. Get login QR code
$qrcode = Login::getQrcode();
// Generate a QR image from $qrcode['url'] and let the user scan it with the Bilibili app
// Poll the scan status
while (true) {
    $result = Login::checkQrcode($qrcode['qrcode_key']);
    if ($result['code'] == 0) {
        $cookie = $result['cookie'];
        break;
    }
    sleep(1);
}

// 2. Get the real room ID
$realRoomId = Live::getRealRoomId(12345, $cookie);

// 3. Get WebSocket connection details
$wsData = Live::getInitialWebSocketUrl($realRoomId, $cookie);
// $wsData['token']    // auth token
// $wsData['host']     // server host
// $wsData['wss_port'] // WSS port
```

## API Reference

### Login

| Method | Description |
| :--- | :--- |
| `Login::getQrcode()` | Generate a login QR code |
| `Login::checkQrcode()` | Poll the QR code scan status; returns cookie on success |
| `Login::getUserInfo()` | Get basic info of the currently logged-in user |

### Live

| Method | Description |
| :--- | :--- |
| `Live::getRealRoomId()` | Get the real room ID (resolves short room IDs) |
| `Live::getRealRoomInfo()` | Get live room basic info |
| `Live::getInitialWebSocketUrl()` | Get WebSocket connection details |
| `Live::getUserBarrageMsg()` | Get the user's danmu sending permissions for a room |
| `Live::sendMsg()` | Send a danmu message |
| `Live::reportLiveHeartbeat()` | Send web live heartbeat (every 60 seconds) |
| `Live::getOnlineGoldRank()` | Get the room's online ranking |
| `Live::addSilentUser()` | Mute a user in the room |
| `Live::getSilentUserList()` | Get the room's muted user list |
| `Live::delSilentUser()` | Unmute a user in the room |
| `Live::getVipNumbers()` | Get the number of VIP subscriptions (Guard) |
| `Live::getStreamerInfo()` | Get user basic info |
| `Live::getMasterInfo()` | Get basic info of a specified UID without cookie |
| `Live::getUserInfo()` | ~~Get user basic info~~ (deprecated, use `getStreamerInfo()`) |

### WebSocket

| Method | Description |
| :--- | :--- |
| `WebSocket::buildAuthPayload()` | Build authentication packet |
| `WebSocket::buildHeartbeatPayload()` | Build heartbeat packet |
| `WebSocket::parseResponsePayload()` | Parse response packets (auto-handles Brotli/Zlib decryption) |

## Example: Danmu Monitor with Workerman

The following example demonstrates how to connect to a Bilibili live room using Workerman and listen for danmu messages, gifts, and follows. Implement your own business logic inside `onMessageReceived`.

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
        $this->cookie = ''; // Cookie copied from browser, or obtained via Login QR flow
        $this->roomId = ''; // Room ID
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
            echo "Connected to WebSocket, room: " . $roomId . "\n";

            // Send authentication packet
            $con->send(Bililive\WebSocket::buildAuthPayload($roomId, $token, $this->cookie));

            // WebSocket heartbeat every 30 seconds
            Timer::add(30, function () use ($con) {
                if ($con->getStatus() === AsyncTcpConnection::STATUS_ESTABLISHED) {
                    $con->send(Bililive\WebSocket::buildHeartbeatPayload());
                }
            });

            // HTTP heartbeat every 60 seconds
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
            echo "Connection closed, reconnecting...\n";
            $this->scheduleReconnect();
        };

        $con->onError = function ($connection, $code, $msg) {
            echo "Connection error: $msg (code: $code)\n";
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
                    case 'DANMU_MSG':     // Danmu message
                        // Implement your danmu handling logic here
                        break;
                    case 'SEND_GIFT':     // Gift message
                        // Implement your gift acknowledgment logic here
                        break;
                    case 'INTERACT_WORD': // Follow notification
                        // Implement your follow acknowledgment logic here
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

## Related Projects

| Project | Description |
| :--- | :--- |
| [php-bilibili-danmu-core](https://github.com/zxc7563598/php-bilibili-danmu-core) | Core Bilibili interaction module (this project) |
| [php-bilibili-danmu-docker](https://github.com/zxc7563598/php-bilibili-danmu-docker) | One-click Docker deployment |
| [php-bilibili-danmu](https://github.com/zxc7563598/php-bilibili-danmu) | Main application |
| [vue-bilibili-danmu-admin](https://github.com/zxc7563598/vue-bilibili-danmu-admin) | Frontend: Admin dashboard |
| [vue-bilibili-danmu-shop](https://github.com/zxc7563598/vue-bilibili-danmu-shop) | Frontend: Mobile points shop |

## Notes

- Traditional PHP-FPM is not well-suited for persistent connections due to its request-response model. Use long-running process solutions such as [Workerman](https://github.com/walkor/workerman) or [Swoole](https://github.com/swoole/swoole-src).
- The `ext-brotli` extension is required to decrypt WebSocket packets. Without it, danmu messages cannot be parsed correctly.
- `Live::getUserInfo()` is deprecated. Use `Live::getStreamerInfo()` instead.
