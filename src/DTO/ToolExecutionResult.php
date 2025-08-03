<?php
/**
 * ToolExecutionResult.php - 工具执行结果数据传输对象
 * 
 * 该类封装了各种工具（搜索、URL读取等）执行的结果数据，
 * 提供统一的数据格式和接口。
 */

namespace DeepResearch\DTO;

class ToolExecutionResult
{
    /**
     * @var string 工具类型
     */
    private $toolType;
    
    /**
     * @var string 工具目标（搜索关键词或URL）
     */
    private $target;
    
    /**
     * @var mixed 工具执行结果
     */
    private $result;
    
    /**
     * @var string 时间戳
     */
    private $timestamp;
    
    /**
     * @var string|null 错误信息
     */
    private $error;
    
    /**
     * @var bool 执行是否成功
     */
    private $success;
    
    /**
     * 构造函数
     * 
     * @param string $toolType 工具类型 (search|read_url)
     * @param string $target 工具目标
     * @param mixed $result 执行结果
     * @param string $timestamp 时间戳
     * @param string|null $error 错误信息
     * @param bool $success 是否成功
     */
    public function __construct(
        string $toolType,
        string $target,
        $result,
        string $timestamp,
        ?string $error = null,
        bool $success = true
    ) {
        $this->toolType = $toolType;
        $this->target = $target;
        $this->result = $result;
        $this->timestamp = $timestamp;
        $this->error = $error;
        $this->success = $success;
    }
    
    /**
     * 获取工具类型
     * 
     * @return string 工具类型
     */
    public function getToolType(): string
    {
        return $this->toolType;
    }
    
    /**
     * 获取工具目标
     * 
     * @return string 工具目标
     */
    public function getTarget(): string
    {
        return $this->target;
    }
    
    /**
     * 获取执行结果
     * 
     * @return mixed 执行结果
     */
    public function getResult()
    {
        return $this->result;
    }
    
    /**
     * 获取时间戳
     * 
     * @return string 时间戳
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }
    
    /**
     * 获取错误信息
     * 
     * @return string|null 错误信息
     */
    public function getError(): ?string
    {
        return $this->error;
    }
    
    /**
     * 检查执行是否成功
     * 
     * @return bool 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
    
    /**
     * 获取格式化的结果内容
     * 
     * @return string 格式化的内容
     */
    public function getFormattedContent(): string
    {
        if (!$this->success) {
            return "工具执行失败: " . ($this->error ?? '未知错误');
        }
        
        switch ($this->toolType) {
            case 'search':
                if ($this->result instanceof \DeepResearch\DTO\SearchResult) {
                    $content = "搜索结果:\n";
                    foreach ($this->result->getResults() as $index => $item) {
                        $content .= "来源 " . ($index + 1) . ": " . $item['title'] . "\n";
                        $content .= "网址: " . $item['url'] . "\n";
                        $content .= "内容: " . $item['content'] . "\n\n";
                    }
                    return $content;
                }
                break;
                
            case 'read_url':
                if (is_string($this->result)) {
                    return "URL内容:\n来源: " . $this->target . "\n内容: " . $this->result;
                }
                break;
        }
        
        return "无法格式化结果内容";
    }
    
    /**
     * 转换为数组
     * 
     * @return array 数组表示
     */
    public function toArray(): array
    {
        return [
            'tool_type' => $this->toolType,
            'target' => $this->target,
            'result' => $this->result,
            'timestamp' => $this->timestamp,
            'error' => $this->error,
            'success' => $this->success
        ];
    }
}
