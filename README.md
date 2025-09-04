# HTTP 客户端使用文档

## 安装

```bash
composer require guzzlehttp/guzzle
```

## 配置 Autoload

在 `composer.json` 中配置 PSR-4 自动加载，将 `CodeLiu\HttpClient` 命名空间指向源代码目录（例如 `src/`）：

```json
{
    "autoload": {
        "psr-4": {
            "CodeLiu\HttpClient\": "src/"
        }
    }
}
```

然后运行以下命令生成 autoload 文件：

```bash
composer dump-autoload
```

## 创建请求

可以通过 `get`、`post`、`put`、`patch` 和 `delete` 方法来创建请求。所有请求方法都会返回一个 `CodeLiu\HttpClient\Response` 实例。

```php
use CodeLiu\HttpClient\Http;

$response = Http::get('https://api.github.com/users/codeliu');
```

## Response 方法

Response 实例提供了大量有用的方法来解析和检查响应：

- `$response->body() : string` - 获取原始响应体字符串
- `$response->json() : array|null` - 将响应体解析为 PHP 数组
- `$response->status() : int` - 获取 HTTP 状态码
- `$response->ok() : bool` - 状态码是否为 2xx
- `$response->successful() : bool` - 状态码是否为 2xx（与 ok() 等价）

### 访问响应数据

```php
// 两种写法等价
$loginName = $response->json()['login'];
$loginName = $response['login'];
```

## 请求数据

### 查询参数

对于 GET 请求，可以将一个数组作为第二个参数传递，它会自动作为 URL 的查询字符串发送：

```php
$response = Http::get('https://httpbin.org/get', [
    'name' => 'Taylor',
    'page' => 1,
]);
```

### Form 表单请求

使用 `post()` 方法发送 `application/x-www-form-urlencoded` 类型的请求：

```php
$response = Http::post('https://httpbin.org/post', [
    'name' => 'Sara',
    'role' => 'Privacy Consultant',
]);
```

### JSON 请求

使用 `json()` 方法发送 `application/json` 类型的请求。`put()`、`patch()` 和 `delete()` 方法也默认发送 JSON 数据：

```php
$response = Http::json('https://httpbin.org/post', [
    'name' => 'Steve',
    'role' => 'Network Administrator',
]);
```

### 原始 Body 请求

使用 `withBody()` 方法可以发送一个原始的请求体，例如 XML 数据：

```php
$xml = '<user><name>John</name></user>';
$response = Http::withBody($xml, 'application/xml')->post('https://httpbin.org/post');
```

### Multipart 请求（文件上传）

使用 `attach()` 方法可以构建 `multipart/form-data` 请求来上传文件：

```php
$response = Http::attach(
    'avatar', file_get_contents('avatar.jpg'), 'user.jpg'
)->post('https://httpbin.org/post', [
    'user_id' => '123', // 也可以同时发送普通字段
]);
```

## 请求头

使用 `withHeaders()` 方法可以为请求添加自定义头信息：

```php
$response = Http::withHeaders([
    'X-Client-Version' => '1.0.0',
    'Accept' => 'application/json'
])->get('https://api.github.com');
```

## 认证

使用 `withToken()` 方法可以方便地添加 Authorization 头：

```php
$response = Http::withToken('token')->get('https://api.github.com/user');
```

使用 `withBasicAuth()` 方法进行 Basic 认证：

```php
$response = Http::withBasicAuth('taylor@laravel.com', 'secret')->get('https://api.github.com/user');
```

使用 `withDigestAuth()` 方法进行 Digest 认证：

```php
$response = Http::withDigestAuth('taylor', 'secret')->get('https://httpbin.org/digest-auth/auth/user/pass');
```

## 超时设置

使用 `timeout()` 方法设置请求超时时间（秒）：

```php
$response = Http::timeout(3)->get('https://httpbin.org/delay/5');
```

使用 `connectTimeout()` 方法设置连接超时时间（秒）：

```php
$response = Http::connectTimeout(3)->get('https://httpbin.org/delay/5');
```

## 重试机制

使用 `retry()` 方法设置失败重试：

```php
$response = Http::retry(3, 100)->get('https://httpbin.org/status/500');
```

## 代理设置

### 1. 为 HTTP 和 HTTPS 设置同一个代理

```php
$response = Http::withProxy('http://user:pass@host:port')
                ->get('https://httpbin.org/ip');
```

### 2. 使用数组为 HTTPS 请求设置隧道代理，并排除特定域名

```php
$response = Http::withProxy([
    'https' => 'http://user:pass@https-proxy.com:8080',
    'no' => ['.mit.edu', 'foo.com']
])->get('https://httpbin.org/ip');
```

## 错误处理

HTTP 客户端不会在遇到 4xx 或 5xx 状态码时抛出异常，但可以使用以下方法检查请求是否成功：

```php
if ($response->successful()) {
    // 请求成功
}

if ($response->failed()) {
    // 请求失败
}

if ($response->clientError()) {
    // 客户端错误 (4xx)
}

if ($response->serverError()) {
    // 服务器错误 (5xx)
}
```

如果需要抛出异常，可以使用 `throw()` 方法：

```php
$response = Http::get('https://httpbin.org/status/500')->throw();
```

也可以使用 `throwIf()` 方法有条件地抛出异常：

```php
$response = Http::get('https://httpbin.org/status/500')->throwIf($condition);
```

### 异常处理示例

```php
try {
    $response = Http::get('https://httpbin.org/status/500')->throw();
} catch (\Exception $e) {
    echo "请求失败，状态码：" . $e->response->status();
}
```

## Guzzle 选项

为了获得最大的灵活性，可以使用 `withOptions()` 方法传递任何 Guzzle 支持的请求选项：

```php
$response = Http::withOptions([
    'debug' => true,      // 开启 Guzzle 的调试模式
    'version' => '2.0'    // 指定 HTTP 协议版本
])->get('https://httpbin.org/get');
```

## 总结

这个 HTTP 客户端提供了简洁易用的 API，支持各种 HTTP 请求类型、认证方式、超时设置、重试机制和代理配置。通过链式调用方法，可以轻松构建复杂的 HTTP 请求。
