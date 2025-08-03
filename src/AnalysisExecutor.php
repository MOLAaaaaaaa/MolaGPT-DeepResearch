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

class AnalysisExecutor
{
    /**
     * 分析提供商类型常量
     */
    const PROVIDER_DASHSCOPE = 'dashscope';
    const PROVIDER_OPENAI = 'openai';
    
    /**
     * @var string API密钥
     */
    private $apiKey;
    
    /**
     * @var string API URL
     */
    private $apiUrl;
    
    /**
     * @var string 提供商类型
     */
    private $providerType;
    
    /**
     * @var string 模型名称
     */
    private $modelName;
    
    /**
     * @var Logger 日志记录器实例
     */
    private $logger;
    
    /**
     * @var string|null 工具规划器API密钥
     */
    private $plannerApiKey;
    
    /**
     * @var string|null 工具规划器API URL
     */
    private $plannerApiUrl;
    
    /**
     * @var string|null 工具规划器模型名称
     */
    private $plannerModel;
    
    /**
     * 构造函数
     * 
     * @param string $apiKey API密钥
     * @param string $apiUrl API URL
     * @param string $providerType 提供商类型 (dashscope|openai)
     * @param string $modelName 模型名称
     * @param Logger|null $logger 可选的日志记录器实例
     * @param string|null $plannerApiKey 可选的工具规划器API密钥
     * @param string|null $plannerApiUrl 可选的工具规划器API URL
     * @param string|null $plannerModel 可选的工具规划器模型名称
     */
    public function __construct(
        string $apiKey, 
        string $apiUrl, 
        string $providerType = self::PROVIDER_DASHSCOPE,
        string $modelName = 'qwen-plus-latest',
        ?Logger $logger = null,
        ?string $plannerApiKey = null,
        ?string $plannerApiUrl = null,
        ?string $plannerModel = null
    ) {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        $this->providerType = $providerType;
        $this->modelName = $modelName;
        $this->logger = $logger ?? new Logger();
        $this->plannerApiKey = $plannerApiKey;
        $this->plannerApiUrl = $plannerApiUrl;
        $this->plannerModel = $plannerModel;
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
        $this->log("开始分析搜索结果");
        
        // 准备分析提示
        $prompt = $this->prepareAnalysisPrompt(
            $searchResult,
            $question,
            $previousAnalysis,
            $currentRound,
            $totalRounds,
            $searchHistory
        );
        
        // 发送进度消息
        $this->sendProgress($progressCallback, "专家分析开始...");
        
        // 根据提供商类型选择不同的API调用方法
        if ($this->providerType === self::PROVIDER_DASHSCOPE) {
            $analysisResult = $this->callDashScopeApi($prompt);
        } else {
            $analysisResult = $this->callOpenAIApi($prompt);
        }
        
        if (isset($analysisResult['error'])) {
            $this->log("分析API错误: " . $analysisResult['error']);
            return new AnalysisResult('', date('Y-m-d H:i:s'), $analysisResult['error']);
        }
        
        $this->log("搜索结果分析完成");
        
        return new AnalysisResult(
            $analysisResult['analysis'],
            $analysisResult['timestamp'] ?? date('Y-m-d H:i:s')
        );
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
        $context = "请分析以下搜索结果，提取关键信息，并指出下一步应该深入研究的方向。\n\n";
        $context .= "原始问题: " . $question . "\n\n";
        $context .= "当前研究进度: 第 {$currentRound} 轮（共 {$totalRounds} 轮）\n\n";

        // 添加搜索历史
        if (!empty($searchHistory)) {
            $context .= "搜索历史:\n";
            foreach ($searchHistory as $historyItem) {
                $context .= "第 {$historyItem['round']} 轮: \"{$historyItem['query']}\"\n";
            }
            $context .= "\n";
        }
        
        if ($previousAnalysis) {
            $context .= "先前分析: " . $previousAnalysis . "\n\n";
        }
        
        $context .= "搜索结果:\n";
        foreach ($searchResult->getResults() as $index => $result) {
            $context .= "来源 " . ($index + 1) . ": " . $result['title'] . "\n";
            $context .= "网址: " . $result['url'] . "\n";
            $context .= "内容: " . $result['content'] . "\n\n";
        }
        
        // 修改提示，要求提供下一轮搜索关键词，并考虑全局研究计划
        $context .= "请对以下搜索结果进行分析，输出包括：
    - 每条信息的关键信息汇总在一起，可在其中关键位置说明来自什么来源
    - 来源信息之间的异同、矛盾或空白
    - 你对这些信息的整体看法与分析（重要，包含正反面）
    ";

        // 如果不是最后一轮，请求下一轮搜索关键词
        if ($currentRound < $totalRounds) {
            $context .= "最后，请明确给出下一轮搜索应使用的关键词，格式如下：
    <NEXT_QUERY>你建议的下一轮搜索关键词</NEXT_QUERY>
    ### 只需要一个<NEXT_QUERY></NEXT_QUERY> 即可 ###

    请根据当前分析结果和搜索历史，选择能够更深入探索主题、填补信息空白或解决疑问的关键词。考虑到这是第 {$currentRound} 轮（共 {$totalRounds} 轮）研究，你的目标是通过后续 " . ($totalRounds - $currentRound) . " 轮搜索全面探索主题的各个方面。

    注意不要重复之前已出现在搜索关键词中的内容，而是选择能够扩展知识面、提供新视角或深入细节的新的关键词。理想情况下，每轮搜索都应该为总体研究贡献新的洞见。
    ";
        }

        $context .= "请使用分段或条列形式组织文字，保持内容紧凑、有条理。生成的内容将用于后续模型整合使用。";
        
        return $context;
    }
    
