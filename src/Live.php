<?php

namespace Hejunjie\Bililive;

use Exception;
use Hejunjie\Bililive\Service\HttpClient;
use Hejunjie\Bililive\Service\Processing;

/**
 * 直播接口处理类
 * @package Hejunjie\Bililive
 */
class Live
{

    private static $config;

    // 初始化配置
    private static function init(): void
    {
        if (!self::$config) {
            self::$config = require __DIR__ . '/Config/api.php';
        }
    }

    /**
     * 获取真实房间号
     * 
     * @param int $room_id 房间号（可以是短号）
     * @param string $cookie 用户cookie
     * 
     * @return int 真实房间号
     * @throws Exception 
     */
    public static function getRealRoomId(int $room_id, string $cookie): int
    {
        self::init();
        $getRealRoomId = HttpClient::sendGetRequest(self::$config['getRealRoomId'] . '?room_id=' . $room_id, [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ], 10, $cookie);
        if ($getRealRoomId['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getRealRoomId['httpStatus']);
        }
        $jsonData = json_decode($getRealRoomId['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        return !empty($jsonData['data']['room_id']) ? $jsonData['data']['room_id'] : $room_id;
    }

    /**
     * 获取直播间基本信息
     * 
     * @param int $room_id 房间号（可以是短号）
     * @param string $cookie 用户cookie
     * 
     * @return array {code:接口状态`int`, msg:失败后的信息`string`, data:成功后的数据`array`} 
     * @throws Exception 
     */
    public static function getRealRoomInfo(int $room_id, string $cookie): array
    {
        self::init();
        $getRealRoomInfo = HttpClient::sendGetRequest(self::$config['getRealRoomId'] . '?room_id=' . $room_id, [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ], 10, $cookie);
        if ($getRealRoomInfo['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getRealRoomInfo['httpStatus']);
        }
        $jsonData = json_decode($getRealRoomInfo['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        return [
            'code' => $jsonData['code'],
            'msg' => $jsonData['msg'],
            'data' => [
                'uid' => isset($jsonData['data']['uid']) ? $jsonData['data']['uid'] : 0, // uid
                'room_id' => isset($jsonData['data']['room_id']) ? $jsonData['data']['room_id'] : 0, // 房间号
                'attention' => isset($jsonData['data']['attention']) ? $jsonData['data']['attention'] : 0, // 关注数量
                'online' => isset($jsonData['data']['online']) ? $jsonData['data']['online'] : 0, // 观看人数
                'live_status' => isset($jsonData['data']['live_status']) ? $jsonData['data']['live_status'] : 0, // 直播状态，0=未开播,1=直播中,2=轮播中
                'title' => isset($jsonData['data']['title']) ? $jsonData['data']['title'] : '', // 直播间标题
                'live_time' => isset($jsonData['data']['live_time']) ? $jsonData['data']['live_time'] : '', // 直播开始时间
                'keyframe' => isset($jsonData['data']['keyframe']) ? $jsonData['data']['keyframe'] : '' // 关键帧
            ]
        ];
    }

    /**
     * 
     * 获取直播间连接信息
     * 
     * @param int $room_id 直播间房间号
     * @param string $cookie 用户cookie
     * 
     * @return array {token:认证密钥`string`, host:服务器域名`string`, port:TCP端口`int`, ws_port:WS端口`int`, wss_port:WSS端口`int`} 
     * @throws Exception 
     */
    public static function getInitialWebSocketUrl(int $room_id, string $cookie)
    {
        self::init();
        $getDanmuInfo = HttpClient::sendGetRequest(self::$config['getDanmuInfo'] . '?id=' . $room_id, [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ], 10, $cookie);
        if ($getDanmuInfo['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getDanmuInfo['httpStatus']);
        }
        $jsonData = json_decode($getDanmuInfo['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['data'])) {
            throw new \Exception("接口未返回有效数据");
        }
        return [
            'token' => $jsonData['data']['token'],
            'host' => $jsonData['data']['host_list'][0]['host'],
            'port' => $jsonData['data']['host_list'][0]['port'],
            'ws_port' => $jsonData['data']['host_list'][0]['ws_port'],
            'wss_port' => $jsonData['data']['host_list'][0]['wss_port']
        ];
    }

    /**
     * 获取用户在目标房间所能发送弹幕的最大长度
     * 
     * @param int $room_id 
     * @param string $cookie 
     * 
     * @return array {mode:mode`int`, color:字体颜色`int`, length:长度`int`, bubble:bubble`int`}
     * @throws Exception 
     */
    public static function getUserBarrageMsg(int $room_id, string $cookie): array
    {
        self::init();
        $getInfoByUser = HttpClient::sendGetRequest(self::$config['getInfoByUser'] . '?room_id=' . $room_id, [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ], 10, $cookie);
        if ($getInfoByUser['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getInfoByUser['httpStatus'] . "详情：" . json_encode($getInfoByUser, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        $jsonData = json_decode($getInfoByUser['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['data']['property'])) {
            throw new \Exception("接口未返回有效数据");
        }
        return [
            'mode' => $jsonData['data']['property']['danmu']['mode'],
            'color' => $jsonData['data']['property']['danmu']['color'],
            'length' => $jsonData['data']['property']['danmu']['length'],
            'bubble' => $jsonData['data']['property']['bubble']
        ];
    }

    /**
     * 发送弹幕
     * 
     * @param int $room_id 直播间房间号
     * @param string $cookie 用户cookie
     * @param string $message 需要发送的消息
     * 
     * @return void 
     * @throws Exception 
     */
    public static function sendMsg(int $room_id, string $cookie, string $message): void
    {
        self::init();
        $getUserBarrageMsg = self::getUserBarrageMsg($room_id, $cookie);
        $bili_jct = Processing::getBiliJctFromCookie($cookie);
        $max_length = 30;
        $length = mb_strlen($message, 'UTF-8');

        for ($i = 0; $i < $length; $i += $max_length) {
            $sendMsg = HttpClient::sendPostRequest(self::$config['sendMsg'], [
                "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
                "Origin: https://live.bilibili.com/" . $room_id,
                // "Content-type: application/x-www-form-urlencoded",
                // "Accept: application/x-www-form-urlencoded"
            ], http_build_query([
                'color' => $getUserBarrageMsg['color'],
                'fontsize' => 25,
                'mode' => $getUserBarrageMsg['mode'],
                'msg' =>  mb_substr($message, $i, $max_length, 'UTF-8'),
                'rnd' => time(),
                'roomid' => $room_id,
                'bubble' => $getUserBarrageMsg['bubble'],
                'csrf_token' => $bili_jct,
                'csrf' => $bili_jct
            ]), 10, $cookie);
            if ($sendMsg['httpStatus'] != 200) {
                throw new \Exception('接口异常响应 httpStatus: ' . $sendMsg['httpStatus'] . "详情：" . json_encode($sendMsg, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
            }
            $jsonData = json_decode($sendMsg['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
            }
            if (!isset($jsonData['code'])) {
                throw new \Exception("弹幕发送失败, 无法获取数据");
            }
            if ($jsonData['code'] != 0) {
                throw new \Exception("弹幕发送失败, 详情：" . json_encode($jsonData, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
            }
        }
    }

    /**
     * web端直播心跳上报(60秒一次)
     * 
     * @param int $room_id 直播间房间号
     * @param string $cookie 用户cookie
     * 
     * @return void 
     */
    public static function reportLiveHeartbeat(int $room_id, string $cookie): void
    {
        self::init();
        $hb = base64_encode('60|' . $room_id . '1|0');
        HttpClient::sendGetRequest(self::$config['webHeartBeat'] . '?pf=web&hb=' . $hb, [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com/" . $room_id,
        ], 10, $cookie);
        HttpClient::sendGetRequest(self::$config['heartBeat'], [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com/" . $room_id,
        ], 10, $cookie);
    }

    /**
     * 获取直播间在线榜
     * 
     * @param int $uid 主播uid
     * @param int $room_id 主播房间号
     * @param string $cookie 用户cookie
     * 
     * @return array {online_num: 在线人数`int`, online_item: 每个在线的信息`array`}
     * @throws Exception 
     */
    public static function getOnlineGoldRank(int $uid, int $room_id, string $cookie): array
    {
        self::init();
        $getOnlineGoldRank = HttpClient::sendGetRequest(self::$config['getOnlineGoldRank'] . '?ruid=' . $uid . '&roomId=' . $room_id . '&page=1&pageSize=5000', [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ], 10, $cookie);
        if ($getOnlineGoldRank['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getOnlineGoldRank['httpStatus'] . ', 详情：' . json_encode($getOnlineGoldRank, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        $jsonData = json_decode($getOnlineGoldRank['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['code'])) {
            throw new \Exception("高能榜获取失败, 无法获取数据");
        }
        if ($jsonData['code'] != 0) {
            throw new \Exception("高能榜获取失败, 详情：" . json_encode($jsonData, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        $onlineNum = $jsonData['data']['onlineNum'];
        $onlineItem = [];
        foreach ($jsonData['data']['OnlineRankItem'] as $item) {
            $onlineItem[] = [
                'rank' => $item['userRank'], // 排名
                'uid' => $item['uid'], // uid
                'name' => $item['name'], // 名称
                'score' => $item['score'] // 贡献
            ];
        }
        return [
            'online_num' => $onlineNum,
            'online_item' => $onlineItem
        ];
    }
}
