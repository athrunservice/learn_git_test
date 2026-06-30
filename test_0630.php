<?php

/**
 * Aho-Corasick 自动机（多模式字符串匹配）
 * 支持中文、英文等多种字符
 */
class AhoCorasick {
    private $trie = [];        // 字典树节点
    private $nodeCount = 0;    // 节点计数
    private $output = [];      // 输出模式（每个节点结束的模式索引）
    private $fail = [];        // 失败指针
    private $patterns = [];    // 存储所有模式串

    public function __construct() {
        // 初始化根节点 (0)
        $this->trie[0] = [];
        $this->fail[0] = 0;
        $this->output[0] = [];
    }

    /**
     * 插入模式串
     * @param string $pattern 模式串
     * @param int $index 模式索引（可选，用于标识）
     */
    public function insert(string $pattern, int $index = null) {
        if ($index === null) {
            $index = count($this->patterns);
        }
        $this->patterns[] = $pattern;

        $node = 0;
        $len = mb_strlen($pattern, 'UTF-8');
        
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($pattern, $i, 1, 'UTF-8');
            
            if (!isset($this->trie[$node][$char])) {
                $this->nodeCount++;
                $this->trie[$node][$char] = $this->nodeCount;
                $this->trie[$this->nodeCount] = [];
                $this->fail[$this->nodeCount] = 0;
                $this->output[$this->nodeCount] = [];
            }
            $node = $this->trie[$node][$char];
        }
        
        $this->output[$node][] = $index;  // 记录在此节点结束的模式
    }

    /**
     * 构建失败指针（BFS）
     */
    public function build() {
        $queue = [];
        
        // 第一层字符的 fail 指向根
        foreach ($this->trie[0] as $char => $child) {
            $this->fail[$child] = 0;
            $queue[] = $child;
        }
        
        while (!empty($queue)) {
            $current = array_shift($queue);
            
            foreach ($this->trie[$current] as $char => $child) {
                $queue[] = $child;
                
                $state = $this->fail[$current];
                
                // 沿着 fail 指针查找能匹配当前字符的状态
                while ($state != 0 && !isset($this->trie[$state][$char])) {
                    $state = $this->fail[$state];
                }
                
                if (isset($this->trie[$state][$char])) {
                    $state = $this->trie[$state][$char];
                }
                
                $this->fail[$child] = $state;
                
                // 合并输出（output link）
                $this->output[$child] = array_merge(
                    $this->output[$child],
                    $this->output[$state]
                );
            }
        }
    }

    /**
     * 在文本中查找所有模式匹配
     * @param string $text 待搜索文本
     * @return array 匹配结果 [位置 => [模式索引, ...]]
     */
    public function search(string $text): array {
        $matches = [];
        $state = 0;
        $len = mb_strlen($text, 'UTF-8');
        
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            
            // 沿着 fail 指针找到能匹配的节点
            while ($state != 0 && !isset($this->trie[$state][$char])) {
                $state = $this->fail[$state];
            }
            
            if (isset($this->trie[$state][$char])) {
                $state = $this->trie[$state][$char];
            } else {
                $state = 0;
            }
            
            // 输出当前节点及通过 fail 链的所有匹配
            if (!empty($this->output[$state])) {
                foreach ($this->output[$state] as $patternIndex) {
                    $pos = $i - mb_strlen($this->patterns[$patternIndex], 'UTF-8') + 1;
                    if (!isset($matches[$pos])) {
                        $matches[$pos] = [];
                    }
                    $matches[$pos][] = $patternIndex;
                }
            }
        }
        
        return $matches;
    }

    /**
     * 获取所有模式串
     */
    public function getPatterns(): array {
        return $this->patterns;
    }
}

// ====================== 使用示例 ======================

$ac = new AhoCorasick();

// 插入多个模式
$ac->insert("he");
$ac->insert("she");
$ac->insert("his");
$ac->insert("hers");
$ac->insert("她");
$ac->insert("他的");

// 构建自动机
$ac->build();

$text = "ahishershehis他的";

// 搜索
$matches = $ac->search($text);

echo "文本: {$text}\n\n";
echo "匹配结果:\n";

foreach ($matches as $pos => $indices) {
    echo "位置 {$pos}: ";
    foreach ($indices as $idx) {
        echo $ac->getPatterns()[$idx] . " ";
    }
    echo "\n";
}