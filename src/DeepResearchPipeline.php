<?php
/**
 * DeepResearchPipeline.php - 多轮搜索与分析主类
 * 
 * 该类是深度研究功能的主要入口点，负责协调搜索和分析过程，
 * 实现多轮迭代式深入研究功能。
 */

namespace DeepResearch;

use DeepResearch\DTO\SearchResult;
use DeepResearch\DTO\AnalysisResult;
use DeepResearch\DTO\ToolExecutionResult;
use DeepResearch\Util\Logger;

class DeepResearchPipeline
{
    /**
     * @var SearchExecutor 搜索执行器实例
     */
    private $searchExecutor;
    
    /**
     * @var AnalysisExecutor 分析执行器实例
     */
    private $analysisExecutor;
    
    /**
     * @var Logger 日志记录器实例
     */
    private $logger;
    
    /**
     * @var array 搜索历史记录
     */
    private $searchHistory = [];
    
    /**
     * @var array 分析结果集合
     */
    private $analysisCollection = [];
    
    /**
     * @var array 工具执行历史
     */
    private $toolHistory = [];
    
    /**
     * @var bool 是否配置了内容读取器服务
     */
    private $hasReaderService = false;
    
    /**
     * 构造函数
     * 
     * @param SearchExecutor $searchExecutor 搜索执行器实例
     * @param AnalysisExecutor $analysisExecutor 分析执行器实例
     * @param Logger|null $logger 可选的日志记录器实例
     * @param bool $hasReaderService 是否配置了内容读取器服务
     */
    public function __construct(
        SearchExecutor $searchExecutor, 
        AnalysisExecutor $analysisExecutor, 
        ?Logger $logger = null,
        bool $hasReaderService = false
    ) {
        $this->searchExecutor = $searchExecutor;
        $this->analysisExecutor = $analysisExecutor;
        $this->logger = $logger ?? new Logger();
        $this->hasReaderService = $hasReaderService;
    }
    
    /**
     * 执行深度研究
     * 
     * @param string $initialQuery 初始查询关键词
     * @param string $originalQuestion 原始问题（上下文）
     * @param int $depth 研究深度（迭代次数）
     * @param callable|null $eventCallback 事件回调函数，接收 (string $eventType, mixed $data) 参数
     * @return array 包含所有分析结果的数组
     */
    public function executeResearch(
        string $initialQuery, 
        string $originalQuestion, 
        int $depth = 3, 
        ?callable $eventCallback = null
    ): array {
        // 规范化深度参数
        $maxDepth = 10;
        $depth = min(max(2, (int)$depth), $maxDepth);
        
        $this->log("开始深度研究. 查询: '{$initialQuery}', 深度: {$depth}");
        $this->sendEvent($eventCallback, 'research_start', [
            'query' => $initialQuery,
            'depth' => $depth,
            'has_reader_service' => $this->hasReaderService
        ]);
        
        // 重置状态
        $this->searchHistory = [];
        $this->analysisCollection = [];
        $this->toolHistory = [];
        
        $context = $originalQuestion;
        $fullReportContent = '';
        
        // 执行多轮迭代研究
        for ($round = 1; $round <= $depth; $round++) {
            $this->log("开始第 {$round} 轮研究");
            $this->sendEvent($eventCallback, 'round_start', [
                'round' => $round,
                'total_rounds' => $depth
            ]);
            
            // 步骤1: 工具规划
            $this->sendEvent($eventCallback, 'planning_start', ['round' => $round]);
            
            $planResult = $this->analysisExecutor->planTool(
                $round === 1 ? $initialQuery : $context,
                $context,
                $this->hasReaderService
            );
            
            $this->sendEvent($eventCallback, 'planning_complete', $planResult);
            $this->log("第 {$round} 轮工具规划: {$planResult['tool']} -> {$planResult['target']}");
            
            // 步骤2: 执行工具
            $toolResult = $this->executeTool($planResult, $eventCallback, $round);
            
            if (!$toolResult->isSuccess()) {
                $errorMsg = "第 {$round} 轮工具执行失败: " . $toolResult->getError();
                $this->log($errorMsg);
                $this->sendEvent($eventCallback, 'error', ['message' => $errorMsg, 'round' => $round]);
                return ['error' => $errorMsg];
            }
            
            // 记录工具执行历史
            $this->toolHistory[] = $toolResult->toArray();
            
            // 步骤3: 分析工具结果
            $this->sendEvent($eventCallback, 'analysis_start', ['round' => $round]);
            
            $analysisResult = $this->analyzeToolResult($toolResult, $originalQuestion, $context, $eventCallback, $round, $depth);
            
            if ($analysisResult->hasError()) {
                $errorMsg = "第 {$round} 轮分析失败: " . $analysisResult->getError();
                $this->log($errorMsg);
                $this->sendEvent($eventCallback, 'error', ['message' => $errorMsg, 'round' => $round]);
                return ['error' => $errorMsg];
            }
            
            $currentAnalysis = $analysisResult->getAnalysis();
            $this->analysisCollection[] = [
                'round' => $round,
                'tool_used' => $planResult['tool'],
                'target' => $planResult['target'],
                'analysis' => $currentAnalysis,
                'timestamp' => $analysisResult->getTimestamp()
            ];
            
            // 更新上下文
            $context = $this->updateContext($currentAnalysis, $context);
            $fullReportContent .= "第 {$round} 轮分析:\n" . $currentAnalysis . "\n\n";
            
            $this->sendEvent($eventCallback, 'round_complete', [
                'round' => $round,
                'analysis' => $currentAnalysis
            ]);
            
            $this->log("第 {$round} 轮研究完成");
        }
        
        // 步骤4: 生成最终报告
        $this->sendEvent($eventCallback, 'final_report_start', []);
        
        $finalReport = $this->generateFinalReport($originalQuestion, $fullReportContent, $eventCallback);
        
        $this->sendEvent($eventCallback, 'research_complete', [
            'final_report' => $finalReport,
            'rounds_completed' => $depth
        ]);
        
        return [
            'analysis' => $finalReport,
            'rounds' => $this->analysisCollection,
            'tool_history' => $this->toolHistory,
            'search_history' => $this->searchHistory
        ];
    }
    
