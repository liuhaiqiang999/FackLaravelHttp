<?php

namespace CodeLiu\HttpClient;

use Psr\Http\Message\ResponseInterface;
use ArrayAccess;
use CodeLiu\HttpClient\Exceptions\RequestException;

/**
 * 响应封装：提供与 Laravel 响应对象类似的友好方法
 *
 * 主要方法：
 * - body() 返回原始字符串
 * - json() 尝试将 body 解析为 JSON（解析失败返回 null）
 * - status() 返回 HTTP 状态码
 * - ok/successful/failed/clientError/serverError 判断状态
 * - header/headers 访问响应头
 * - throw() 在 4xx/5xx 抛出 RequestException（中文信息）
 * - 实现 ArrayAccess，便于像数组一样访问 JSON 字段
 */
class Response implements ArrayAccess
{
    /** @var ResponseInterface PSR-7 响应对象 */
    protected $psrResponse;

    /** @var null|array 解析后的 JSON（如能解析） */
    protected $decodedJson;

    /**
     * 构造函数
     *
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->psrResponse = $response;
        $this->decodedJson = null;
    }

    /**
     * 返回原始响应体字符串
     *
     * @return string
     */
    public function body()
    {
        return (string)$this->psrResponse->getBody();
    }

    /**
     * 尝试解析并返回 JSON（解析失败返回 null）
     *
     * @return array|null
     */
    public function json()
    {
        if ($this->decodedJson === null) {
            $contents          = $this->body();
            $this->decodedJson = json_decode($contents, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->decodedJson = null;
            }
        }
        return $this->decodedJson;
    }

    /**
     * 返回 HTTP 状态码
     *
     * @return int
     */
    public function status()
    {
        return $this->psrResponse->getStatusCode();
    }

    public function ok()
    {
        $s = $this->status();
        return $s >= 200 && $s < 300;
    }

    public function successful()
    {
        return $this->ok();
    }

    public function failed()
    {
        return !$this->successful();
    }

    public function clientError()
    {
        $s = $this->status();
        return $s >= 400 && $s < 500;
    }

    public function serverError()
    {
        $s = $this->status();
        return $s >= 500 && $s < 600;
    }

    /**
     * 获取单个头部值（多值以逗号拼接）
     *
     * @param string $header
     * @return string|null
     */
    public function header($header)
    {
        $vals = $this->psrResponse->getHeader($header);
        return $vals ? implode(', ', $vals) : null;
    }

    /**
     * 获取所有头部
     *
     * @return array
     */
    public function headers()
    {
        return $this->psrResponse->getHeaders();
    }

    /**
     * 如果响应为 4xx/5xx，则抛出 RequestException（中文信息）
     *
     * @return $this
     * @throws RequestException
     */
    public function throw()
    {
        if ($this->clientError() || $this->serverError()) {
            throw new RequestException('HTTP 请求失败，状态码：' . $this->status(), $this->status(), $this);
        }
        return $this;
    }

    // ArrayAccess 接口实现（基于解析后的 JSON）
    public function offsetExists($offset)
    {
        $json = $this->json();
        return is_array($json) && array_key_exists($offset, $json);
    }

    public function offsetGet($offset)
    {
        $json = $this->json();
        if (is_array($json) && array_key_exists($offset, $json)) return $json[$offset];
        return null;
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('响应对象为只读');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('响应对象为只读');
    }
}
