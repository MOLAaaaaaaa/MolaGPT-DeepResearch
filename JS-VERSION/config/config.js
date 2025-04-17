/**
 * config.js - API密钥和URL配置
 * 
 * 该文件包含深度研究功能所需的API密钥和URL配置。
 * 实际使用时，建议将此文件替换为环境变量或其他安全的配置方式。
 */

require('dotenv').config();

module.exports = {
    // 搜索API配置
    search: {
        provider: process.env.SEARCH_PROVIDER || 'exa',
        api_key: process.env.EXA_API_KEY,
        api_url: process.env.SEARCH_API_URL || 'https://api.exa.ai/search'
    },
    
    // 分析API配置 - DashScope
    analysis_dashscope: {
        api_key: process.env.DASHSCOPE_API_KEY,
        api_url: process.env.DASHSCOPE_API_URL || 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
        model: process.env.DASHSCOPE_MODEL || 'qwen-plus'
    },
    
    // 分析API配置 - OpenAI
    analysis_openai: {
        api_key: process.env.OPENAI_API_KEY,
        api_url: process.env.OPENAI_API_URL || 'https://api.openai.com/v1/chat/completions',
        model: process.env.OPENAI_MODEL || 'gpt-4-turbo-preview'
    },
    
    // 日志配置
    logging: {
        level: process.env.LOG_LEVEL || 'info',
        dir: process.env.LOG_DIR || './logs',
        file: process.env.LOG_FILE || './logs/deep_research.log'
    },
    
    // 深度研究配置
    research: {
        default_depth: parseInt(process.env.RESEARCH_DEFAULT_DEPTH) || 3,
        max_depth: parseInt(process.env.RESEARCH_MAX_DEPTH) || 10
    },
    
    server: {
        port: process.env.PORT || 3000,
        env: process.env.NODE_ENV || 'development'
    }
}; 