    /**
     * 整合所有分析结果
     * 
     * @return string 整合后的分析结果
     */
    private function consolidateAnalysis(): string
    {
        $finalAnalysis = "深度研究总结 - 基于 " . count($this->analysisCollection) . " 轮迭代分析\n\n";
        
        foreach ($this->analysisCollection as $index => $analysis) {
            $finalAnalysis .= "第 " . ($index + 1) . " 轮分析:\n";
            $finalAnalysis .= $analysis['analysis'] . "\n\n";
        }
        
        return $finalAnalysis;
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
     * 获取搜索历史
     * 
     * @return array 搜索历史记录
     */
    public function getSearchHistory(): array
    {
        return $this->searchHistory;
    }
    
    /**
     * 获取分析结果集合
     * 
     * @return array 分析结果集合
     */
    public function getAnalysisCollection(): array
    {
        return $this->analysisCollection;
    }
    
    /**
     * 执行指定的工具
     * 
     * @param array $planResult 工具规划结果
     * @param callable|null $eventCallback 事件回调函数
     * @param int $round 当前轮次
     * @return ToolExecutionResult 工具执行结果
     */
    private function executeTool(array $planResult, ?callable $eventCallback, int $round): ToolExecutionResult
    {
        $tool = $planResult['tool'];
        $target = $planResult['target'];
        
        $this->sendEvent($eventCallback, 'tool_start', [
            'tool' => $tool,
            'target' => $target,
            'round' => $round
        ]);
        
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            switch ($tool) {
                case 'search':
                    $this->log("执行搜索工具: {$target}");
                    $result = $this->searchExecutor->executeSearch($target, true);
                    
                    if ($result->hasError()) {
                        return new ToolExecutionResult($tool, $target, null, $timestamp, $result->getError(), false);
                    }
                    
                    // 记录搜索历史
                    $this->searchHistory[] = [
                        'round' => $round,
                        'query' => $target,
                        'results_count' => count($result->getResults())
                    ];
                    
                    $this->sendEvent($eventCallback, 'tool_output', [
                        'tool' => $tool,
                        'results_count' => count($result->getResults()),
                        'round' => $round
                    ]);
                    
                    return new ToolExecutionResult($tool, $target, $result, $timestamp);
                    
                case 'read_url':
                    $this->log("执行URL读取工具: {$target}");
                    $content = $this->searchExecutor->getContentFromUrl($target);
                    
                    if ($content === null) {
                        return new ToolExecutionResult($tool, $target, null, $timestamp, "无法从URL提取内容", false);
                    }
                    
                    $this->sendEvent($eventCallback, 'tool_output', [
                        'tool' => $tool,
                        'url' => $target,
                        'content_length' => strlen($content),
                        'round' => $round
                    ]);
                    
                    return new ToolExecutionResult($tool, $target, $content, $timestamp);
                    
                default:
                    return new ToolExecutionResult($tool, $target, null, $timestamp, "未知的工具类型: {$tool}", false);
            }
        } catch (\Exception $e) {
            $this->log("工具执行异常: " . $e->getMessage());
            return new ToolExecutionResult($tool, $target, null, $timestamp, $e->getMessage(), false);
        }
    }
    
