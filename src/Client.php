<?php

namespace CodeLiu\HttpClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use CodeLiu\HttpClient\Response as HttpResponse;
use CodeLiu\HttpClient\Exceptions\ConnectionException;
use CodeLiu\HttpClient\Exceptions\RequestException;

/**
 * 通用 HTTP 客户端（框架无关）
 */
class Client
{
    /** @var GuzzleClient 内部 Guzzle 实例 */
    protected $guzzle;

    /** @var array 全局 Guzzle 选项（构造时传入或通过 withOptions 更新） */
    protected $options = [];

    // 每次请求的临时状态（发送后 reset）
    protected $headers      = [];
    protected $token        = null;
    protected $body         = null; // 原始 body
    protected $multipart    = [];
    protected $timeout      = 3;    // 默认3秒超时
    protected $retryTimes   = 0;    // 重试次数（不包含首次请求）
    protected $retrySleepMs = 0;    // 重试间隔（毫秒）
    protected $proxy        = null;

    /**
     * 构造函数
     *
     * @param array $options 可选的 Guzzle 构造选项（如 base_uri, handler 等）
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->guzzle  = new GuzzleClient($this->options);
    }

    // -------------------- 链式设置方法 --------------------

    /**
     * 设置请求头（合并）
     *
     * @param array $headers 键值对
     * @return $this
     */
    public function withHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withProxy($proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * 添加 Authorization: Bearer <token>（或自定义类型）
     *
     * @param string $token
     * @param string $type
     * @return $this
     */
    public function withToken($token, $type = 'Bearer')
    {
        $this->token                    = $token;
        $this->headers['Authorization'] = $type . ' ' . $token;
        return $this;
    }

    /**
     * 设置原始 body 与 Content-Type（例如发送二进制文件）
     *
     * @param string $content
     * @param string $contentType
     * @return $this
     */
    public function withBody($content, $contentType = 'text/plain')
    {
        $this->body                    = $content;
        $this->headers['Content-Type'] = $contentType;
        return $this;
    }

    /**
     * 添加 multipart 字段（用于文件上传）
     *
     * @param string $name 字段名
     * @param mixed $contents 文件内容或资源流
     * @param string|null $filename 可选文件名
     * @return $this
     */
    public function attach($name, $contents, $filename = null)
    {
        $item = ['name' => $name, 'contents' => $contents];
        if ($filename) $item['filename'] = $filename;
        $this->multipart[] = $item;
        return $this;
    }

    /**
     * 设置超时时间（秒）
     *
     * @param int|float $seconds
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * 设置重试策略（简单实现）
     *
     * @param int $times 重试次数（不包含首次）
     * @param int $sleepMs 每次重试前等待毫秒数
     * @return $this
     */
    public function retry($times, $sleepMs = 0)
    {
        $this->retryTimes   = (int)$times;
        $this->retrySleepMs = (int)$sleepMs;
        return $this;
    }

    /**
     * 直接传递给 Guzzle 的自定义选项（会重新创建内部 Guzzle 实例以生效）
     *
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
        // 重新创建 Guzzle 实例以让新选项立即生效
        $this->guzzle = new GuzzleClient($this->options);
        return $this;
    }

    // -------------------- HTTP 动词 --------------------
    // GET 请求
    public function get($url, $query = [])
    {
        return $this->send('GET', $url, ['query' => $query]);
    }

    // POST 表单
    public function post($url, $data = [])
    {
        return $this->send('POST', $url, ['data' => $data, 'asJson' => false]);
    }

    // POST JSON
    public function json($url, $data = [])
    {
        return $this->send('POST', $url, ['data' => $data, 'asJson' => true]);
    }

    // PUT JSON
    public function put($url, $data = [])
    {
        return $this->send('PUT', $url, ['data' => $data, 'asJson' => true]);
    }

    // PATCH JSON
    public function patch($url, $data = [])
    {
        return $this->send('PATCH', $url, ['data' => $data, 'asJson' => true]);
    }

    // DELETE (可以带 JSON body)
    public function delete($url, $data = [])
    {
        return $this->send('DELETE', $url, ['data' => $data, 'asJson' => true]);
    }



    // -------------------- 内部方法 --------------------

    /**
     * 根据链式设置构建传给 Guzzle 的选项
     *
     * @param string $method
     * @param array $extras ['query' => [], 'data' => []]
     * @return array
     */
    protected function buildOptions(string $method, array $extras = []): array
    {
        $opts = $this->options;

        // 1. 超时
        if ($this->timeout !== null) {
            $opts['timeout'] = $this->timeout;
        }

        if ($this->proxy !== null) {
            $opts['proxy'] = $this->proxy;
        }

        // 2. Headers
        if (!empty($this->headers)) {
            $opts['headers'] = array_merge($opts['headers'] ?? [], $this->headers);
        }

        // 3. multipart
        if (!empty($this->multipart)) {
            $opts['multipart'] = $this->multipart;
        }

        // 4. body（原始 body）
        if ($this->body !== null) {
            $opts['body'] = $this->body;
        }

        // 5. query
        if (!empty($extras['query'])) {
            $opts['query'] = $extras['query'];
        }

        // 6. data（表单或 JSON）
        if (!empty($extras['data'])) {
            $data = $extras['data'];

            // 优先级：multipart > body > form > json
            if (!empty($this->multipart)) {
                // 将普通字段附加到 multipart
                foreach ($data as $k => $v) {
                    $opts['multipart'][] = [
                        'name'     => $k,
                        'contents' => is_scalar($v) ? (string)$v : json_encode($v),
                    ];
                }
            } elseif (isset($opts['body'])) {
                // body 已设置，不覆盖
            } elseif (!empty($extras['asJson'])) {
                $opts['json'] = $data;
            } elseif ($method === 'POST') {
                $opts['form_params'] = $data;
            }
        }

        return $opts;
    }


    /**
     * 发送完成后重置请求级临时状态
     */
    protected function resetState()
    {
        $this->headers      = [];
        $this->token        = null;
        $this->body         = null;
        $this->multipart    = [];
        $this->timeout      = null;
        $this->retryTimes   = 0;
        $this->retrySleepMs = 0;
        $this->proxy        = null; // +++ 4. 重置代理状态 +++

    }

    protected function send(string $method, string $url, array $extras = []): HttpResponse
    {
        $attempts = max(1, $this->retryTimes + 1); // 包含首次请求
        $lastEx   = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                // 调用 buildOptions 时传入 method
                $opts = $this->buildOptions($method, $extras);

                // 发送请求
                $res = $this->guzzle->request($method, $url, $opts);

                $response = new HttpResponse($res);

                // 5xx 错误重试
                if ($response->serverError() && $i < $attempts - 1) {
                    if ($this->retrySleepMs) usleep($this->retrySleepMs * 1000);
                    continue;
                }

                $this->resetState();
                return $response;

            } catch (GuzzleRequestException $e) {
                $lastEx = $e;

                if ($i < $attempts - 1 && $this->retrySleepMs) {
                    usleep($this->retrySleepMs * 1000);
                    continue;
                }

                // 网络层错误
                $ctx = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : null;
                if ($ctx && isset($ctx['errno'])) {
                    throw new ConnectionException('网络连接异常：' . $e->getMessage(), $e->getCode());
                }

                $resp     = $e->getResponse();
                $response = $resp ? new HttpResponse($resp) : null;
                throw new RequestException('HTTP 请求失败：' . $e->getMessage(), $e->getCode(), $response);

            } catch (\Exception $e) {
                $lastEx = $e;
                if ($i < $attempts - 1 && $this->retrySleepMs) {
                    usleep($this->retrySleepMs * 1000);
                    continue;
                }
                throw new \Exception('请求过程中发生异常：' . $e->getMessage(), $e->getCode());
            }
        }

        // 最后的兜底
        if ($lastEx) throw $lastEx;
        throw new \Exception('发送请求时发生未知错误');
    }

}