    /**
     * 调用DashScope API
     * 
     * @param string $prompt 分析提示
     * @return array 分析结果
     */
    private function callDashScopeApi(string $prompt): array
    {
        $this->log("调用DashScope API进行分析");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $this->modelName,
            'messages' => [
                ['role' => 'system', 'content' => '# 最重要的要求 你的回复不能拖沓，必须要精炼，节省字数。你需要查看之前的分析，判断是否偏离最初的方向，如果你认为偏离方向，请将方向拉回。 # 角色设定 你是一位在各种领域经验丰富、洞察力深刻的顶级专家分析师。你的思维严谨、注重细节，并擅长从复杂信息中提炼核心观点和发现隐藏的联系。 # 背景情境 你是一个多 Agent 协作研究系统中的关键环节。此前，一个搜索引擎 Agent 已经围绕核心研究主题收集了相关的资料。现在，这些原始资料将提供给你进行专业的深度分析。你的分析结果将作为后续合成报告或决策制定的重要依据。 '],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.6
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $this->apiKey
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return ['error' => '分析API请求失败，HTTP状态码: ' . $httpCode];
        }
        
        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON解析错误: ' . json_last_error_msg()];
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return [
                'analysis' => $data['choices'][0]['message']['content'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            return ['error' => '无法从API响应中提取分析结果'];
        }
    }
    
    /**
     * 调用OpenAI API
     * 
     * @param string $prompt 分析提示
     * @return array 分析结果
     */
    private function callOpenAIApi(string $prompt): array
    {
        $this->log("调用OpenAI API进行分析");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $this->modelName,
            'messages' => [
                ['role' => 'system', 'content' => '# 最重要的要求 你的回复不能拖沓，必须要精炼，节省字数。你需要查看之前的分析，判断是否偏离最初的方向，如果你认为偏离方向，请将方向拉回。 # 角色设定 你是一位在各种领域经验丰富、洞察力深刻的顶级专家分析师。你的思维严谨、注重细节，并擅长从复杂信息中提炼核心观点和发现隐藏的联系。 # 背景情境 你是一个多 Agent 协作研究系统中的关键环节。此前，一个搜索引擎 Agent 已经围绕核心研究主题收集了相关的资料。现在，这些原始资料将提供给你进行专业的深度分析。你的分析结果将作为后续合成报告或决策制定的重要依据。 '],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.6
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $this->apiKey
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return ['error' => '分析API请求失败，HTTP状态码: ' . $httpCode];
        }
        
        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON解析错误: ' . json_last_error_msg()];
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            return [
                'analysis' => $data['choices'][0]['message']['content'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            return ['error' => '无法从API响应中提取分析结果'];
        }
    }
    
    /**
     * 规划下一步应使用的工具
     * 
     * @param string $query 用户查询
     * @param string $context 当前上下文
     * @param bool $hasReaderService 是否配置了内容读取器服务
     * @return array 工具规划结果 ['tool' => 'search'|'read_url', 'target' => '搜索关键词或URL']
     */
    public function planTool(string $query, string $context = '', bool $hasReaderService = false): array
    {
        if (empty($this->plannerApiKey) || empty($this->plannerApiUrl)) {
            // 如果没有配置规划器，默认使用搜索工具
            $this->log("工具规划器未配置，默认使用搜索工具");
            return [
                'tool' => 'search',
                'target' => $query
            ];
        }

        $this->log("开始工具规划...");

        // 构建工具规划的提示
        $prompt = $this->buildPlannerPrompt($query, $context, $hasReaderService);
        
        // 调用规划器API
        $planResult = $this->callPlannerApi($prompt);
        
        if (isset($planResult['error'])) {
            $this->log("工具规划失败: " . $planResult['error']);
            // 规划失败时默认使用搜索
            return [
                'tool' => 'search',
                'target' => $query
            ];
        }

        // 解析规划结果
        return $this->parsePlanResult($planResult['response']);
    }
    
    /**
     * 构建工具规划的提示
     * 
     * @param string $query 用户查询
     * @param string $context 当前上下文
     * @param bool $hasReaderService 是否有读取器服务
     * @return string 规划提示
     */
    private function buildPlannerPrompt(string $query, string $context, bool $hasReaderService): string
    {
        $availableTools = "1. search - 执行网络搜索以获取相关信息";
        if ($hasReaderService) {
            $availableTools .= "\n2. read_url - 从指定的URL读取完整内容";
        }

        $prompt = "你是一个工具规划专家。根据用户的查询和当前上下文，选择最合适的工具来获取信息。

可用工具:
{$availableTools}

用户查询: {$query}

当前上下文: " . ($context ?: "无") . "

请分析查询内容，并选择最合适的工具。如果查询中包含具体的URL或者明确要求分析某个网页内容，选择read_url工具；否则选择search工具进行网络搜索。

请严格按照以下JSON格式返回:
{
    \"tool\": \"search\" 或 \"read_url\",
    \"target\": \"搜索关键词或URL\",
    \"reason\": \"选择此工具的原因\"
}

只返回JSON，不要包含其他文字。";

        return $prompt;
    }
    
    /**
     * 调用工具规划器API
     * 
     * @param string $prompt 规划提示
     * @return array 规划结果
     */
    private function callPlannerApi(string $prompt): array
    {
        $ch = curl_init();
        
        $requestData = [
            'model' => $this->plannerModel,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => 500
        ];

        curl_setopt($ch, CURLOPT_URL, $this->plannerApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $this->plannerApiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            curl_close($ch);
            return ['error' => 'cURL请求失败: ' . curl_error($ch)];
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            return ['error' => '规划器API请求失败，HTTP状态码: ' . $httpCode];
        }

        $data = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON解析错误: ' . json_last_error_msg()];
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return ['response' => $data['choices'][0]['message']['content']];
        } else {
            return ['error' => '无法从规划器API响应中提取结果'];
        }
    }
    
    /**
     * 解析规划结果
     * 
     * @param string $response 规划器响应
     * @return array 解析后的规划结果
     */
    private function parsePlanResult(string $response): array
    {
        // 尝试解析JSON响应
        $jsonData = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['tool'])) {
            $tool = $jsonData['tool'];
            $target = $jsonData['target'] ?? '';
            
            // 验证工具类型
            if (!in_array($tool, ['search', 'read_url'])) {
                $tool = 'search';
            }
            
            $this->log("工具规划完成: {$tool} -> {$target}");
            
            return [
                'tool' => $tool,
                'target' => $target,
                'reason' => $jsonData['reason'] ?? ''
            ];
        }
        
