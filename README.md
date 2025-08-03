# Deep Research Core

一个用于执行多轮搜索与深度分析的 PHP 库，基于智能工具规划的研究引擎。

## 🚀 功能特点

- **🔄 多轮迭代研究**：支持基于前一轮分析结果自动生成下一轮研究策略
- **🧠 智能工具规划**：AI驱动的工具选择，自动决定使用搜索或URL内容提取
- **🔍 双重信息源**：结合网络搜索和直接URL内容提取，获得更全面的信息
- **⚡ 流式事件机制**：实时进度反馈，支持构建交互式用户界面
- **🎨 Web Demo界面**：提供完整的交互式演示页面，零配置快速体验
- **🔌 灵活的提供商支持**：支持多种AI提供商（DashScope、OpenAI、OpenRouter等）
- **🔒 安全配置管理**：基于.env文件的安全API密钥管理
- **📦 模块化设计**：清晰的职责分离，易于扩展和维护

## 📋 详细说明

MolaGPT-DeepResearch 是一个基于 PHP 的智能研究引擎，通过多轮迭代和工具规划来生成高质量的研究报告。

**核心流程：**
1. **工具规划**：AI分析查询并选择最适合的工具（网络搜索 vs URL提取）
2. **信息采集**：执行选定的工具获取相关信息
3. **专家分析**：对获取的信息进行深度分析和洞察提取
4. **上下文更新**：基于分析结果更新研究上下文
5. **迭代优化**：重复上述过程，逐步深化研究
6. **报告生成**：整合所有轮次的发现，生成结构化研究报告

## 📦 安装

### 系统要求

- PHP 7.4 或更高版本
- Composer
- cURL 扩展
- （可选）Docker - 用于运行内容提取服务

### 通过Composer安装

```bash
composer require molagpt/deep-research-core
```

## ⚙️ 配置

### 1. 环境变量配置

将 `.env.example` 复制为 `.env` 并配置您的API密钥：

```bash
cp .env.example .env
```

编辑 `.env` 文件：

```env
# 必需配置
EXA_API_KEY="your-exa-api-key"
ANALYSIS_PROVIDER="dashscope"
DASHSCOPE_API_KEY="your-dashscope-api-key"

# 可选配置 - 启用高级工具规划
PLANNER_API_KEY="your-openrouter-api-key"

# 可选配置 - 启用URL内容提取
READER_API_URL="http://127.0.0.1:8000/read"
```

### 2. 可选：启用内容提取服务

如果您想使用"读取URL"功能，可以运行我们提供的Docker服务：

```bash
cd services
docker-compose up -d
```

详细说明请参考 `services/README.md`。

## 🎯 快速开始

### 基本用法

```php
<?php
require_once 'vendor/autoload.php';

use DeepResearch\DeepResearchPipeline;
use DeepResearch\SearchExecutor;
use DeepResearch\AnalysisExecutor;
use DeepResearch\Util\Logger;

// 加载配置
$config = require_once 'config/config.php';

// 创建组件
$logger = new Logger($config['logging']['log_file']);

$searchExecutor = new SearchExecutor(
    $config['search']['api_key'],
    $logger,
    $config['search']['api_url'],
    $config['reader']['api_url'] ?? null
);

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

// 创建研究管道
$pipeline = new DeepResearchPipeline(
    $searchExecutor, 
    $analysisExecutor, 
    $logger,
    !empty($config['reader']['api_url'])
);

// 事件回调处理
$eventCallback = function($eventType, $data) {
    switch ($eventType) {
        case 'research_start':
            echo "开始研究: {$data['query']}\n";
            break;
        case 'tool_start':
            echo "使用工具: {$data['tool']} -> {$data['target']}\n";
            break;
        case 'analysis_complete':
            echo "第 {$data['round']} 轮分析完成\n";
            break;
        case 'research_complete':
            echo "研究完成！\n";
            break;
    }
};

// 执行研究
$result = $pipeline->executeResearch(
    '人工智能的最新发展趋势',  // 初始查询
    '请分析人工智能领域的最新发展趋势', // 原始问题
    3, // 研究深度
    $eventCallback
);

// 输出结果
echo $result['analysis'];
```

### 命令行使用

```bash
cd examples
php cli.php "人工智能的最新发展趋势" 3
```

### HTTP API使用

```bash
curl -X POST http://localhost/public/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "query": "人工智能的最新发展趋势",
    "depth": 3
  }'
```

## 🎨 交互式Demo

我们提供了一个完整的Web界面Demo，让您可以直观地体验Deep Research的强大功能。

### 🌐 在线体验

访问我们的在线Demo：**https://molaaaaaa.github.io/MolaGPT-DeepResearch/**

> **注意**：在线版本是纯前端演示，提供模拟的研究流程。要体验真实的AI功能，请下载到本地运行。

### 🏠 本地Demo

1. **配置环境**：
   ```bash
   # 复制并配置环境变量
   cp .env.example .env
   # 编辑 .env 文件，添加您的API密钥
   ```

2. **安装依赖**：
   ```bash
   composer install
   ```

3. **启动Demo服务器**：
   ```bash
   cd demo
   php -S localhost:8080
   ```

4. **访问Demo页面**：
   打开浏览器访问 `http://localhost:8080`

