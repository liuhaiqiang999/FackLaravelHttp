<?php

// 假设你的类文件都已通过 Composer 的 autoload 加载
// 如果没有，你需要手动 require_once
require_once __DIR__ . '/vendor/autoload.php';

use CodeLiu\HttpClient\Http;
use CodeLiu\HttpClient\Exceptions\ConnectionException;
use CodeLiu\HttpClient\Exceptions\RequestException;

// 为了方便演示，定义一个打印函数
function print_response($title, $callback)
{
    echo "========================================\n";
    echo "🚀 " . $title . "\n";
    echo "========================================\n";
    try {
        $callback();
    } catch (ConnectionException $e) {
        echo "❌ 网络连接异常: " . $e->getMessage() . "\n";
    } catch (RequestException $e) {
        echo "❌ HTTP 请求失败 (RequestException)\n";
        echo "   - 消息: " . $e->getMessage() . "\n";
        echo "   - 状态码: " . $e->getCode() . "\n";
        if ($e->response) {
            echo "   - 响应体: " . $e->response->body() . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ 发生未知异常: " . $e->getMessage() . "\n";
    }
    echo "\n\n";
}


// -------------------- 开始演示 --------------------

// 1. 基本的 GET 请求
print_response('基本的 GET 请求', function () {
    $response = Http::get('https://httpbin.org/get', ['foo' => 'bar', 'user_id' => 123]);

    echo "请求成功! 状态码: " . $response->status() . "\n";
    // 使用 json() 方法获取解析后的数组
    $data = $response->json();
    echo "请求的 URL 参数 'foo': " . $data['args']['foo'] . "\n";
});


// 2. 发送 POST 表单 (x-www-form-urlencoded)
print_response('发送 POST 表单', function () {
    $response = Http::post('https://httpbin.org/post', [
        'name'    => 'CodeLiu',
        'project' => 'HttpClient'
    ]);

    echo "请求成功! 状态码: " . $response->status() . "\n";
    $data = $response->json();
    echo "返回的表单字段 'name': " . $data['form']['name'] . "\n";
});


// 3. 发送 POST JSON 数据
print_response('发送 POST JSON 数据并携带自定义 Header', function () {
    $response = Http::withHeaders([
        'X-Client-Version' => '1.0.0',
        'Accept'           => 'application/json'
    ])->json('https://httpbin.org/post', [
        'id'   => 1,
        'tags' => ['php', 'guzzle']
    ]);

    echo "请求成功! 状态码: " . $response->ok() . "\n"; // ok() 是 status() >= 200 && < 300 的简写
    $data = $response->json();
    echo "返回的 JSON 字段 'id': " . $data['json']['id'] . "\n";
    echo "收到的自定义 Header 'X-Client-Version': " . $data['headers']['X-Client-Version'] . "\n";

    // Response 对象实现了 ArrayAccess, 可以像数组一样访问 JSON 字段
    echo "通过 ArrayAccess 访问 'tags': " . implode(', ', $response['json']['tags']) . "\n";
});


// 4. 发送 PUT / PATCH / DELETE 请求
print_response('发送 PUT 请求', function () {
    // 假设你已经在 Client.php 中添加了 put() 方法
    $response = Http::put('https://httpbin.org/put', ['status' => 'updated']);
    echo "PUT 请求成功! 返回的 JSON: " . $response->body() . "\n";
});

print_response('发送 DELETE 请求', function () {
    // 假设你已经在 Client.php 中添加了 delete() 方法
    $response = Http::delete('https://httpbin.org/delete');
    echo "DELETE 请求成功! 状态码: " . $response->status() . "\n";
});


// 5. 上传文件 (multipart/form-data)
print_response('上传文件（同时附带普通字段）', function () {
    // 确保当前目录下有一个 avatar.jpg 文件
    if (!file_exists('avatar.jpg')) {
        file_put_contents('avatar.jpg', 'fake-image-data');
    }

    $response = Http::attach('avatar', file_get_contents('avatar.jpg'), 'user_avatar.jpg')
        ->post('https://httpbin.org/post', [
            'user_id'     => '456',
            'description' => 'A test avatar'
        ]);

    if ($response->successful()) { // successful() 是 ok() 的别名
        $data = $response->json();
        echo "文件上传成功!\n";
        echo "普通字段 'user_id': " . $data['form']['user_id'] . "\n";
        echo "上传的文件信息: " . $data['files']['avatar'] . "\n";
    }
});


// 6. 发送原始 Body (例如 XML 或其他文本)
print_response('发送 XML 请求', function () {
    $xml = '<request><id>123</id></request>';

    $response = Http::withBody($xml, 'application/xml')
        ->post('https://httpbin.org/post');

    $data = $response->json();
    echo "XML 发送成功!\n";
    echo "服务器收到的 Content-Type: " . $data['headers']['Content-Type'] . "\n";
    echo "服务器收到的原始 Body: " . $data['data'] . "\n";
});


// 7. 使用 Token 认证
print_response('使用 Bearer Token 认证', function () {
    $response = Http::withToken('your-secret-jwt-token')
        ->get('https://httpbin.org/headers');

    echo "返回的 Authorization Header: " . $response->json()['headers']['Authorization'] . "\n";
});


// 8. 超时和重试机制
print_response('测试超时（设置为极短时间）', function () {
    // httpbin.org/delay/5 会延迟5秒响应
    $response = Http::timeout(2)->get('https://httpbin.org/delay/5');
    // 这段代码通常不会执行，因为会在 2 秒后抛出异常
});

print_response('测试重试机制（访问一个 503 服务）', function () {
    echo "将尝试请求 1 次，然后重试 2 次 (总计 3 次)...\n";
    $response = Http::retry(2, 500) // 重试2次，每次间隔500ms
    ->get('https://httpbin.org/status/503');

    if ($response->serverError()) {
        echo "请求最终失败，状态码: " . $response->status() . " (这是预期的)\n";
    }
});


// 9. 自动抛出异常
print_response('处理 4xx/5xx 错误（使用 throw() 方法）', function () {
    echo "正在请求一个 404 页面...\n";
    // .throw() 方法会在遇到 4xx 或 5xx 响应时自动抛出 RequestException
    $response = Http::get('https://httpbin.org/status/404')->throw();

    // 如果没有抛出异常（例如状态码是 200），则会执行这里
    echo "请求成功了（这不应该发生）\n";
});