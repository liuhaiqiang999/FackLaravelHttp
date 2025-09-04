<?php

namespace CodeLiu\HttpClient;

/**
 * 静态门面 (Facade) 类
 *
 * 提供了类似 Laravel Http Facade 的静态调用方法，
 * 使得在任何地方都可以方便地以 Http::get() 的形式发起请求。
 *
 * @method static Client withHeaders(array $headers) 设置请求头（合并）
 * @method static Client withToken(string $token, string $type = 'Bearer') 添加 Authorization Bearer Token
 * @method static Client withBody(string $content, string $contentType = 'text/plain') 设置原始请求体和 Content-Type
 * @method static Client attach(string $name, mixed $contents, string $filename = null) 添加 multipart 字段用于文件上传
 * @method static Client timeout(int|float $seconds) 设置超时时间（秒）
 * @method static Client retry(int $times, int $sleepMs = 0) 设置重试策略
 * @method static Client withOptions(array $options) 直接传递给 Guzzle 的自定义选项
 *
 * @method static Response get(string $url, array $query = []) 发送 GET 请求
 * @method static Response post(string $url, array $data = []) 发送 POST 表单请求 (application/x-www-form-urlencoded)
 * @method static Response json(string $url, array $data = []) 发送 POST JSON 请求 (application/json)
 * @method static Response put(string $url, array $data = []) 发送 PUT JSON 请求
 * @method static Response patch(string $url, array $data = []) 发送 PATCH JSON 请求
 * @method static Response delete(string $url, array $data = []) 发送 DELETE 请求 (可携带 JSON body)
 */
class Http
{
    /**
     * 魔术方法，将静态调用代理到 Client 实例上
     *
     * @param string $method 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public static function __callStatic($method, $arguments)
    {
        $instance = new Client();
        return $instance->{$method}(...$arguments);
    }
}