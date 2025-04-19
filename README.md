# Deep Research Core

一个用于执行多轮搜索与深度分析的 PHP 库，解耦合自我的现有项目。

## 功能特点

- **多轮迭代搜索**：支持基于前一轮分析结果自动生成下一轮搜索关键词
- **专家级分析**：对搜索结果进行深入分析，提取关键信息并生成洞见
- **灵活的提供商支持**：支持多种搜索和分析API提供商（默认使用Exa搜索和DashScope/OpenAI分析）
- **完整的进度反馈**：提供详细的进度回调，方便集成到各种应用场景
- **简洁的API设计**：易于集成到现有PHP项目中

## 详细说明

MolaGPT-DeepResearch 是一个基于 PHP 的函数调用研究引擎，旨在通过迭代的搜索与分析流程，生成高质量的研究报告。用户首先提供初始查询（例如“量子计算的最新进展”），该查询作为第一轮搜索的关键字。在每一轮中，系统会调用搜索执行器（SearchExecutor）从指定的 API 获取搜索结果，然后通过分析执行器（AnalysisExecutor）对这些结果进行深度分析，提取关键信息并生成洞见。分析结果会更新为下一轮的上下文，并可生成进一步的关键字。所有迭代的分析结果最终整合为一份研究报告，包含关键信息汇总、来自多个来源的对比分析，以及对主题的整体见解。

## 安装

### 要求

- PHP 7.4 或更高版本
- Composer

### 通过Composer安装

```bash
composer require molagpt/deep-research-core
```

## 快速开始

### 基本用法

```php
<?php
// 引入自动加载
require_once 'vendor/autoload.php';

// 导入类
use DeepResearch\DeepResearchPipeline;
use DeepResearch\SearchExecutor;
use DeepResearch\AnalysisExecutor;
use DeepResearch\Util\Logger;

// 创建日志记录器
$logger = new Logger('deep_research.log');

// 创建搜索执行器
$searchExecutor = new SearchExecutor(
    'your-exa-api-key',
    $logger
);

// 创建分析执行器
$analysisExecutor = new AnalysisExecutor(
    'your-analysis-api-key',
    'https://api.openai.com/v1/chat/completions',
    'dashscope', // 或 'openai'
    'model-name',
    $logger
);

// 创建深度研究管道
$pipeline = new DeepResearchPipeline($searchExecutor, $analysisExecutor, $logger);

// 进度回调函数
$progressCallback = function($message) {
    echo "[进度] " . $message . PHP_EOL;
};

// 执行深度研究
$result = $pipeline->executeResearch(
    '量子计算的最新进展', // 初始查询
    '量子计算的最新进展及其在密码学中的应用', // 原始问题（上下文）
    3, // 研究深度（轮次）
    $progressCallback
);

// 输出研究结果
echo $result['analysis'];
```

### 配置文件方式

```php
<?php
// 引入自动加载
require_once 'vendor/autoload.php';

// 导入类
use DeepResearch\DeepResearchPipeline;
use DeepResearch\SearchExecutor;
use DeepResearch\AnalysisExecutor;
use DeepResearch\Util\Logger;

// 加载配置
$config = require_once 'config/config.php';

// 创建日志记录器
$logger = new Logger($config['logging']['log_file']);

// 创建搜索执行器
$searchExecutor = new SearchExecutor(
    $config['search']['api_key'],
    $logger,
    $config['search']['api_url']
);

// 创建分析执行器
$analysisExecutor = new AnalysisExecutor(
    $config['analysis_dashscope']['api_key'],
    $config['analysis_dashscope']['api_url'],
    $config['analysis_dashscope']['provider'],
    $config['analysis_dashscope']['model'],
    $logger
);

// 创建深度研究管道
$pipeline = new DeepResearchPipeline($searchExecutor, $analysisExecutor, $logger);

// 执行深度研究
$result = $pipeline->executeResearch('量子计算的最新进展', '量子计算的最新进展', 3);

// 输出研究结果
echo $result['analysis'];
```

## 项目结构

```
deep-research-core/
├── src/
│   ├── DeepResearchPipeline.php       # 多轮搜索与分析主类
│   ├── SearchExecutor.php             # 搜索封装
│   ├── AnalysisExecutor.php           # 分析模型封装
│   ├── DTO/
│   │   ├── SearchResult.php           # 搜索结果数据传输对象
│   │   └── AnalysisResult.php         # 分析结果数据传输对象
│   └── Util/
│       └── Logger.php                 # 日志
│
├── config/
│   └── config.php                     # API 密钥和 URL 配置
│
├── public/
│   └── api.php                        # 示例 HTTP API
│
├── examples/
│   └── cli.php                        # 示例 CLI
│
├── .env.example                       # 变量
├── composer.json                      
└── README.md                          
```

## 配置

### 配置文件

配置文件位于`config/config.php`，包含以下配置项：

```php
return [
    // 搜索API配置
    'search' => [
        'provider' => 'exa',
        'api_key' => 'your-exa-api-key',
        'api_url' => 'https://api.exa.ai/search'
    ],
    
    // 分析API配置 - DashScope
    'analysis_dashscope' => [
        'provider' => 'dashscope',
        'api_key' => 'your-dashscope-api-key',
        'api_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
        'model' => 'qwen-plus-latest'
    ],
    
    // 分析API配置 - OpenAI
    'analysis_openai' => [
        'provider' => 'openai',
        'api_key' => 'your-openai-api-key',
        'api_url' => 'https://api.openai.com/v1/chat/completions',
        'model' => 'gpt-4o'
    ],
    
    // 日志配置
    'logging' => [
        'enabled' => true,
        'log_file' => __DIR__ . '/../logs/deep_research.log'
    ],
    
    // 深度研究配置
    'research' => [
        'default_depth' => 3,
        'max_depth' => 10
    ]
];
```

### 环境变量

也可以使用环境变量进行配置，复制`.env.example`为`.env`并填入实际的API密钥和URL。

## 示例

### CLI示例

```bash
php examples/cli.php "量子计算的最新进展" 3
```

### HTTP API示例

启动PHP内置服务器：

```bash
php -S localhost:8000 -t public/
```

发送POST请求：

```bash
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{"query":"量子计算的最新进展","depth":3}'
```

## 扩展

### 自定义搜索提供商

可以通过继承`SearchExecutor`类来实现自定义搜索提供商：

```php
class CustomSearchExecutor extends \DeepResearch\SearchExecutor
{
    // 重写executeSearch方法
    public function executeSearch(string $query, bool $isDeepResearch = false): \DeepResearch\DTO\SearchResult
    {
        // 实现自定义搜索逻辑
    }
}
```

### 自定义分析提供商

可以通过继承`AnalysisExecutor`类来实现自定义分析提供商：

```php
class CustomAnalysisExecutor extends \DeepResearch\AnalysisExecutor
{
    // 重写analyzeResults方法
    public function analyzeResults(
        \DeepResearch\DTO\SearchResult $searchResult,
        string $question,
        ?string $previousAnalysis = null,
        ?callable $progressCallback = null,
        int $currentRound = 1,
        int $totalRounds = 2,
        array $searchHistory = []
    ): \DeepResearch\DTO\AnalysisResult
    {
        // 实现自定义分析逻辑
    }
}
```

## 许可证

MIT

## 贡献

这是我的第一个项目，可能有很多 bug，欢迎提交问题和拉取请求！
