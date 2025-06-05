<?php

namespace Hejunjie\Bililive\Service;

use Exception;

/**
 * 数据处理类
 * @package Hejunjie\Bililive\Service
 */
class Processing
{

    /**
     * 获取cookie
     * 
     * @param string $header 登录信息请求的header信息
     * @param string $refresh_token refresh_token
     * 
     * @return string cookie信息
     */
    public static function buildCookieString(string $header, string $refresh_token, string $buvid3, string $buvid4, int $b_nut): string
    {
        $headers = [];
        // 解析header数据
        foreach (explode("\r\n", $header) as $line) {
            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line, 2);
                // 检查是否是 "Set-Cookie" 头
                if (strtolower($key) === 'set-cookie') {
                    // 以 "; " 为分隔符将字符串拆分成多个键值对
                    $pairs = explode('; ', $value);
                    foreach ($pairs as $pair) {
                        // 使用 '=' 分隔键和值，并将它们存入数组
                        if (strpos($pair, '=') !== false) {
                            list($key, $value) = explode('=', $pair, 2);
                            $headers[trim($key)] = trim($value);
                        }
                    }
                }
            }
        }
        // 获取 buvid3 / buvid4
        $headers['buvid3'] = $buvid3;
        $headers['buvid4'] = $buvid4;
        $headers['b_nut'] = $b_nut;
        $headers['refresh_token'] = $refresh_token;
        // 使用 array_map 和 implode 组合成所需格式
        $result = implode(";", array_map(
            fn($key, $value) => "{$key}={$value}",
            array_keys($headers),
            $headers
        ));
        return $result;
    }

    /**
     * 通过cookie获取uid
     * 
     * @param string $cookie 用户cookie
     * 
     * @return int uid
     */
    public static function getUidFromCookie(string $cookie): int
    {
        $pairs = explode(";", $cookie);
        $result = [];
        foreach ($pairs as $pair) {
            list($key, $value) = explode("=", $pair, 2); // 用 = 分割键和值
            $result[$key] = $value;
        }
        return isset($result['DedeUserID']) ? $result['DedeUserID'] : 0;
    }

    /**
     * 通过cookie获取用户buvid3
     * 
     * @param string $cookie 用户cookie
     * 
     * @return string buvid3
     */
    public static function getBuvid3FromCookie(string $cookie): string
    {
        $pairs = explode(";", $cookie);
        $result = [];
        foreach ($pairs as $pair) {
            list($key, $value) = explode("=", $pair, 2); // 用 = 分割键和值
            $result[$key] = $value;
        }
        return isset($result['buvid3']) ? $result['buvid3'] : '';
    }

    /**
     * 通过cookie获取用户bili_jct
     * 
     * @param string $cookie 用户cookie
     * 
     * @return string bili_jct
     */
    public static function getBiliJctFromCookie(string $cookie): string
    {
        $pairs = explode(";", $cookie);
        $result = [];
        foreach ($pairs as $pair) {
            list($key, $value) = explode("=", $pair, 2); // 用 = 分割键和值
            $result[$key] = $value;
        }
        return isset($result['bili_jct']) ? $result['bili_jct'] : '';
    }

    /**
     * 根据 cookie 获取 bili_ticket
     * 
     * @param string $bili_jct 
     * @return array 
     * @throws Exception 
     */
    public static function getBiliTicket(string $cookie): array
    {

        $ts = time(); // 当前时间戳
        $key = 'XgwSnGZ1p';
        $message = "ts$ts";
        // 计算 HMAC-SHA256 签名，返回 hex 格式
        $hexSign = hash_hmac('sha256', $message, $key);
        $url = 'https://api.bilibili.com/bapis/bilibili.api.ticket.v1.Ticket/GenWebTicket';
        // 构造查询参数
        $params = http_build_query([
            'key_id' => 'ec02',
            'hexsign' => $hexSign,
            'context[ts]' => $ts,
            'csrf' => self::getBiliJctFromCookie($cookie)
        ]);
        // 初始化 cURL 请求
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "$url?$params",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0'
            ]
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200 || $response === false) {
            throw new Exception("HTTP error! status: $status");
        }
        $data = json_decode($response, true);
        return [
            'bili_ticket' => $data['ticket'],
            'bili_ticket_expires' => $data['created_at'] + $data['ttl'],
        ];
    }

    /**
     * 构建 websocket 头部
     * 
     * @param int $len 正文长度
     * @param int $version 协议版本
     * - 0:普通包 (正文不使用压缩)
     * - 1:心跳及认证包 (正文不使用压缩)
     * - 2:普通包 (正文使用 zlib 压缩)
     * - 3:普通包 (使用 brotli 压缩的多个带文件头的普通包)
     * @param int $operation 操作码
     * - 2:心跳包
     * - 3:心跳包回复 (人气值)
     * - 5:普通包 (命令)
     * - 7:认证包
     * - 8:认证包回复
     * 
     * @return string 头部信息
     */
    public static function buildHeader(int $len, int $version, int $operation): string
    {
        return sprintf(
            '%s%s%s%s%s',
            pack('N', (16 + $len)),
            pack('n', 16),
            pack('n', $version),
            pack('N', $operation),
            pack('N', 1)
        );
    }

    /**
     * websocket 数据解包
     * 
     * @param string $data 
     * 
     * @return array|false {packet_len:整包长度`int`, header_len:头部长度`int`, protocol_version:协议版本`int`, opcode:操作码`int`, magic_number:sequence(不重要)`int`, payload: 正文数据`string`} | bool
     */
    public static function unpack(string $data)
    {
        return @unpack('Npacket_len/nheader_len/nprotocol_version/Nopcode/Nmagic_number/a*payload', $data);
    }

    /**
     * brotli 解压
     * 
     * @param string $data 正文数据
     * 
     * @return array|false []
     * @throws Exception 
     */
    public static function brotli(string $data): array|bool
    {
        if (!function_exists('brotli_uncompress')) {
            throw new \Exception("未安装 brotli 扩展");
        }
        $decompressedBody = brotli_uncompress($data);
        $off = 0;
        $max = strlen(substr($decompressedBody, 16));
        $decode = [];
        while ($off < $max) {
            $jiemi = unpack('Npacket_len/nheader_len/nprotocol_version/Nopcode/Nmagic_number', substr($decompressedBody, $off, 16));
            $unpack = unpack('Npacket_len/nheader_len/nprotocol_version/Nopcode/Nmagic_number/a*payload', substr($decompressedBody, $off, $jiemi['packet_len']));
            $unpack['payload'] = json_decode($unpack['payload'], true);
            $decode[] = $unpack;
            $off += $jiemi['packet_len'];
        }
        return $decode;
    }

    /**
     * zlib 解压
     * 
     * @param string $data 正文数据
     * 
     * @return array|false []
     */
    public static function zlib(string $data)
    {
        $decompressedBody = @gzuncompress($data);
        $off = 0;
        $max = strlen(substr($decompressedBody, 16));
        $decode = [];
        while ($off < $max) {
            $jiemi = unpack('Npacket_len/nheader_len/nprotocol_version/Nopcode/Nmagic_number', substr($decompressedBody, $off, 16));
            $unpack = unpack('Npacket_len/nheader_len/nprotocol_version/Nopcode/Nmagic_number/a*payload', substr($decompressedBody, $off, $jiemi['packet_len']));
            $unpack['payload'] = json_decode($unpack['payload'], true);
            $decode[] = $unpack;
            $off += $jiemi['packet_len'];
        }
        return $decode;
    }

    /**
     * 获取签名数组
     * 
     * @param array $params
     * @param string $imgKey 
     * @param string $subKey 
     * 
     * @return string 
     */
    public static function encWbi(array $params, string $imgKey, string $subKey): string
    {
        $mixinKey = self::getMixinKey($imgKey . $subKey);
        $params['wts'] = time();
        ksort($params);
        $chrFilter = "/[!'()*]/";
        $query = [];
        foreach ($params as $key => $value) {
            $value = preg_replace($chrFilter, '', $value);
            $query[] = urlencode($key) . '=' . urlencode($value);
        }
        $queryStr = implode('&', $query);
        $wRid = md5($queryStr . $mixinKey);
        return $queryStr . '&w_rid=' . $wRid;
    }

    /**
     * 生成 mixin_key
     * 
     * @param string $original img_key拼接sub_key
     * 
     * @return string 
     */
    public static function getMixinKey(string $original): string
    {
        $result = '';
        $mixinKeyEncTab = [
            46,
            47,
            18,
            2,
            53,
            8,
            23,
            32,
            15,
            50,
            10,
            31,
            58,
            3,
            45,
            35,
            27,
            43,
            5,
            49,
            33,
            9,
            42,
            19,
            29,
            28,
            14,
            39,
            12,
            38,
            41,
            13,
            37,
            48,
            7,
            16,
            24,
            55,
            40,
            61,
            26,
            17,
            0,
            1,
            60,
            51,
            30,
            4,
            22,
            25,
            54,
            21,
            56,
            59,
            6,
            63,
            57,
            62,
            11,
            36,
            20,
            34,
            44,
            52
        ];
        foreach ($mixinKeyEncTab as $index) {
            $result .= $original[$index] ?? '';
        }
        return substr($result, 0, 32);
    }

    /**
     * 获取 img_key 和 sub_key
     * 
     * @param string $cookie cookie
     * 
     * @return array 
     */
    public static function getWbiKeys($cookie): array
    {
        $response = json_decode(self::curlGet(
            'https://api.bilibili.com/x/web-interface/nav',
            $cookie
        ), true);
        if (!$response || empty($response['data']['wbi_img'])) {
            throw new Exception('获取 Wbi Key 失败');
        }
        $imgUrl = $response['data']['wbi_img']['img_url'];
        $subUrl = $response['data']['wbi_img']['sub_url'];
        return [
            'img_key' => substr(basename($imgUrl), 0, strpos(basename($imgUrl), '.')),
            'sub_key' => substr(basename($subUrl), 0, strpos(basename($subUrl), '.')),
        ];
    }

    /**
     * 独立GET请求，用于获取 img_key 和 sub_key
     * 
     * @param string $url 
     * @param null|string $cookies 
     * 
     * @return string 
     */
    private static function curlGet(string $url, ?string $cookies = null): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_REFERER => 'https://www.bilibili.com/',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Connection: close',
            ],
        ]);
        if ($cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
