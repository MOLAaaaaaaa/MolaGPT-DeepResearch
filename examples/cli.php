<?php
/**
 * cli.php - 示例CLI调用入口
 * 
 * 该文    // 创建搜索执行器
    $searchExecutor = new SearchExecutor(
        $config['search']['api_key'],
        $logger,
        $config['search']['api_url'],
        $config['reader']['api_url'] ?? null // 可选的内容读取器服务
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
        !empty($config['reader']['api_url']) // 是否配置了内容读取器服务
    );究功能。
 * 使用方法: php cli.php "你的研究问题" [研究深度]
 */

// 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 导入类
use DeepResearch\DeepResearchPipeline;
use DeepResearch\SearchExecutor;
use DeepResearch\AnalysisExecutor;
use DeepResearch\Util\Logger;

// 检查命令行参数
if ($argc < 2) {
    echo "用法: php cli.php \"你的研究问题\" [研究深度]\n";
    echo "例如: php cli.php \"量子计算的最新进展\" 3\n";
    exit(1);
}

// 获取参数
$question = $argv[1];
$depth = isset($argv[2]) ? (int)$argv[2] : 2;

// 创建日志目录
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// 创建日志记录器
$logger = new Logger($logDir . '/cli_research.log');
$logger->info("开始深度研究: {$question}");

try {
    // 加载配置
    $config = require_once __DIR__ . '/../config/config.php';
    
    // 创建搜索执行器
    $searchExecutor = new SearchExecutor(
        $config['search']['api_key'],
        $logger,
        $config['search']['api_url']
    );
    
    // 创建分析执行器 (使用DashScope)
    $analysisExecutor = new AnalysisExecutor(
        $config['analysis_dashscope']['api_key'],
        $config['analysis_dashscope']['api_url'],
        $config['analysis_dashscope']['provider'],
        $config['analysis_dashscope']['model'],
        $logger
    );
    
    // 创建深度研究管道
    $pipeline = new DeepResearchPipeline($searchExecutor, $analysisExecutor, $logger);
    
    // 事件回调函数 - 支持新的事件流机制
    $eventCallback = function($eventType, $data) {
        switch ($eventType) {
            case 'research_start':
                echo "[开始] 深度研究启动，查询: {$data['query']}，深度: {$data['depth']}\n";
                if ($data['has_reader_service']) {
                    echo "[信息] 已启用内容读取器服务\n";
                }
                break;
                
            case 'round_start':
                echo "\n[轮次] === 第 {$data['round']} 轮 / 共 {$data['total_rounds']} 轮 ===\n";
                break;
                
            case 'planning_start':
                echo "[规划] 正在规划第 {$data['round']} 轮使用的工具...\n";
                break;
                
            case 'planning_complete':
                echo "[规划] 决定使用工具: {$data['tool']} -> {$data['target']}\n";
                if (!empty($data['reason'])) {
                    echo "[规划] 原因: {$data['reason']}\n";
                }
                break;
                
            case 'tool_start':
                echo "[工具] 开始执行: {$data['tool']} ({$data['target']})\n";
                break;
                
            case 'tool_output':
                if ($data['tool'] === 'search') {
                    echo "[工具] 搜索完成，找到 {$data['results_count']} 条结果\n";
                } elseif ($data['tool'] === 'read_url') {
                    echo "[工具] URL读取完成，内容长度: {$data['content_length']} 字符\n";
                }
                break;
                
            case 'analysis_start':
                echo "[分析] 开始分析第 {$data['round']} 轮的结果...\n";
                break;
                
            case 'analysis_complete':
                echo "[分析] 第 {$data['round']} 轮分析完成\n";
                break;
                
            case 'round_complete':
                echo "[完成] 第 {$data['round']} 轮研究完成\n";
                break;
                
            case 'final_report_start':
                echo "\n[总结] 开始生成最终研究报告...\n";
                break;
                
            case 'research_complete':
                echo "[完成] 深度研究完成！\n";
                break;
                
            case 'error':
                echo "[错误] {$data['message']}\n";
                break;
                
            default:
                echo "[事件] {$eventType}\n";
                break;
        }
    };
    
    // 执行深度研究
    echo "开始对 \"{$question}\" 进行深度研究 (深度: {$depth})...\n";
    $result = $pipeline->executeResearch($question, $question, $depth, $eventCallback);
    
    // 检查是否有错误
    if (isset($result['error'])) {
        echo "研究过程中出错: " . $result['error'] . "\n";
        exit(1);
    }
    
    // 输出研究结果
    echo "\n============ 深度研究结果 ============\n\n";
    echo $result['analysis'] . "\n";
    
    // 保存结果到文件
    $outputFile = 'research_result_' . date('Ymd_His') . '.txt';
    file_put_contents($outputFile, $result['analysis']);
    echo "\n结果已保存到文件: {$outputFile}\n";
    
    // 输出搜索历史
    echo "\n============ 搜索历史 ============\n\n";
    foreach ($result['search_history'] as $history) {
        echo "第 {$history['round']} 轮: \"{$history['query']}\"\n";
    }
    
    $logger->info("深度研究完成: {$question}");
    
} catch (Exception $e) {
    echo "发生错误: " . $e->getMessage() . "\n";
    $logger->error("深度研究异常: " . $e->getMessage());
    exit(1);
}
