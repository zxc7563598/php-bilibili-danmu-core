<?php

namespace Hejunjie\Bililive;

use Exception;
use Hejunjie\Bililive\Service\HttpClient;
use Hejunjie\Bililive\Service\Processing;

/**
 * 登陆接口处理类
 * @package Hejunjie\Bililive
 */
class Login
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
     * 获取扫描二维码
     * 
     * @return array {url:用以生成二维码的URL`string`, qrcode_key:扫码登录秘钥`string`} 
     * @throws Exception 
     */
    public static function getQrcode(): array
    {
        self::init();
        $qrcodeGenerate = HttpClient::sendGetRequest(self::$config['qrcodeGenerate'], [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ]);
        if ($qrcodeGenerate['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $qrcodeGenerate['httpStatus']);
        }
        $jsonData = json_decode($qrcodeGenerate['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果 JSON 解码失败，记录错误或抛出异常
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        if (!isset($jsonData['data'])) {
            throw new \Exception("接口未返回有效数据");
        }
        return [
            'url' => $jsonData['data']['url'],
            'qrcode_key' => $jsonData['data']['qrcode_key']
        ];
    }

    /**
     * 验证登录信息
     * 
     * 扫码code：0-扫码登录成功，86038-二维码已失效，86090-二维码已扫码未确认，86101-未扫码
     * 
     * @param string $qrcode_key 扫码登录秘钥
     * 
     * @return array {code:扫码code`int`, message:扫码状态信息`string`, data:cookie信息`string`} 
     */
    public static function checkQrcode(string $qrcode_key): array
    {
        self::init();
        $qrcodePoll = HttpClient::sendGetRequest(self::$config['qrcodePoll'] . '?qrcode_key=' . $qrcode_key, [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ]);
        if ($qrcodePoll['httpStatus'] != 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $qrcodePoll['httpStatus']);
        }
        $jsonData = json_decode($qrcodePoll['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果 JSON 解码失败，记录错误或抛出异常
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        // 登陆成功时，根据header头解析cookie
        $cookie = '';
        if (isset($jsonData['data']['code']) && $jsonData['data']['code'] == 0) {
            $getBvid = self::getBvid();
            $cookie = Processing::buildCookieString($qrcodePoll['header'], $jsonData['data']['refresh_token'], $getBvid['buvid3'], $getBvid['buvid4'], $getBvid['b_nut']);
        }
        return [
            'code' => isset($jsonData['data']['code']) ? $jsonData['data']['code'] : '',
            'message' => isset($jsonData['data']['message']) ? $jsonData['data']['message'] : '',
            'cookie' => $cookie
        ];
    }

    /**
     * 获取buvid
     * 
     * @return array {buvid3:buvid3`string`, buvid4:buvid4`string`, b_nut:b_nut`int`} 
     * @throws Exception 
     */
    private static function getBvid(): array
    {
        self::init();
        $getBvid = HttpClient::sendGetRequest(self::$config['getBuvid'], [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36",
            "Origin: https://live.bilibili.com",
        ]);
        if ($getBvid['httpStatus'] !== 200) {
            throw new \Exception('接口异常响应 httpStatus: ' . $getBvid['httpStatus']);
        }
        $data = json_decode($getBvid['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果 JSON 解码失败，记录错误或抛出异常
            throw new \Exception("接口响应了无效的 JSON 数据: " . json_last_error_msg());
        }
        return [
            'buvid3' => isset($data['data']['b_3']) ? $data['data']['b_3'] : '',
            'buvid4' => isset($data['data']['b_4']) ? $data['data']['b_4'] : '',
            'b_nut' => time()
        ];
    }
}