    /**
     * 分析工具执行结果
     * 
     * @param ToolExecutionResult $toolResult 工具执行结果
     * @param string $originalQuestion 原始问题
     * @param string $context 当前上下文
     * @param callable|null $eventCallback 事件回调函数
     * @param int $round 当前轮次
     * @param int $totalRounds 总轮次
     * @return AnalysisResult 分析结果
     */
    private function analyzeToolResult(
        ToolExecutionResult $toolResult, 
        string $originalQuestion, 
        string $context, 
        ?callable $eventCallback, 
        int $round, 
        int $totalRounds
    ): AnalysisResult {
        // 构建分析提示
        $prompt = $this->buildAnalysisPrompt($toolResult, $originalQuestion, $context, $round, $totalRounds);
        
        // 使用现有的分析执行器进行分析
        // 创建一个虚拟的SearchResult来兼容现有的接口
        $virtualSearchResult = new SearchResult(
            [['title' => $toolResult->getTarget(), 'url' => '', 'content' => $toolResult->getFormattedContent()]],
            $toolResult->getTarget(),
            $toolResult->getTimestamp()
        );
        
        return $this->analysisExecutor->analyzeResults(
            $virtualSearchResult,
            $originalQuestion,
            $context,
            function($message) use ($eventCallback) {
                $this->sendEvent($eventCallback, 'analysis_progress', ['message' => $message]);
            },
            $round,
            $totalRounds,
            $this->searchHistory
        );
    }
    
    /**
     * 构建分析提示
     * 
     * @param ToolExecutionResult $toolResult 工具执行结果
     * @param string $originalQuestion 原始问题
     * @param string $context 当前上下文
     * @param int $round 当前轮次
     * @param int $totalRounds 总轮次
     * @return string 分析提示
     */
    private function buildAnalysisPrompt(
        ToolExecutionResult $toolResult, 
        string $originalQuestion, 
        string $context, 
        int $round, 
        int $totalRounds
    ): string {
        $prompt = "你是一位资深的研究分析专家。请分析以下信息并提供深入的洞察。\n\n";
        $prompt .= "原始问题: {$originalQuestion}\n\n";
        $prompt .= "当前研究进度: 第 {$round} 轮（共 {$totalRounds} 轮）\n\n";
        
        if (!empty($context) && $context !== $originalQuestion) {
            $prompt .= "研究上下文:\n{$context}\n\n";
        }
        
        $prompt .= "本轮使用的工具: " . $toolResult->getToolType() . "\n";
        $prompt .= "工具目标: " . $toolResult->getTarget() . "\n\n";
        
        $prompt .= "获取的信息:\n";
        $prompt .= $toolResult->getFormattedContent() . "\n\n";
        
        $prompt .= "请提供以下分析:\n";
        $prompt .= "1. 信息摘要：总结获取到的关键信息\n";
        $prompt .= "2. 深入分析：对信息进行深入解读和分析\n";
        $prompt .= "3. 关联性分析：这些信息如何回答原始问题\n";
        $prompt .= "4. 发现的问题或空白：还有哪些方面需要进一步探索\n\n";
        
        if ($round < $totalRounds) {
            $prompt .= "5. 下一步建议：为了更全面地回答原始问题，下一轮研究应该关注什么？\n\n";
            $prompt .= "请在分析结尾处，用以下格式明确给出下一轮的研究方向：\n";
            $prompt .= "<NEXT_QUERY>具体的搜索关键词或URL</NEXT_QUERY>\n\n";
            $prompt .= "注意：如果已经获得了较全面的信息，可以建议搜索相关的不同角度或更深入的细节。\n";
        }
        
        $prompt .= "请确保分析结构清晰、内容详实，便于后续整合。";
        
        return $prompt;
    }
    
