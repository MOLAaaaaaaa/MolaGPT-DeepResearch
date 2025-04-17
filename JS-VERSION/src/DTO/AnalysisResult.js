/**
 * AnalysisResult.js - 分析结果数据传输对象
 * 
 * 用于封装和传输分析结果的数据结构
 */

class AnalysisResult {
    /**
     * @param {string} analysis 分析内容
     * @param {string} timestamp 分析时间戳
     * @param {string|null} [error=null] 错误信息（如果有）
     */
    constructor(analysis, timestamp, error = null) {
        this.analysis = analysis;
        this.timestamp = timestamp;
        this.error = error;
    }

    /**
     * 获取分析内容
     * @returns {string} 分析内容
     */
    getAnalysis() {
        return this.analysis;
    }

    /**
     * 获取时间戳
     * @returns {string} 时间戳
     */
    getTimestamp() {
        return this.timestamp;
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
     * 将对象转换为JSON字符串
     * @returns {string} JSON字符串
     */
    toJSON() {
        return JSON.stringify({
            analysis: this.analysis,
            timestamp: this.timestamp,
            error: this.error
        });
    }

    /**
     * 从JSON字符串创建AnalysisResult对象
     * @param {string} json JSON字符串
     * @returns {AnalysisResult} AnalysisResult对象
     */
    static fromJSON(json) {
        const data = JSON.parse(json);
        return new AnalysisResult(
            data.analysis,
            data.timestamp,
            data.error
        );
    }
}

module.exports = AnalysisResult; 