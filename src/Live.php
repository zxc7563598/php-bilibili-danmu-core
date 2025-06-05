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
            "Origin: https://live.bilibili.com",
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
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
        // 获取直播间信息
        $getRealRoomInfo = HttpClient::sendGetRequest(self::$config['getRealRoomId'] . '?room_id=' . $room_id, [
            "Origin: https://live.bilibili.com",
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
        if ($getRealRoomInfo['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getRealRoomInfo['httpStatus']);
        }
        $jsonData = json_decode($getRealRoomInfo['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        // 获取主播个人信息
        if (isset($jsonData['data']['uid'])) {
            $getMasterInfo = HttpClient::sendGetRequest(self::$config['getMasterInfo'] . '?uid=' . $jsonData['data']['uid'], [
                "Origin: https://live.bilibili.com",
            ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
            if ($getMasterInfo['httpStatus'] != 200) {
                throw new \Exception('接口异常响应 httpStatus: ' . $getMasterInfo['httpStatus']);
            }
            $getMasterInfoData = json_decode($getMasterInfo['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
            }
        }
        // 返回数据
        return [
            'code' => $jsonData['code'],
            'msg' => $jsonData['msg'],
            'data' => [
                'uid' => isset($jsonData['data']['uid']) ? $jsonData['data']['uid'] : 0, // uid
                'uname' => isset($getMasterInfoData['data']['info']['uname']) ? $getMasterInfoData['data']['info']['uname'] : 0, // uname
                'face' => isset($getMasterInfoData['data']['info']['face']) ? $getMasterInfoData['data']['info']['face'] : 0, // 头像
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
        // 获取wbi
        $getWbiKeys = Processing::getWbiKeys($cookie);
        $signedParams = Processing::encWbi([
            'id' => $room_id,
            'type' => 0,
            'web_location' => 444.8,
        ], $getWbiKeys['img_key'], $getWbiKeys['sub_key']);
        // 请求数据
        $url = self::$config['getDanmuInfo'] . '?' . $signedParams;
        echo '[请求数据]' . $url . PHP_EOL;
        $getDanmuInfo = HttpClient::sendGetRequest($url, [
            "Origin: https://live.bilibili.com",
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
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
            "Origin: https://live.bilibili.com",
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
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
                "Origin: https://live.bilibili.com/" . $room_id,
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
            ]), 10, $cookie, ("https://live.bilibili.com/" . $room_id));
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
            "Origin: https://live.bilibili.com/" . $room_id,
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
        HttpClient::sendGetRequest(self::$config['heartBeat'], [
            "Origin: https://live.bilibili.com/" . $room_id,
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
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
            "Origin: https://live.bilibili.com",
        ], 10, $cookie, ("https://live.bilibili.com/" . $room_id));
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

    /**
     * 添加黑名单用户
     * 
     * @param int $room_id 直播间房间号
     * @param string $cookie 登录凭证
     * @param int $uid 加入黑名单的uid
     * @param string $msg 要禁言的弹幕内容
     * 
     * @return void 
     */
    public static function addSilentUser(int $room_id, string $cookie, int $uid, string $msg): void
    {
        self::init();
        $bili_jct = Processing::getBiliJctFromCookie($cookie);
        // 请求接口
        $addSilentUser = HttpClient::sendPostRequest(self::$config['addSilentUser'], [
            "Origin: https://live.bilibili.com/" . $room_id,
        ], http_build_query([
            'room_id' => $room_id,
            'tuid' => $uid,
            'msg' => $msg,
            'mobile_app' => 'web',
            'csrf_token' => $bili_jct,
            'csrf' => $bili_jct
        ]), 10, $cookie, ("https://live.bilibili.com/" . $room_id));
        if ($addSilentUser['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $addSilentUser['httpStatus'] . "详情：" . json_encode($addSilentUser, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        $jsonData = json_decode($addSilentUser['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['code'])) {
            throw new \Exception("添加黑名单失败, 无法获取数据");
        }
        if ($jsonData['code'] != 0) {
            throw new \Exception("添加黑名单失败, 详情：" . json_encode($jsonData, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
    }

    /**
     * 获取直播间黑名单列表
     * 
     * @param int $room_id 直播间房间号
     * @param string $cookie 登录凭证
     * @param int $page 页码
     * 
     * @return array {total: 总条数`int`, total_page: 总页码`int`, data: 每个黑名单的信息`array`}
     */
    public static function getSilentUserList(int $room_id, string $cookie, int $page): array
    {
        self::init();
        $bili_jct = Processing::getBiliJctFromCookie($cookie);
        // 请求接口
        $getSilentUserList = HttpClient::sendPostRequest(self::$config['getSilentUserList'], [
            "Origin: https://live.bilibili.com/" . $room_id,
        ], http_build_query([
            'room_id' => $room_id,
            'ps' => $page,
            'csrf_token' => $bili_jct,
            'csrf' => $bili_jct
        ]), 10, $cookie, ("https://live.bilibili.com/" . $room_id));
        if ($getSilentUserList['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getSilentUserList['httpStatus'] . "详情：" . json_encode($getSilentUserList, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        $jsonData = json_decode($getSilentUserList['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['code']) || !isset($jsonData['data'])) {
            throw new \Exception("黑名单列表获取失败, 无法获取数据");
        }
        if ($jsonData['code'] != 0) {
            throw new \Exception("黑名单列表获取失败, 详情：" . json_encode($jsonData, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        // 返回数据
        return $jsonData['data'];
    }

    /**
     * 删除直播间黑名单用户
     * 
     * @param int $room_id 直播间房间号
     * @param string $cookie 登录凭证
     * @param int $black_id 黑名单ID
     * 
     * @return void 
     */
    public static function delSilentUser(int $room_id, string $cookie, int $black_id): void
    {
        self::init();
        $bili_jct = Processing::getBiliJctFromCookie($cookie);
        // 请求接口
        $delSilentUser = HttpClient::sendPostRequest(self::$config['delSilentUser'], [
            "Origin: https://live.bilibili.com/" . $room_id,
        ], http_build_query([
            'roomid' => $room_id,
            'id' => $black_id,
            'csrf_token' => $bili_jct,
            'csrf' => $bili_jct
        ]), 10, $cookie, ("https://live.bilibili.com/" . $room_id));
        if ($delSilentUser['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $delSilentUser['httpStatus'] . "详情：" . json_encode($delSilentUser, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
        $jsonData = json_decode($delSilentUser['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['code'])) {
            throw new \Exception("解除黑名单失败, 无法获取数据");
        }
        if ($jsonData['code'] != 0) {
            throw new \Exception("解除黑名单失败, 详情：" . json_encode($jsonData, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES + JSON_PRESERVE_ZERO_FRACTION));
        }
    }
}
