<?php
namespace CodeLiu\HttpClient\Exceptions;

/**
 * 请求异常（例如 HTTP 4xx/5xx 或请求被 Guzzle 拒绝）
 * 包含 $response（如果有）。
 */
class RequestException extends \Exception
{
    /** @var mixed 可能为 \CodeLiu\HttpClient\Response 实例或 null */
    public $response;

    /**
     * 构造函数
     *
     * @param string $message 中文错误信息
     * @param int $code 错误码（可为 HTTP 状态码）
     * @param mixed|null $response Response 实例或 null
     */
    public function __construct($message = "", $code = 0, $response = null)
    {
        parent::__construct($message, $code);
        $this->response = $response;
    }
}
