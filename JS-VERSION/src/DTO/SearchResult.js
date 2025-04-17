/**
 * SearchResult.js - 搜索结果数据传输对象
 * 
 * 用于封装和传输搜索结果的数据结构
 */

class SearchResult {
    /**
     * @param {Array} results 搜索结果数组
     * @param {string} query 搜索查询
     * @param {string} timestamp 搜索时间戳
     * @param {string|null} error 错误信息（如果有）
     * @param {string} [searchType='standard'] 搜索类型
     */
    constructor(results, query, timestamp, error = null, searchType = 'standard') {
        this.results = results;
        this.query = query;
        this.timestamp = timestamp;
        this.error = error;
        this.searchType = searchType;
    }

    /**
     * 获取搜索结果数量
     * @returns {number} 结果数量
     */
    getResultCount() {
        return this.results.length;
    }

    /**
     * 检查是否有错误
     * @returns {boolean} 是否有错误
     */
    hasError() {
        return this.error !== null;
    }

    /**
     * 获取错误信息
     * @returns {string|null} 错误信息
     */
    getError() {
        return this.error;
    }

    /**
     * 获取搜索结果
     * @returns {Array} 搜索结果数组
     */
    getResults() {
        return this.results;
    }

    /**
     * 获取搜索查询
     * @returns {string} 搜索查询
     */
    getQuery() {
        return this.query;
    }

    /**
     * 获取搜索时间戳
     * @returns {string} 时间戳
     */
    getTimestamp() {
        return this.timestamp;
    }

    /**
     * 获取搜索类型
     * @returns {string} 搜索类型
     */
    getSearchType() {
        return this.searchType;
    }

    /**
     * 将对象转换为JSON字符串
     * @returns {string} JSON字符串
     */
    toJSON() {
        return JSON.stringify({
            results: this.results,
            query: this.query,
            timestamp: this.timestamp,
            error: this.error,
            searchType: this.searchType
        });
    }

    /**
     * 从JSON字符串创建SearchResult对象
     * @param {string} json JSON字符串
     * @returns {SearchResult} SearchResult对象
     */
    static fromJSON(json) {
        const data = JSON.parse(json);
        return new SearchResult(
            data.results,
            data.query,
            data.timestamp,
            data.error,
            data.searchType
        );
    }
}

module.exports = SearchResult; 