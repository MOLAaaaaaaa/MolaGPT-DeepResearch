<?php
/**
 * config.php - API密钥和URL配置
 * 
 * 该文件包含深度研究功能所需的API密钥和URL配置。
 * 新版本优先从项目根目录的 .env 文件加载配置。
 * 如果 .env 文件不存在或未配置相关项，则会使用此文件中的默认值。
 */

// 引入 Composer 的自动加载器以使用依赖库
require_once __DIR__ . '/../vendor/autoload.php';

// 加载 .env 文件中的环境变量
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // .env 文件不是必需的，如果不存在则忽略
}

return [
    // 搜索API配置
    'search' => [
        'provider' => 'exa',
        'api_key' => $_ENV['EXA_API_KEY'] ?? 'your-exa-api-key', // 替换为实际的Exa API密钥
        'api_url' => 'https://api.exa.ai/search'
    ],
    
    // 分析API配置 - 根据 .env 中的 ANALYSIS_PROVIDER 动态选择
    'analysis' => [
        'provider' => $_ENV['ANALYSIS_PROVIDER'] ?? 'dashscope', // 'dashscope' 或 'openai'
        'api_key' => $_ENV['ANALYSIS_PROVIDER'] === 'openai' 
            ? ($_ENV['OPENAI_API_KEY'] ?? 'your-openai-api-key')
            : ($_ENV['DASHSCOPE_API_KEY'] ?? 'your-dashscope-api-key'),
        'api_url' => $_ENV['ANALYSIS_PROVIDER'] === 'openai'
            ? 'https://api.openai.com/v1/chat/completions'
            : 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
        'model' => $_ENV['ANALYSIS_PROVIDER'] === 'openai'
            ? ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o')
            : ($_ENV['DASHSCOPE_MODEL'] ?? 'qwen-plus-latest')
    ],

    // 工具规划器API配置 (可选)
    'planner' => [
        'api_key' => $_ENV['PLANNER_API_KEY'] ?? null,
        'api_url' => $_ENV['PLANNER_API_URL'] ?? 'https://openrouter.ai/api/v1/chat/completions',
        'model'   => $_ENV['PLANNER_MODEL'] ?? 'nous-hermes-2-mixtral-8x7b-dpo'
    ],

    // 内容读取器服务配置 (可选)
    'reader' => [
        'api_url' => $_ENV['READER_API_URL'] ?? null, // 例如 'http://127.0.0.1:8000/read'
    ],
    
    // 日志配置
    'logging' => [
        'enabled' => filter_var($_ENV['LOG_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'log_file' => __DIR__ . '/../' . ($_ENV['LOG_FILE_PATH'] ?? 'logs/deep_research.log')
    ],
    
    // 深度研究配置
    'research' => [
        'default_depth' => (int)($_ENV['DEFAULT_RESEARCH_DEPTH'] ?? 3),
        'max_depth' => 10
    ]
];
