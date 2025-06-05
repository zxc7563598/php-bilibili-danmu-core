<?php

namespace Hejunjie\Bililive\Service;

/**
 * 网络处理类
 * @package Hejunjie\Bililive\Service
 */
class HttpClient
{

    private static $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ];
    /**
     * 使用 cURL 发送 GET 请求
     * 
     * @param string $url URL地址
     * @param array $headers header数组
     * @param int $timeout 超时时间（秒）
     * @param string $cookie cookie
     * 
     * @return array {httpStatus:Http Status 状态码`int`, data:返回内容`string`, header:返回header`string`} 
     * @throws Exception
     */
    public static function sendGetRequest(string $url, array $headers = [], int $timeout = 10, string $cookie = '', string $referer = 'https://www.bilibili.com/'): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('无效的 URL: ' . $url);
        }
        $ch = curl_init();
        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // 设置请求头
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$userAgents[array_rand(self::$userAgents)]);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE); // 获取头的大小
        // 获取 HTTP 状态码
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // 检查 cURL 是否发生错误
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL 错误: ' . $errorMsg);
        }
        curl_close($ch);
        // 分离头部和主体
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        // 返回结构化结果
        return [
            'httpStatus' => $httpStatus,
            'data' => $body,
            'header' => $header
        ];
    }

    /**
     * 使用 cURL 发送 POST 请求
     * 
     * @param string $url URL地址
     * @param array $headers header数组
     * @param mixed $data 请求数据
     * @param int $timeout 超时时间（秒）
     * @param string $cookie cookie
     * 
     * @return array {httpStatus:Http Status 状态码`int`, data:返回内容`string`, header:返回header`string`} 
     * @throws Exception
     */
    public static function sendPostRequest(string $url, array $headers = [], mixed $data = null, int $timeout = 10, string $cookie = '', string $referer = 'https://www.bilibili.com/'): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('无效的 URL: ' . $url);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // 设置请求头
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$userAgents[array_rand(self::$userAgents)]);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        // 设置请求数据
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE); // 获取头的大小
        // 获取 HTTP 状态码
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // 检查 cURL 是否发生错误
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL 错误: ' . $errorMsg);
        }
        curl_close($ch);
        // 分离头部和主体
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        // 返回结构化结果
        return [
            'httpStatus' => $httpStatus,
            'data' => $body,
            'header' => $header
        ];
    }
}
