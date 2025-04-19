/**
 * api.js - 示例HTTP API接口入口
 * 
 * 该文件展示了如何创建一个HTTP API接口来提供深度研究功能。
 * 使用方法: 通过POST请求访问此文件，提供query和depth参数。
 */

const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');

// 导入类
const DeepResearchPipeline = require('../src/DeepResearchPipeline');
const SearchExecutor = require('../src/SearchExecutor');
const AnalysisExecutor = require('../src/AnalysisExecutor');
const Logger = require('../src/Util/Logger');

// 创建Express应用
const app = express();

// 中间件配置
app.use(cors());
app.use(express.json());

// 创建日志目录
const logDir = path.join(__dirname, '../logs');
if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
}

// 创建日志记录器
const logger = new Logger();
logger.setLogLevel('info');

// 加载配置
const config = require('../config/config');

// 创建执行器实例
const searchExecutor = new SearchExecutor(
    config.search.api_key,
    logger,
    config.search.api_url
);

const analysisExecutor = new AnalysisExecutor(
    config.analysis_dashscope.api_key,
    config.analysis_dashscope.api_url,
    config.analysis_dashscope.model,
    logger
);

// 创建深度研究管道
const pipeline = new DeepResearchPipeline(searchExecutor, analysisExecutor, logger);

// API路由
app.post('/api/research', async (req, res) => {
    try {
        // 验证请求参数
        const { query, depth = 2 } = req.body;
        
        if (!query || typeof query !== 'string' || query.trim() === '') {
            return res.status(400).json({
                success: false,
                error: '缺少必要参数: query'
            });
        }
        
        logger.info(`API请求: ${query}, 深度: ${depth}`);
        
        // 进度存储
        const progressMessages = [];
        
        // 进度回调函数
        const progressCallback = (message) => {
            progressMessages.push(message);
        };
        
        // 执行深度研究
        const result = await pipeline.executeResearch(query, query, depth, progressCallback);
        
        // 检查是否有错误
        if (result.error) {
            return res.status(500).json({
                success: false,
                error: result.error,
                progress: progressMessages
            });
        }
        
        // 返回成功结果
        res.json({
            success: true,
            query,
            depth,
            analysis: result.analysis,
            search_history: result.search_history,
            progress: progressMessages,
            timestamp: new Date().toISOString()
        });
        
        logger.info(`API请求完成: ${query}`);
        
    } catch (error) {
        logger.error(`API异常: ${error.message}`);
        
        res.status(500).json({
            success: false,
            error: `服务器内部错误: ${error.message}`
        });
    }
});

// 启动服务器
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`API服务器运行在端口 ${PORT}`);
}); 