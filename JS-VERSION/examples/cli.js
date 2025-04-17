/**
 * cli.js - 示例CLI调用入口
 * 
 * 该文件展示了如何在命令行环境中使用深度研究功能。
 * 使用方法: node cli.js "你的研究问题" [研究深度]
 */

require('dotenv').config();
const fs = require('fs');
const path = require('path');

// 导入类
const DeepResearchPipeline = require('../src/DeepResearchPipeline');
const SearchExecutor = require('../src/SearchExecutor');
const AnalysisExecutor = require('../src/AnalysisExecutor');
const Logger = require('../src/Util/Logger');

// 检查必要的环境变量
const searchApiKey = process.env.SEARCH_API_KEY;
if (!searchApiKey) {
    console.error('错误: 未设置 SEARCH_API_KEY 环境变量');
    process.exit(1);
}

// 获取参数
const args = process.argv.slice(2);
const query = args[0];
const depth = parseInt(args[1]) || parseInt(process.env.RESEARCH_DEFAULT_DEPTH) || 3;

if (!query) {
    console.error('使用方法: npm run cli "研究问题" [深度]');
    process.exit(1);
}

// 创建日志目录
const logDir = path.join(__dirname, '../logs');
if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
}

// 创建日志记录器
const logger = new Logger();
logger.setLogLevel('info');
logger.info(`开始深度研究: ${query}`);

async function main() {
    try {
        // 创建搜索执行器
        const searchExecutor = new SearchExecutor(
            searchApiKey,
            logger,
            process.env.SEARCH_API_URL
        );
        
        // 创建分析执行器 (使用DashScope)
        const analysisExecutor = new AnalysisExecutor(
            process.env.ANALYSIS_DASHSCOPE_API_KEY,
            process.env.ANALYSIS_DASHSCOPE_API_URL,
            process.env.ANALYSIS_DASHSCOPE_MODEL,
            logger
        );
        
        // 创建深度研究管道
        const pipeline = new DeepResearchPipeline(searchExecutor, analysisExecutor, logger);
        
        // 执行深度研究
        console.log(`开始对 "${query}" 进行深度研究 (深度: ${depth})...`);
        const result = await pipeline.executeResearch(query, query, depth, (message) => {
            logger.info(message);
        });
        
        // 检查是否有错误
        if (result.error) {
            console.error('研究过程中出错:', result.error);
            process.exit(1);
        }
        
        // 输出研究结果
        console.log('\n============ 深度研究结果 ============\n');
        console.log(result.analysis + '\n');
        
        // 保存结果到文件
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const filename = `./results/research_${timestamp}.json`;
        require('fs').mkdirSync('./results', { recursive: true });
        require('fs').writeFileSync(filename, JSON.stringify(result, null, 2));
        console.log('\n研究完成！结果已保存到:', filename);
        
        logger.info(`深度研究完成: ${query}`);
        
    } catch (error) {
        console.error('研究过程中出错:', error.message);
        logger.error(`深度研究异常: ${error.message}`);
        process.exit(1);
    }
}

main(); 