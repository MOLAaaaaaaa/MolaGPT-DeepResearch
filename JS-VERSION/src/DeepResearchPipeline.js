/**
 * DeepResearchPipeline.js - 多轮搜索与分析主类
 * 
 * 该类是深度研究功能的主要入口点，负责协调搜索和分析过程，
 * 实现多轮迭代式深入研究功能。
 */

const SearchExecutor = require('./SearchExecutor');
const AnalysisExecutor = require('./AnalysisExecutor');
const Logger = require('./Util/Logger');

class DeepResearchPipeline {
    /**
     * @param {SearchExecutor} searchExecutor 搜索执行器实例
     * @param {AnalysisExecutor} analysisExecutor 分析执行器实例
     * @param {Logger} logger 日志记录器实例
     */
    constructor(searchExecutor, analysisExecutor, logger) {
        this.searchExecutor = searchExecutor;
        this.analysisExecutor = analysisExecutor;
        this.logger = logger;
        this.searchHistory = [];
        this.analysisCollection = [];
    }

    /**
     * 执行深度研究
     * 
     * @param {string} query 查询关键词
     * @param {string} originalQuery 原始问题（上下文）
     * @param {number} [depth=2] 研究深度（迭代次数）
     * @param {Function} [progressCallback=null] 进度回调函数
     * @returns {Promise<Object>} 包含所有分析结果的数组
     */
    async executeResearch(query, originalQuery, depth = 2, progressCallback = null) {
        try {
            this.logger.info(`开始深度研究: ${query}, 深度: ${depth}`);

            // 执行搜索
            if (progressCallback) {
                progressCallback('正在执行搜索...');
            }
            const searchResult = await this.searchExecutor.executeSearch(query, true);
            
            if (!searchResult.success) {
                throw new Error(`搜索失败: ${searchResult.error}`);
            }

            // 执行分析
            if (progressCallback) {
                progressCallback('正在分析搜索结果...');
            }
            const analysisResult = await this.analysisExecutor.executeAnalysis(query, searchResult.results);
            
            if (!analysisResult.success) {
                throw new Error(`分析失败: ${analysisResult.error}`);
            }

            // 如果深度大于1，继续深入研究
            let relatedQueries = [];
            if (depth > 1) {
                if (progressCallback) {
                    progressCallback('正在生成相关问题...');
                }
                relatedQueries = this.generateRelatedQueries(analysisResult.analysis);
                
                for (const relatedQuery of relatedQueries) {
                    if (progressCallback) {
                        progressCallback(`正在研究相关问题: ${relatedQuery}`);
                    }
                    const relatedResult = await this.executeResearch(
                        relatedQuery,
                        originalQuery,
                        depth - 1,
                        progressCallback
                    );
                    analysisResult.analysis += `\n\n相关问题 "${relatedQuery}" 的研究结果:\n${relatedResult.analysis}`;
                }
            }

            this.logger.info('深度研究完成');
            return {
                success: true,
                analysis: analysisResult.analysis,
                search_history: {
                    original_query: originalQuery,
                    current_query: query,
                    related_queries: relatedQueries,
                    timestamp: new Date().toISOString()
                }
            };
        } catch (error) {
            this.logger.error(`深度研究失败: ${error.message}`);
            return {
                success: false,
                error: error.message
            };
        }
    }

    generateRelatedQueries(analysis) {
        // 这里可以实现从分析结果中提取相关问题的逻辑
        // 目前返回空数组，需要根据实际需求实现
        return [];
    }

    /**
     * 整合所有分析结果
     * 
     * @returns {string} 整合后的分析结果
     */
    consolidateAnalysis() {
        let finalAnalysis = `深度研究总结 - 基于 ${this.analysisCollection.length} 轮迭代分析\n\n`;
        
        this.analysisCollection.forEach((analysis, index) => {
            finalAnalysis += `第 ${index + 1} 轮分析:\n`;
            finalAnalysis += `${analysis.analysis}\n\n`;
        });
        
        return finalAnalysis;
    }
    
    /**
     * 记录日志
     * 
     * @param {string} message 日志消息
     */
    log(message) {
        if (this.logger) {
            this.logger.log(message);
        }
    }
    
    /**
     * 发送进度更新
     * 
     * @param {Function|null} callback 进度回调函数
     * @param {string} message 进度消息
     */
    sendProgress(callback, message) {
        if (callback && typeof callback === 'function') {
            callback(message);
        }
    }
    
    /**
     * 获取搜索历史
     * 
     * @returns {Array} 搜索历史记录
     */
    getSearchHistory() {
        return this.searchHistory;
    }
    
    /**
     * 获取分析结果集合
     * 
     * @returns {Array} 分析结果集合
     */
    getAnalysisCollection() {
        return this.analysisCollection;
    }
}

module.exports = DeepResearchPipeline; 