### Demo功能特色

- **🎯 交互式研究界面**：直观的研究主题输入和配置选项
- **🔑 自定义API配置**：支持在界面中直接配置各种API密钥
- **⚡ 实时进度追踪**：流式显示搜索、分析、工具规划等各个阶段
- **📊 可视化结果**：结构化展示研究报告和过程数据
- **🔧 灵活配置**：支持1-5轮研究深度，可指定研究重点
- **📱 响应式设计**：适配各种屏幕尺寸的设备

### Demo页面结构

```
demo/
├── index.html          # 主Demo页面
└── README.md          # Demo详细说明
```

Demo页面包含三个主要标签页：

1. **交互式演示**：完整的研究体验界面
2. **功能特性**：系统核心功能展示
3. **API示例**：代码集成参考

### 建议测试案例

在Demo中尝试以下研究主题：

- "人工智能在医疗领域的最新应用"
- "2024年新能源汽车发展趋势"
- "区块链技术在金融领域的创新"
- "量子计算商业化前景分析"

### Demo技术特点

- **流式响应**：基于Server-Sent Events (SSE)的实时数据流
- **事件驱动**：完整的进度追踪和状态反馈
- **错误处理**：友好的错误信息和恢复机制
- **无依赖前端**：纯HTML/CSS/JavaScript，无需额外框架

## 📚 API文档

### 事件类型

新版本支持详细的事件流，您可以通过事件回调实时监控研究进度：

| 事件类型 | 说明 | 数据字段 |
|---------|------|---------|
| `research_start` | 研究开始 | `query`, `depth`, `has_reader_service` |
| `round_start` | 轮次开始 | `round`, `total_rounds` |
| `planning_start` | 工具规划开始 | `round` |
| `planning_complete` | 工具规划完成 | `tool`, `target`, `reason` |
| `tool_start` | 工具执行开始 | `tool`, `target`, `round` |
| `tool_output` | 工具执行完成 | `tool`, 结果信息 |
| `analysis_start` | 分析开始 | `round` |
| `analysis_complete` | 分析完成 | `round`, `analysis_preview` |
| `round_complete` | 轮次完成 | `round`, `analysis` |
| `final_report_start` | 最终报告生成开始 | - |
| `research_complete` | 研究完成 | `final_report`, `rounds_completed` |
| `error` | 错误发生 | `message`, `round` |

### 返回数据结构

```php
[
    'analysis' => '最终研究报告',
    'rounds' => [
        // 每轮的详细分析结果
        [
            'round' => 1,
            'tool_used' => 'search',
            'target' => '搜索关键词',
            'analysis' => '分析内容',
            'timestamp' => '2024-01-01 12:00:00'
        ]
    ],
    'tool_history' => [
        // 工具执行历史
    ],
    'search_history' => [
        // 搜索历史
    ]
]
```

## 🔧 高级配置

### 支持的AI提供商

#### DashScope (阿里云)
```env
ANALYSIS_PROVIDER="dashscope"
DASHSCOPE_API_KEY="your-key"
DASHSCOPE_MODEL="qwen-plus"
```

#### OpenAI
```env
ANALYSIS_PROVIDER="openai"
OPENAI_API_KEY="your-key"
OPENAI_MODEL="gpt-4o"
```

#### 工具规划器 (OpenRouter)
```env
PLANNER_API_KEY="your-openrouter-key"
PLANNER_MODEL="nous-hermes-2-mixtral-8x7b-dpo"
```

### 自定义配置

您可以在 `config/config.php` 中进一步自定义配置，或完全通过环境变量进行配置。

## 🏗️ 架构

```
DeepResearchPipeline (核心协调器)
├── SearchExecutor (搜索执行器)
│   ├── Exa API (网络搜索)
│   └── Reader Service (URL内容提取)
├── AnalysisExecutor (分析执行器)
│   ├── Tool Planner (工具规划)
│   ├── DashScope/OpenAI (内容分析)
│   └── Report Generator (报告生成)
└── Logger (日志记录)
```

### Demo架构

Demo系统采用前后端分离的流式响应架构：

```
Frontend (demo/index.html)
├── 用户交互界面
├── 实时事件监听 (SSE)
└── 响应式布局

Backend (public/api.php)
├── 流式API接口 (text/event-stream)
├── 事件驱动输出
└── 错误处理机制

Core Pipeline
├── 深度研究管道
├── 事件回调系统
└── 结果流式传输
```

## 🛠️ 开发

### 运行示例

```bash
# 命令行示例
cd examples
php cli.php "您的研究问题" 3

# HTTP API示例
cd public
php -S localhost:8000

# Web Demo界面
cd demo  
php -S localhost:8080
# 然后访问 http://localhost:8080
```

### 开发工具

- **命令行工具**：`examples/cli.php` - 快速测试和调试
- **HTTP API**：`public/api.php` - 集成到Web应用
- **交互式Demo**：`demo/index.html` - 可视化研究过程
- **测试诊断**：访问Demo中的功能特性页面查看系统状态

### 启用调试

在 `.env` 文件中设置：

```env
LOG_ENABLED=true
LOG_FILE_PATH="logs/research.log"
```

## 📄 许可证

MIT License

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📞 支持

如有问题，请在 GitHub 上创建 Issue 或联系维护者。
