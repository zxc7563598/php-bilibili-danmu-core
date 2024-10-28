
## 发行说明

B站直播 WebSocket 连接的核心组件库，提供简洁的接口实现，包括登录、直播间信息流加密/解密、以及相关的关键方法。适合集成到需要对接 Bilibili 直播间的项目中（弹幕监控，礼物答谢、定时广告、关注感谢，自动回复）


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