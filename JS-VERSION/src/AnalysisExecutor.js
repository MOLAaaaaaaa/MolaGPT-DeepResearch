const axios = require('axios');

class AnalysisExecutor {
    constructor(apiKey, apiUrl, model, logger) {
        this.apiKey = apiKey;
        this.apiUrl = apiUrl;
        this.model = model;
        this.logger = logger;
    }

    async executeAnalysis(query, searchResults) {
        try {
            this.logger.info(`开始分析: ${query}`);

            const response = await axios.post(this.apiUrl, {
                model: this.model,
                messages: [
                    {
                        role: "system",
                        content: "你是一个专业的研究分析助手，请根据提供的搜索结果进行深入分析。"
                    },
                    {
                        role: "user",
                        content: `研究问题: ${query}\n\n搜索结果:\n${JSON.stringify(searchResults, null, 2)}`
                    }
                ],
                temperature: 0.7,
                max_tokens: 2000
            }, {
                headers: {
                    'Authorization': `Bearer ${this.apiKey}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.data && response.data.choices && response.data.choices[0]) {
                this.logger.info('分析完成');
                return {
                    success: true,
                    analysis: response.data.choices[0].message.content,
                    query: query,
                    timestamp: new Date().toISOString()
                };
            } else {
                throw new Error('分析结果格式错误');
            }
        } catch (error) {
            this.logger.error(`分析执行失败: ${error.message}`);
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
}

module.exports = AnalysisExecutor; 