/**
 * SearchExecutor.js - Exa 搜索封装
 * 
 * 该类负责执行网络搜索，封装了对Exa API的调用，
 * 支持标准搜索和深度研究模式。
 */

const axios = require('axios');
const SearchResult = require('./DTO/SearchResult');
const Logger = require('./Util/Logger');

class SearchExecutor {
    /**
     * @param {string} apiKey Exa API密钥
     * @param {Logger} [logger=null] 可选的日志记录器实例
     * @param {string} [apiUrl=null] 可选的API URL
     */
    constructor(apiKey, logger = null, apiUrl = null) {
        if (!apiKey) {
            throw new Error('API密钥不能为空');
        }
        
        this.apiKey = apiKey;
        this.logger = logger || new Logger();
        this.apiUrl = apiUrl || process.env.SEARCH_API_URL || 'https://api.exa.ai/search';
        
        // 创建一个配置好的 axios 实例
        this.axiosInstance = axios.create({
            baseURL: this.apiUrl,
            headers: {
                'Content-Type': 'application/json',
                'x-api-key': this.apiKey
            },
            validateStatus: function (status) {
                return status >= 200 && status < 300;
            }
        });

        this.logger.info(`SearchExecutor 初始化完成，API URL: ${this.apiUrl}`);
    }

    /**
     * 执行搜索
     * 
     * @param {string} query 搜索查询
     * @param {boolean} [isDeepResearch=false] 是否为深度研究模式
     * @returns {SearchResult} 搜索结果对象
     */
    async executeSearch(query, isDeepResearch = false) {
        try {
            this.logger.info(`执行搜索: ${query}, 深度研究: ${isDeepResearch}`);

            const params = this.prepareRequestParams(query, isDeepResearch);
            const response = await this.axiosInstance.post('', params);

            if (response.data && response.data.results) {
                this.logger.info(`搜索完成，获得 ${response.data.results.length} 条结果`);
                return {
                    success: true,
                    results: this.formatSearchResults(response.data, isDeepResearch),
                    query: query,
                    timestamp: new Date().toISOString()
                };
            } else {
                throw new Error('搜索结果格式错误');
            }
        } catch (error) {
            this.logger.error(`搜索执行失败: ${error.message}`);
            if (error.response) {
                this.logger.error(`API响应: ${JSON.stringify(error.response.data)}`);
            }
            return {
                success: false,
                error: error.message,
                query: query,
                timestamp: new Date().toISOString()
            };
        }
    }

    /**
     * 准备API请求参数
     * 
     * @param {string} query 搜索查询
     * @param {boolean} isDeepResearch 是否为深度研究模式
     * @returns {Object} 请求参数
     */
    prepareRequestParams(query, isDeepResearch) {
        return {
            query: query,
            type: "auto",
            contents: {
                text: {
                    maxCharacters: isDeepResearch ? 1500 : 1000,
                    includeHtmlTags: true
                },
                livecrawl: "always"
            },
            num_results: isDeepResearch ? 13 : 10
        };
    }

    /**
     * 格式化搜索结果
     * 
     * @param {Object} searchData API返回的原始数据
     * @param {boolean} isDeepResearch 是否为深度研究模式
     * @returns {Array} 格式化后的结果
     */
    formatSearchResults(searchData, isDeepResearch) {
        const formattedResults = [];

        if (searchData.results && Array.isArray(searchData.results)) {
            // 根据模式决定取多少条结果
            const maxResults = isDeepResearch ? 15 : 10;

            searchData.results.slice(0, maxResults).forEach(result => {
                formattedResults.push({
                    title: result.title || '未知标题',
                    url: result.url || '',
                    content: result.summary || result.text || result.snippet || '无内容'
                });
            });
        }

        return formattedResults;
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
}

module.exports = SearchExecutor; 