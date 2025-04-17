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
use RuntimeException;

class SearchExecutor
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1; // seconds
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_CONNECT_TIMEOUT = 10;
    
    private string $apiKey;
    private string $apiUrl;
    private Logger $logger;
    private array $curlOptions;
    
    /**
     * 构造函数
     * 
     * @param string $apiKey Exa API密钥
     * @param Logger|null $logger 可选的日志记录器实例
     * @param string|null $apiUrl 可选的API URL
     * @throws RuntimeException 当API密钥为空时
     */
    public function __construct(string $apiKey, ?Logger $logger = null, ?string $apiUrl = null)
    {
        if (empty($apiKey)) {
            throw new RuntimeException('API密钥不能为空');
        }
        
        $this->apiKey = $apiKey;
        $this->logger = $logger ?? new Logger();
        $this->apiUrl = $apiUrl ?? $_ENV['SEARCH_API_URL'] ?? 'https://api.exa.ai/search';
        
        // 预设CURL选项以提高性能
        $this->curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TCP_NODELAY => 1,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey
            ]
        ];
        
        $this->logger->info("SearchExecutor 初始化完成，API URL: {$this->apiUrl}");
    }
    
    /**
     * 执行搜索
     * 
     * @param string $query 搜索查询
     * @param bool $isDeepResearch 是否为深度研究模式
     * @return SearchResult 搜索结果对象
     * @throws RuntimeException 当搜索查询为空时
     */
    public function executeSearch(string $query, bool $isDeepResearch = false): SearchResult
    {
        if (empty($query)) {
            throw new RuntimeException('搜索查询不能为空');
        }
        
        $this->logger->info("开始执行" . ($isDeepResearch ? "深度" : "标准") . "搜索: {$query}");
        
        // 使用重试机制
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < self::MAX_RETRIES) {
            try {
                $params = $this->prepareRequestParams($query, $isDeepResearch);
                $result = $this->makeApiRequest($params);
                
                if (!isset($result['error'])) {
                    $formattedResults = $this->formatSearchResults($result, $isDeepResearch);
                    $this->logger->info("搜索完成，找到" . count($formattedResults) . "条结果");
                    
                    return new SearchResult(
                        $formattedResults,
                        $query,
                        date('Y-m-d H:i:s'),
                        null,
                        $isDeepResearch ? 'deep_research' : 'standard'
                    );
                }
                
                throw new RuntimeException($result['error']);
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->logger->warning("搜索尝试 {$attempt} 失败: {$lastError}");
                
                if (++$attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY * $attempt);
                    continue;
                }
            }
        }
        
        $this->logger->error("搜索执行失败，已重试{$attempt}次: {$lastError}");
        return new SearchResult([], $query, date('Y-m-d H:i:s'), $lastError);
    }
    
    /**
     * 准备API请求参数
     */
    private function prepareRequestParams(string $query, bool $isDeepResearch): array
    {
        return [
            'query' => $query,
            'type' => "auto",
            'contents' => [
                'text' => [
                    'maxCharacters' => $isDeepResearch ? 1500 : 1000,
                    'includeHtmlTags' => true
                ],
                'livecrawl' => "always"
            ],
            'num_results' => $isDeepResearch ? 13 : 10
        ];
    }
    
    /**
     * 执行API请求
     * 
     * @throws RuntimeException 当请求失败时
     */
    private function makeApiRequest(array $params): array
    {
        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new RuntimeException('无法初始化CURL');
        }
        
        try {
            curl_setopt_array($ch, $this->curlOptions);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_THROW_ON_ERROR));
            
            $response = curl_exec($ch);
            if ($response === false) {
                throw new RuntimeException('CURL执行失败: ' . curl_error($ch));
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new RuntimeException("API请求失败，HTTP状态码: {$httpCode}");
            }
            
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            
            if (!isset($data['results'])) {
                throw new RuntimeException('无效的API响应格式');
            }
            
            return $data;
            
        } catch (\JsonException $e) {
            throw new RuntimeException('JSON处理错误: ' . $e->getMessage());
        } finally {
            curl_close($ch);
        }
    }
    
    /**
     * 格式化搜索结果
     */
    private function formatSearchResults(array $searchData, bool $isDeepResearch): array
    {
        if (!isset($searchData['results']) || !is_array($searchData['results'])) {
            return [];
        }
        
        $maxResults = $isDeepResearch ? 15 : 10;
        $results = array_slice($searchData['results'], 0, $maxResults);
        
        return array_map(function($result) {
            return [
                'title' => $result['title'] ?? '未知标题',
                'url' => $result['url'] ?? '',
                'content' => $result['summary'] ?? $result['text'] ?? $result['snippet'] ?? '无内容'
            ];
        }, $results);
    }
}
