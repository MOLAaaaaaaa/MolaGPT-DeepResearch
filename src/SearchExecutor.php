<?php
/**
 * SearchExecutor.php - Exa 搜索封装
 * 
 * 该类负责执行网络搜索，封装了对Exa API的调用，
 * 支持标准搜索和深度研究模式。
 */

namespace DeepResearch;

use DeepResearch\DTO\SearchResult;
use DeepResearch\Util\Logger;

class SearchExecutor
{
    /**
     * @var string Exa API密钥
     */
    private $apiKey;
    
    /**
     * @var string Exa API URL
     */
    private $apiUrl = 'https://api.exa.ai/search';
    
    /**
     * @var string|null 内容读取器服务的URL
     */
    private $readerApiUrl;
    
    /**
     * @var Logger 日志记录器实例
     */
    private $logger;
    
    /**
     * 构造函数
     * 
     * @param string $apiKey Exa API密钥
     * @param Logger|null $logger 可选的日志记录器实例
     * @param string|null $apiUrl 可选的API URL
     * @param string|null $readerApiUrl 可选的内容读取器服务URL
     */
    public function __construct(string $apiKey, ?Logger $logger = null, ?string $apiUrl = null, ?string $readerApiUrl = null)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger ?? new Logger();
        $this->readerApiUrl = $readerApiUrl;
        
        if ($apiUrl !== null) {
            $this->apiUrl = $apiUrl;
        }
    }
    
    /**
     * 执行搜索
     * 
     * @param string $query 搜索查询
     * @param bool $isDeepResearch 是否为深度研究模式
     * @return SearchResult 搜索结果对象
     */
    public function executeSearch(string $query, bool $isDeepResearch = false): SearchResult
    {
        if (empty($query)) {
            $this->log("错误: 搜索查询为空");
            return new SearchResult([], $query, date('Y-m-d H:i:s'), '搜索查询不能为空');
        }
        
        $this->log("开始执行" . ($isDeepResearch ? "深度" : "标准") . "搜索: {$query}");
        
        // 准备请求参数
        $requestParams = $this->prepareRequestParams($query, $isDeepResearch);
        
        // 执行API请求
        $result = $this->makeApiRequest($requestParams);
        
        if (isset($result['error'])) {
            $this->log("搜索API错误: " . $result['error']);
            return new SearchResult([], $query, date('Y-m-d H:i:s'), $result['error']);
        }
        
        // 处理搜索结果
        $formattedResults = $this->formatSearchResults($result, $isDeepResearch);
        
        $this->log("搜索完成，找到" . count($formattedResults) . "条结果");
        
        return new SearchResult(
            $formattedResults,
            $query,
            date('Y-m-d H:i:s'),
            null,
            $isDeepResearch ? 'deep_research' : 'standard'
        );
    }
    
    /**
     * 准备API请求参数
     * 
     * @param string $query 搜索查询
     * @param bool $isDeepResearch 是否为深度研究模式
     * @return array 请求参数
     */
    private function prepareRequestParams(string $query, bool $isDeepResearch): array
    {
        if ($isDeepResearch) {
            // 深度研究模式参数
            return [
                'query' => $query,
                'type' => "auto", 
                'contents' => [
                    'text' => [
                        'maxCharacters' => 1500,  // 增加字符数以获取更详细信息
                        'includeHtmlTags' => true
                    ],
                    'livecrawl' => "always"
                ],
                'num_results' => 13,  // 增加结果数量
            ];
        } else {
            // 标准搜索模式参数
            return [
                'query' => $query,
                'type' => "auto",
                'contents' => [
                    'text' => [
                        'maxCharacters' => 1000,
                        'includeHtmlTags' => true
                    ],
                    'livecrawl' => "always"
                ],
                'num_results' => 10
            ];
        }
    }
    
    /**
     * 执行API请求
     * 
     * @param array $params 请求参数
     * @return array 响应数据
     */
    private function makeApiRequest(array $params): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return ['error' => '搜索API请求失败，HTTP状态码: ' . $httpCode];
        }
        
        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON解析错误: ' . json_last_error_msg()];
        }
        
        return $data;
    }
    
    /**
     * 格式化搜索结果
     * 
     * @param array $searchData API返回的原始数据
     * @param bool $isDeepResearch 是否为深度研究模式
     * @return array 格式化后的结果
     */
    private function formatSearchResults(array $searchData, bool $isDeepResearch): array
    {
        $formattedResults = [];
        
        if (isset($searchData['results']) && is_array($searchData['results'])) {
            // 根据模式决定取多少条结果
            $maxResults = $isDeepResearch ? 15 : 10;
            
            foreach (array_slice($searchData['results'], 0, $maxResults) as $result) {
                $formattedResults[] = [
                    'title' => $result['title'] ?? '未知标题',
                    'url' => $result['url'] ?? '',
                    'content' => $result['summary'] ?? ($result['text'] ?? ($result['snippet'] ?? '无内容'))
                ];
            }
        }
        
        return $formattedResults;
    }
    
    /**
     * 从指定的URL提取主要内容
     * 
     * @param string $url 要提取内容的URL
     * @return string|null 提取的文本内容，如果失败则返回null
     */
    public function getContentFromUrl(string $url): ?string
    {
        if (empty($this->readerApiUrl)) {
            $this->log("内容读取器服务未配置，无法从URL提取内容。");
            return null;
        }

        $this->log("开始从URL提取内容: {$url}");

        $ch = curl_init($this->readerApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $url]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30秒超时

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->log("调用内容读取器服务失败: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            $this->log("内容读取器服务返回错误状态码: {$httpCode}. 响应: {$response}");
            return null;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($result['content'])) {
            $this->log("无法解析内容读取器服务的响应或响应中缺少'content'字段。");
            return null;
        }

        $this->log("成功从URL提取内容。");
        return $result['content'];
    }
    
    /**
     * 记录日志
     * 
     * @param string $message 日志消息
     */
    private function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->log($message);
        }
    }
}
