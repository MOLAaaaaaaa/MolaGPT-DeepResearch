/**
 * index.js - 项目入口文件
 * 
 * 提供基本的使用示例和配置
 */

const SearchExecutor = require('./SearchExecutor');
const Logger = require('./Util/Logger');

// 从环境变量获取API密钥
const apiKey = process.env.EXA_API_KEY;

if (!apiKey) {
    console.error('错误: 未设置EXA_API_KEY环境变量');
    process.exit(1);
}

// 创建日志记录器实例
const logger = new Logger();
logger.setLogLevel('info');

// 创建搜索执行器实例
const searchExecutor = new SearchExecutor(apiKey, logger);

// 示例搜索函数
async function performSearch(query, isDeepResearch = false) {
    try {
        const result = await searchExecutor.executeSearch(query, isDeepResearch);
        
        if (result.hasError()) {
            console.error(`搜索错误: ${result.getError()}`);
            return;
        }

        console.log(`搜索完成，找到 ${result.getResultCount()} 条结果`);
        console.log('搜索结果:');
        result.getResults().forEach((item, index) => {
            console.log(`\n结果 ${index + 1}:`);
            console.log(`标题: ${item.title}`);
            console.log(`URL: ${item.url}`);
            console.log(`内容: ${item.content.substring(0, 200)}...`);
        });
    } catch (error) {
        console.error('搜索过程中发生错误:', error);
    }
}

// 导出主要功能
module.exports = {
    SearchExecutor,
    Logger,
    performSearch
};

// 如果直接运行此文件，执行示例搜索
if (require.main === module) {
    const query = process.argv[2] || '人工智能最新发展';
    const isDeepResearch = process.argv[3] === 'deep';
    
    console.log(`执行${isDeepResearch ? '深度' : '标准'}搜索: ${query}`);
    performSearch(query, isDeepResearch);
} 