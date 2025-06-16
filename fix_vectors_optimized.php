<?php
/**
 * 優化版向量嵌入修復程式
 * 針對大文檔使用更小的分塊處理
 */

// 載入環境變數
require_once 'env_loader.php';
// 載入 RAG 系統核心類
require_once 'gemini_rag_system.php';
// 載入配置
$config = require_once 'config.php';

// API 密鑰
$apiKey = getenv('GEMINI_API_KEY') ?: $config['gemini']['api_key'] ?? '';

// 初始化 RAG 系統
$ragSystem = new GeminiRAGSystem($apiKey);

// 開始修復過程
$results = [];

/**
 * 更精細的分割文檔函數
 */
function splitContent($content, $maxBytes = 10000) { // 進一步降低限制到 10000
    $chunks = [];
    $currentChunk = '';
    
    // 按句子分割
    $sentences = preg_split('/(?<=[。！？.!?])\s+/u', $content);
    
    foreach ($sentences as $sentence) {
        // 如果單個句子就超過限制，按字元分割
        if (strlen($sentence) > $maxBytes) {
            if (!empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }
            
            // 按字元分割超長句子
            $words = preg_split('//u', $sentence, -1, PREG_SPLIT_NO_EMPTY);
            $tempChunk = '';
            
            foreach ($words as $word) {
                if (strlen($tempChunk . $word) > $maxBytes) {
                    if (!empty($tempChunk)) {
                        $chunks[] = $tempChunk;
                        $tempChunk = '';
                    }
                }
                $tempChunk .= $word;
            }
            
            if (!empty($tempChunk)) {
                $chunks[] = $tempChunk;
            }
        } else {
            // 如果加入這個句子會超過限制，就開始新的塊
            if (strlen($currentChunk . $sentence) > $maxBytes && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = '';
            }
            $currentChunk .= $sentence . ' ';
        }
    }
    
    if (!empty($currentChunk)) {
        $chunks[] = trim($currentChunk);
    }
    
    return $chunks;
}

try {
    // 連接資料庫
    $vectorDb = new PDO('sqlite:vector_db.sqlite');
    $vectorDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $documentStore = new PDO('sqlite:document_store.sqlite');
    $documentStore->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 獲取所有文檔
    $stmt = $documentStore->query('SELECT doc_id, title, content FROM documents');
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "找到 " . count($documents) . " 個文檔需要處理";
    
    // 為每個文檔生成新的向量嵌入
    foreach ($documents as $doc) {
        try {
            // 檢查是否已有向量嵌入
            $stmt = $vectorDb->prepare('SELECT COUNT(*) FROM embeddings WHERE doc_id = ?');
            $stmt->execute([$doc['doc_id']]);
            $embeddingExists = $stmt->fetchColumn() > 0;
            
            $contentSize = strlen($doc['content']);
            $results[] = "處理文檔 {$doc['doc_id']}，大小：{$contentSize} 字節";
            
            if (!$embeddingExists) {
                // 對於大文檔，進行分塊處理
                if ($contentSize > 10000) {
                    $chunks = splitContent($doc['content'], 8000); // 使用更小的分塊
                    $results[] = "文檔 {$doc['doc_id']} 被分成 " . count($chunks) . " 個塊";
                    
                    // 為每個塊生成向量並求平均
                    $embeddingSum = [];
                    $embeddingCount = 0;
                    
                    foreach ($chunks as $index => $chunk) {
                        try {
                            $embedding = $ragSystem->getEmbedding($chunk);
                            
                            // 如果是第一個嵌入，初始化 embeddingSum
                            if (empty($embeddingSum)) {
                                $embeddingSum = array_fill(0, count($embedding), 0);
                            }
                            
                            // 累加嵌入向量
                            foreach ($embedding as $i => $value) {
                                $embeddingSum[$i] += $value;
                            }
                            $embeddingCount++;
                            
                            $results[] = "成功處理塊 " . ($index + 1) . "/" . count($chunks);
                            sleep(1); // 避免 API 限制
                            
                        } catch (Exception $e) {
                            $results[] = "錯誤：處理塊 {$index} 時發生錯誤 - " . $e->getMessage();
                        }
                    }
                    
                    // 計算平均嵌入向量
                    if ($embeddingCount > 0) {
                        $averageEmbedding = array_map(function($sum) use ($embeddingCount) {
                            return $sum / $embeddingCount;
                        }, $embeddingSum);
                        
                        // 存儲平均嵌入向量
                        $stmt = $vectorDb->prepare('INSERT INTO embeddings (doc_id, embedding) VALUES (?, ?)');
                        $stmt->execute([$doc['doc_id'], json_encode($averageEmbedding)]);
                        
                        $results[] = "成功為文檔 {$doc['doc_id']} 生成平均向量嵌入";
                    }
                } else {
                    // 小文檔直接處理
                    $embedding = $ragSystem->getEmbedding($doc['content']);
                    
                    $stmt = $vectorDb->prepare('INSERT INTO embeddings (doc_id, embedding) VALUES (?, ?)');
                    $stmt->execute([$doc['doc_id'], json_encode($embedding)]);
                    
                    $results[] = "成功為文檔 {$doc['doc_id']} 生成向量嵌入";
                }
            } else {
                $results[] = "文檔 {$doc['doc_id']} 已有向量嵌入，跳過";
            }
            
        } catch (Exception $e) {
            $results[] = "錯誤：處理文檔 {$doc['doc_id']} 時發生錯誤 - " . $e->getMessage();
        }
    }
    
    // 驗證結果
    $stmt = $vectorDb->query('SELECT COUNT(*) as count FROM embeddings');
    $embeddingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $results[] = "完成！現在資料庫中有 {$embeddingCount} 個向量嵌入";
    
} catch (Exception $e) {
    $results[] = "發生錯誤：" . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>優化版向量嵌入修復</title>
    <style>
        body {
            font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .result.success {
            background-color: #d4edda;
            color: #155724;
        }
        .result.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .result.info {
            background-color: #cce5ff;
            color: #004085;
        }
        .actions {
            margin-top: 20px;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 10px;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>優化版向量嵌入修復</h1>
        
        <?php foreach ($results as $result): ?>
            <div class="result <?php 
                if (strpos($result, '錯誤') === 0) echo 'error';
                elseif (strpos($result, '成功') === 0) echo 'success';
                else echo 'info';
            ?>">
                <?php echo htmlspecialchars($result); ?>
            </div>
        <?php endforeach; ?>
        
        <div class="actions">
            <a href="vector_test.php" class="button">測試向量</a>
            <a href="index.php" class="button">返回首頁</a>
        </div>
    </div>
</body>
</html>