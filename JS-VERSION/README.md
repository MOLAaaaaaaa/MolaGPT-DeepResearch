# MolaGPT Deep Research - JavaScript版本

这是一个基于JavaScript实现的深度研究工具，提供命令行和HTTP API两种使用方式。该工具能够进行深度问题分析和研究，支持多轮迭代搜索和分析。

## 功能特点

- 支持深度研究问题分析
- 提供命令行和HTTP API两种接口
- 可配置的研究深度
- 详细的进度跟踪
- 完整的日志记录
- 支持环境变量配置
- 支持多种AI模型（DashScope、OpenAI等）
- 模块化设计，易于扩展

## 系统要求

- Node.js >= 14.0.0
- npm >= 6.0.0

## 安装

1. 克隆仓库：
```bash
cd molagpt-deepresearch/JS-VERSION
```

2. 安装依赖：
```bash
npm install
```

3. 配置环境变量：
```bash
cp .env.example .env
```
然后编辑 `.env` 文件，填入你的API密钥和其他配置。

## 使用方法

### 命令行方式

```bash
npm run cli "你的研究问题" [深度]
```

示例：
```bash
npm run cli "人工智能的发展趋势" 2
```

### HTTP API方式

1. 启动服务器：
```bash
npm start
```

2. 发送POST请求：
```bash
curl -X POST http://localhost:3000/api/research \
  -H "Content-Type: application/json" \
  -d '{"query": "你的研究问题", "depth": 2}'
```

## 配置说明

在 `.env` 文件中配置以下参数：

### 搜索API配置
- `SEARCH_API_KEY`: 搜索API密钥
- `SEARCH_API_URL`: 搜索API地址
- `EXA_API_KEY`: Exa API密钥

### 分析API配置
- `DASHSCOPE_API_KEY`: DashScope API密钥
- `ANALYSIS_API_KEY`: 分析API密钥
- `ANALYSIS_API_URL`: 分析API地址
- `ANALYSIS_PROVIDER`: 分析服务提供商
- `ANALYSIS_MODEL`: 分析模型名称
- `OPENAI_API_KEY`: OpenAI API密钥

### 日志配置
- `LOG_LEVEL`: 日志级别
- `LOG_DIR`: 日志目录
- `LOG_FILE`: 日志文件路径

### 深度研究配置
- `RESEARCH_DEFAULT_DEPTH`: 默认研究深度
- `RESEARCH_MAX_DEPTH`: 最大研究深度

### 服务器配置
- `PORT`: 服务器端口
- `NODE_ENV`: 运行环境

## 开发指南

### 项目结构
```
JS-VERSION/
├── config/           # 配置文件
├── public/           # 公共文件
│   └── api.js        # HTTP API入口
├── src/              # 源代码
│   ├── DeepResearchPipeline.js
│   ├── SearchExecutor.js
│   ├── AnalysisExecutor.js
│   └── Util/
│       └── Logger.js
├── examples/         # 示例代码
│   └── cli.js        # 命令行示例
├── logs/            # 日志目录
├── .env.example     # 环境变量示例
├── package.json     # 项目配置
└── README.md        # 项目说明
```

### 开发命令
```bash
npm run dev    # 开发模式启动
npm run lint   # 代码检查
npm run format # 代码格式化
npm test       # 运行测试
```

### 添加新的分析提供商
1. 在 `src/AnalysisExecutor.js` 中添加新的提供商支持
2. 在 `.env.example` 中添加相应的配置项
3. 更新 `config/config.js` 以支持新的配置

## API文档

### HTTP API

#### POST /api/research
执行深度研究

请求体：
```json
{
    "query": "研究问题",
    "depth": 2
}
```

响应：
```json
{
    "success": true,
    "query": "研究问题",
    "depth": 2,
    "analysis": "分析结果",
    "search_history": {
        "original_query": "原始问题",
        "current_query": "当前问题",
        "related_queries": ["相关问题1", "相关问题2"],
        "timestamp": "2024-03-21T12:34:56.789Z"
    },
    "progress": ["进度消息1", "进度消息2"],
    "timestamp": "2024-03-21T12:34:56.789Z"
}
```

## 错误处理

系统使用统一的错误处理机制，所有错误都会被记录到日志中，并通过适当的格式返回给用户。

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request！

1. Fork 项目
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request 