    /**
     * 更新研究上下文
     * 
     * @param string $newAnalysis 新的分析结果
     * @param string $previousContext 之前的上下文
     * @return string 更新后的上下文
     */
    private function updateContext(string $newAnalysis, string $previousContext): string
    {
        // 提取关键信息，构建累积的上下文
        $contextUpdate = "最新发现: " . substr($newAnalysis, 0, 500) . "...\n\n";
        
        // 如果之前的上下文太长，只保留最近的部分
        if (strlen($previousContext) > 2000) {
            $previousContext = "...(前期研究总结)...\n" . substr($previousContext, -1500);
        }
        
        return $previousContext . $contextUpdate;
    }
    
    /**
     * 生成最终研究报告
     * 
     * @param string $originalQuestion 原始问题
     * @param string $fullReportContent 完整报告内容
     * @param callable|null $eventCallback 事件回调函数
     * @return string 最终报告
     */
    private function generateFinalReport(string $originalQuestion, string $fullReportContent, ?callable $eventCallback): string
    {
        $prompt = "请基于以下多轮深度研究的结果，生成一份完整、专业的研究报告。\n\n";
        $prompt .= "研究问题: {$originalQuestion}\n\n";
        $prompt .= "研究过程和发现:\n{$fullReportContent}\n\n";
        $prompt .= "请生成一份结构化的最终报告，包括：\n";
        $prompt .= "1. 执行摘要\n";
        $prompt .= "2. 主要发现\n";
        $prompt .= "3. 详细分析\n";
        $prompt .= "4. 结论和建议\n";
        $prompt .= "5. 参考来源总结\n\n";
        $prompt .= "请确保报告内容完整、逻辑清晰、结论有据。";
        
        // 使用现有的分析执行器生成最终报告
        $virtualSearchResult = new SearchResult(
            [['title' => '研究总结', 'url' => '', 'content' => $fullReportContent]],
            $originalQuestion,
            date('Y-m-d H:i:s')
        );
        
        $result = $this->analysisExecutor->analyzeResults(
            $virtualSearchResult,
            $originalQuestion,
            $fullReportContent,
            function($message) use ($eventCallback) {
                $this->sendEvent($eventCallback, 'final_report_progress', ['message' => $message]);
            },
            999, // 标记为最终报告轮次
            999,
            $this->searchHistory
        );
        
        if ($result->hasError()) {
            $this->log("最终报告生成失败: " . $result->getError());
            return $this->generateFallbackReport($fullReportContent);
        }
        
        return $result->getAnalysis();
    }
    
    /**
     * 生成备用报告（当AI生成失败时）
     * 
     * @param string $fullReportContent 完整报告内容
     * @return string 备用报告
     */
    private function generateFallbackReport(string $fullReportContent): string
    {
        return "# 深度研究报告\n\n" . 
               "## 研究过程\n\n" . 
               $fullReportContent . 
               "\n\n## 说明\n\n" . 
               "本报告基于多轮迭代研究生成，由于技术原因未能生成结构化总结，但研究过程和发现已完整记录在上述内容中。";
    }
    
    /**
     * 发送事件
     * 
     * @param callable|null $callback 事件回调函数
     * @param string $eventType 事件类型
     * @param mixed $data 事件数据
     */
    private function sendEvent(?callable $callback, string $eventType, $data = null): void
    {
        if ($callback !== null && is_callable($callback)) {
            call_user_func($callback, $eventType, $data);
        }
        
        // 同时记录日志
        $this->log("事件: {$eventType} - " . json_encode($data));
    }
}
