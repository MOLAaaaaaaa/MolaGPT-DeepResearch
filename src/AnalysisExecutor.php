<?php
/**
 * AnalysisExecutor.php - 分析模型封装
 * 
 * 该类负责分析搜索结果，封装了对分析模型API的调用，
 * 支持DashScope或OpenAI作为分析提供商。
 */

namespace DeepResearch;

use DeepResearch\DTO\SearchResult;
use DeepResearch\DTO\AnalysisResult;
use DeepResearch\Util\Logger;
use RuntimeException;

class AnalysisExecutor
{
    /**
     * 分析提供商类型常量
     */
    public const PROVIDER_DASHSCOPE = 'dashscope';
    public const PROVIDER_OPENAI = 'openai';
    
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1; // seconds
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_CONNECT_TIMEOUT = 10;
    
    /**
     * @var string API密钥
     */
    private string $apiKey;
    
    /**
     * @var string API URL
     */
    private string $apiUrl;
    
    /**
     * @var string 提供商类型
     */
    private string $providerType;
    
    /**
     * @var string 模型名称
     */
    private string $modelName;
    
    /**
     * @var Logger 日志记录器实例
     */
    private Logger $logger;
    
    private array $curlOptions;
    
    /**
     * 构造函数
     * 
     * @param string $apiKey API密钥
     * @param string $apiUrl API URL
     * @param string $providerType 提供商类型 (dashscope|openai)
     * @param string $modelName 模型名称
     * @param Logger|null $logger 可选的日志记录器实例
     * @throws RuntimeException 当提供商类型无效时
     */
    public function __construct(
        string $apiKey,
        string $apiUrl,
        string $providerType = self::PROVIDER_DASHSCOPE,
        string $modelName = 'qwen-plus-latest',
        ?Logger $logger = null
    ) {
        if (!in_array($providerType, [self::PROVIDER_DASHSCOPE, self::PROVIDER_OPENAI], true)) {
            throw new RuntimeException('无效的提供商类型');
        }
        
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        $this->providerType = $providerType;
        $this->modelName = $modelName;
        $this->logger = $logger ?? new Logger();
        
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
                $this->getAuthorizationHeader()
            ]
        ];
    }
    
    /**
     * 分析搜索结果
     * 
     * @param SearchResult $searchResult 搜索结果对象
     * @param string $question 原始问题
     * @param string|null $previousAnalysis 先前的分析结果
     * @param callable|null $progressCallback 进度回调函数
     * @param int $currentRound 当前轮次
     * @param int $totalRounds 总轮次
     * @param array $searchHistory 搜索历史
     * @return AnalysisResult 分析结果对象
     * @throws RuntimeException 当分析失败时
     */
    public function analyzeResults(
        SearchResult $searchResult,
        string $question,
        ?string $previousAnalysis = null,
        ?callable $progressCallback = null,
        int $currentRound = 1,
        int $totalRounds = 2,
        array $searchHistory = []
    ): AnalysisResult {
        $this->logger->info("开始分析搜索结果");
        
        $prompt = $this->prepareAnalysisPrompt(
            $searchResult,
            $question,
            $previousAnalysis,
            $currentRound,
            $totalRounds,
            $searchHistory
        );
        
        $this->sendProgress($progressCallback, "专家分析开始...");
        
        // 使用重试机制
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < self::MAX_RETRIES) {
            try {
                $result = $this->makeApiRequest($prompt);
                
                $this->logger->info("搜索结果分析完成");
                return new AnalysisResult(
                    $result['analysis'],
                    $result['timestamp'] ?? date('Y-m-d H:i:s')
                );
                
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $this->logger->warning("分析尝试 {$attempt} 失败: {$lastError}");
                
                if (++$attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY * $attempt);
                    continue;
                }
            }
        }
        
        $this->logger->error("分析执行失败，已重试{$attempt}次: {$lastError}");
        throw new RuntimeException("分析执行失败: {$lastError}");
    }
    
    /**
     * 准备分析提示
     * 
     * @param SearchResult $searchResult 搜索结果对象
     * @param string $question 原始问题
     * @param string|null $previousAnalysis 先前的分析结果
     * @param int $currentRound 当前轮次
     * @param int $totalRounds 总轮次
     * @param array $searchHistory 搜索历史
     * @return string 分析提示
     */
    private function prepareAnalysisPrompt(
        SearchResult $searchResult,
        string $question,
        ?string $previousAnalysis,
        int $currentRound,
        int $totalRounds,
        array $searchHistory
    ): string {
        $context = "# 最重要的要求\n";
        $context .= "你的回复不能拖沓，必须要精炼，节省字数。\n";
        $context .= "你需要查看之前的分析，判断是否偏离最初的方向，如果你认为偏离方向，请将方向拉回。\n\n";
        
        $context .= "# 角色设定\n";
        $context .= "你是一位在各种领域经验丰富、洞察力深刻的顶级专家分析师。\n";
        $context .= "你的思维严谨、注重细节，并擅长从复杂信息中提炼核心观点和发现隐藏的联系。\n\n";
        
        $context .= "# 背景情境\n";
        $context .= "你是一个多 Agent 协作研究系统中的关键环节。\n";
        $context .= "此前，一个搜索引擎 Agent 已经围绕核心研究主题收集了相关的资料。\n";
        $context .= "现在，这些原始资料将提供给你进行专业的深度分析。\n";
        $context .= "你的分析结果将作为后续合成报告或决策制定的重要依据。\n\n";
        
        $context .= "# 研究主题\n{$question}\n\n";
        
        if ($previousAnalysis) {
            $context .= "# 先前分析\n{$previousAnalysis}\n\n";
        }
        
        $context .= "# 当前进度\n";
        $context .= "这是第 {$currentRound} 轮分析（共 {$totalRounds} 轮）\n\n";
        
        $context .= "# 搜索历史\n";
        foreach ($searchHistory as $history) {
            $context .= "第 {$history['round']} 轮: {$history['query']}\n";
        }
        $context .= "\n";
        
        $context .= "# 本轮搜索结果\n";
        foreach ($searchResult->getResults() as $index => $result) {
            $context .= "\n文章 " . ($index + 1) . ":\n";
            $context .= "标题: {$result['title']}\n";
            $context .= "链接: {$result['url']}\n";
            $context .= "内容: {$result['content']}\n";
        }
        
        return $context;
    }
    
    /**
     * 执行API请求
     * 
     * @throws RuntimeException 当请求失败时
     */
    private function makeApiRequest(string $prompt): array
    {
        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new RuntimeException('无法初始化CURL');
        }
        
        try {
            $payload = $this->prepareApiPayload($prompt);
            
            curl_setopt_array($ch, $this->curlOptions);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
            
            $response = curl_exec($ch);
            if ($response === false) {
                throw new RuntimeException('CURL执行失败: ' . curl_error($ch));
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new RuntimeException("API请求失败，HTTP状态码: {$httpCode}");
            }
            
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new RuntimeException('无效的API响应格式');
            }
            
            return [
                'analysis' => $data['choices'][0]['message']['content'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (\JsonException $e) {
            throw new RuntimeException('JSON处理错误: ' . $e->getMessage());
        } finally {
            curl_close($ch);
        }
    }
    
    /**
     * 准备API请求负载
     */
    private function prepareApiPayload(string $prompt): array
    {
        return [
            'model' => $this->modelName,
            'messages' => [
                ['role' => 'system', 'content' => '你是一位专业的研究分析助手，请根据提供的搜索结果进行深入分析。'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.6
        ];
    }
    
    /**
     * 获取授权头
     */
    private function getAuthorizationHeader(): string
    {
        return $this->providerType === self::PROVIDER_OPENAI
            ? 'Authorization: Bearer ' . $this->apiKey
            : 'Authorization: ' . $this->apiKey;
    }
    
    /**
     * 发送进度更新
     * 
     * @param callable|null $callback 进度回调函数
     * @param string $message 进度消息
     */
    private function sendProgress(?callable $callback, string $message): void
    {
        if ($callback !== null) {
            $callback($message);
        }
    }
}
