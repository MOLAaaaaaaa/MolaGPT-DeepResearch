<?php
/**
 * api.php - 示例HTTP API接口入口
 * 
 * 该文件展示了如何创建一个HTTP API接口来提供深度研究功能。
 * 使用方法: 通过POST请求访问此文件，提供query和depth参数。
 */

// 设置响应头
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求（用于CORS预检）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "data: " . json_encode(['error' => '只支持POST请求方法']) . "\n\n";
    exit;
}

// 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 导入类
use DeepResearch\DeepResearchPipeline;
use DeepResearch\SearchExecutor;
use DeepResearch\AnalysisExecutor;
use DeepResearch\Util\Logger;

try {
    // 获取请求体
    $requestBody = file_get_contents('php://input');
    $requestData = json_decode($requestBody, true);
    
    // 验证请求参数
    if (!isset($requestData['query']) || empty($requestData['query'])) {
        http_response_code(400);
        echo "data: " . json_encode(['error' => '缺少必要参数: query']) . "\n\n";
        exit;
    }
    
    $query = $requestData['query'];
    $depth = isset($requestData['depth']) ? (int)$requestData['depth'] : 2;
    $rounds = isset($requestData['rounds']) ? (int)$requestData['rounds'] : $depth; // 支持rounds参数
    $focus = isset($requestData['focus']) ? $requestData['focus'] : '';
    
    // 支持前端传递的配置
    $frontendConfig = isset($requestData['config']) ? $requestData['config'] : [];
    
    // 创建日志目录
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // 创建日志记录器
    $logger = new Logger($logDir . '/api_research.log');
    $logger->info("API请求: {$query}, 深度: {$depth}");
    
    // 加载配置
    $config = require_once __DIR__ . '/../config/config.php';
    
    // 如果前端提供了配置，则优先使用前端配置
    if (!empty($frontendConfig)) {
        if (!empty($frontendConfig['exaApiKey'])) {
            $config['search']['api_key'] = $frontendConfig['exaApiKey'];
        }
        if (!empty($frontendConfig['analysisProvider'])) {
            $config['analysis']['provider'] = $frontendConfig['analysisProvider'];
        }
        if (!empty($frontendConfig['dashscopeApiKey']) && $config['analysis']['provider'] === 'dashscope') {
            $config['analysis']['api_key'] = $frontendConfig['dashscopeApiKey'];
        }
        if (!empty($frontendConfig['openaiApiKey']) && $config['analysis']['provider'] === 'openai') {
            $config['analysis']['api_key'] = $frontendConfig['openaiApiKey'];
        }
        if (!empty($frontendConfig['plannerApiKey'])) {
            $config['planner']['api_key'] = $frontendConfig['plannerApiKey'];
        }
        if (!empty($frontendConfig['readerApiUrl'])) {
            $config['reader']['api_url'] = $frontendConfig['readerApiUrl'];
        }
        
        // 添加模型配置支持
        if (!empty($frontendConfig['analysisModel'])) {
            $config['analysis']['model'] = $frontendConfig['analysisModel'];
        }
        if (!empty($frontendConfig['plannerModel'])) {
            $config['planner']['model'] = $frontendConfig['plannerModel'];
        }
    }
    
    // 创建搜索执行器
    $searchExecutor = new SearchExecutor(
        $config['search']['api_key'],
        $logger,
        $config['search']['api_url'],
        $config['reader']['api_url'] ?? null
    );
    
    // 创建分析执行器
    $analysisExecutor = new AnalysisExecutor(
        $config['analysis']['api_key'],
        $config['analysis']['api_url'],
        $config['analysis']['provider'],
        $config['analysis']['model'],
        $logger,
        $config['planner']['api_key'] ?? null,
        $config['planner']['api_url'] ?? null,
        $config['planner']['model'] ?? null
    );
    
    // 创建深度研究管道
    $pipeline = new DeepResearchPipeline(
        $searchExecutor, 
        $analysisExecutor, 
        $logger,
        !empty($config['reader']['api_url'])
    );
    
    // 事件回调函数 - 改为流式输出
    $eventCallback = function($eventType, $data) {
        $event = [
            'type' => $eventType,
            'message' => is_string($data) ? $data : json_encode($data),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo "data: " . json_encode($event) . "\n\n";
        flush();
        if (ob_get_level()) {
            ob_flush();
        }
    };
    
    // 执行深度研究
    $finalQuery = !empty($focus) ? "{$query} (重点关注: {$focus})" : $query;
    $result = $pipeline->executeResearch($finalQuery, $query, $rounds, $eventCallback);
    
    // 检查是否有错误
    if (isset($result['error'])) {
        $errorEvent = [
            'type' => 'error',
            'message' => $result['error'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo "data: " . json_encode($errorEvent) . "\n\n";
        flush();
        exit;
    }
    
    // 发送完成事件
    $completeEvent = [
        'type' => 'complete',
        'data' => $result['analysis'] ?? '研究完成',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo "data: " . json_encode($completeEvent) . "\n\n";
    flush();
    
    $logger->info("API请求完成: {$query}");
    
} catch (Exception $e) {
    $logger->error("API异常: " . $e->getMessage());
    
    $errorEvent = [
        'type' => 'error', 
        'message' => '服务器内部错误: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo "data: " . json_encode($errorEvent) . "\n\n";
    flush();
}