        // 如果JSON解析失败，尝试从文本中提取信息
        if (strpos($response, 'read_url') !== false) {
            // 尝试提取URL
            if (preg_match('/https?:\/\/[^\s]+/', $response, $matches)) {
                return [
                    'tool' => 'read_url',
                    'target' => $matches[0],
                    'reason' => '从响应中检测到URL'
                ];
            }
        }
        
        // 默认返回搜索工具
        $this->log("无法解析规划结果，默认使用搜索工具");
        return [
            'tool' => 'search',
            'target' => $response, // 使用整个响应作为搜索关键词
            'reason' => '解析失败，使用默认搜索'
        ];
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
    
    /**
     * 发送进度更新
     * 
     * @param callable|null $callback 进度回调函数
     * @param string $message 进度消息
     */
    private function sendProgress(?callable $callback, string $message): void
    {
        if ($callback !== null && is_callable($callback)) {
            call_user_func($callback, $message);
        }
    }
    
    /**
     * 获取提供商类型
     * 
     * @return string 提供商类型
     */
    public function getProviderType(): string
    {
        return $this->providerType;
    }
    
    /**
     * 获取API密钥
     * 
     * @return string API密钥
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
    
    /**
     * 获取API URL
     * 
     * @return string API URL
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }
    
    /**
     * 获取模型名称
     * 
     * @return string 模型名称
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }
}
