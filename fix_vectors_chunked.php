<?php
/**
 * 改進版向量嵌入修復程式
 * 處理大文檔的分塊和向量嵌入生成
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
 * 分割文檔為較小的塊
 */
function splitContent($content, $maxBytes = 30000) {
    $chunks = [];
    $currentChunk = '';
    
    // 按句子分割
    $sentences = preg_split('/(?<=[。！？.!?])\s+/u', $content);
    
    foreach ($sentences as $sentence) {
        // 如果加入這個句子會超過限制，就開始新的塊
        if (strlen($currentChunk . $sentence) > $maxBytes && !empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
            $currentChunk = '';
        }
        $currentChunk .= $sentence . ' ';
    }
    
    if (!empty($currentChunk)) {
        $chunks[] = trim($currentChunk);
    }
    
    // 如果單個塊仍然太大，按字符數分割
    $finalChunks = [];
    foreach ($chunks as $chunk) {
        if (strlen($chunk) > $maxBytes) {
            $splitChunks = str_split($chunk, $maxBytes);
            $finalChunks = array_merge($finalChunks, $splitChunks);
        } else {
            $finalChunks[] = $chunk;
        }
    }
    
    return $finalChunks;
}

try {
    // 首先清除舊的向量嵌入
    $vectorDb = new PDO('sqlite:vector_db.sqlite');
    $vectorDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 刪除所有現有的嵌入
    $vectorDb->exec('DELETE FROM embeddings');
    $results[] = "已清除舊的向量嵌入";
    
    // 獲取所有文檔
    $documentStore = new PDO('sqlite:document_store.sqlite');
    $documentStore->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $documentStore->query('SELECT doc_id, title, content FROM documents');
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "找到 " . count($documents) . " 個文檔需要處理";
    
    // 為每個文檔生成新的向量嵌入
    foreach ($documents as $doc) {
        try {
            $contentSize = strlen($doc['content']);
            $results[] = "處理文檔 {$doc['doc_id']}，大小：{$contentSize} 字節";
            
            // 如果文檔太大，分塊處理
            if ($contentSize > 30000) {
                $chunks = splitContent($doc['content'], 25000); // 使用較小的限制
                $results[] = "文檔 {$doc['doc_id']} 被分成 " . count($chunks) . " 個塊";
                
                foreach ($chunks as $index => $chunk) {
                    try {
                        // 生成嵌入向量
                        $embedding = $ragSystem->getEmbedding($chunk);
                        
                        // 為分塊創建唯一的 doc_id
                        $chunkDocId = $doc['doc_id'] . '_chunk_' . $index;
                        
                        // 存儲嵌入向量
                        $stmt = $vectorDb->prepare('INSERT INTO embeddings (doc_id, embedding) VALUES (?, ?)');
                        $stmt->execute([$chunkDocId, json_encode($embedding)]);
                        
                        // 如果需要，也存儲分塊到文檔表
                        $stmt = $documentStore->prepare('INSERT OR REPLACE INTO documents (doc_id, title, content, metadata) VALUES (?, ?, ?, ?)');
                        $chunkTitle = $doc['title'] . ' (塊 ' . ($index + 1) . ')';
                        $metadata = json_encode(['original_doc_id' => $doc['doc_id'], 'chunk_index' => $index]);
                        $stmt->execute([$chunkDocId, $chunkTitle, $chunk, $metadata]);
                        
                        $results[] = "成功為文檔 {$doc['doc_id']} 的塊 {$index} 生成向量嵌入";
                        
                        // 避免 API 限制，添加延遲
                        usleep(500000); // 0.5 秒延遲
                        
                    } catch (Exception $e) {
                        $results[] = "錯誤：無法為文檔 {$doc['doc_id']} 的塊 {$index} 生成向量嵌入 - " . $e->getMessage();
                    }
                }
            } else {
                // 正常處理小文檔
                $embedding = $ragSystem->getEmbedding($doc['content']);
                
                $stmt = $vectorDb->prepare('INSERT INTO embeddings (doc_id, embedding) VALUES (?, ?)');
                $stmt->execute([$doc['doc_id'], json_encode($embedding)]);
                
                $results[] = "成功為文檔 {$doc['doc_id']} 生成向量嵌入";
                
                usleep(500000); // 0.5 秒延遲
            }
            
        } catch (Exception $e) {
            $results[] = "錯誤：處理文檔 {$doc['doc_id']} 時發生錯誤 - " . $e->getMessage();
        }
    }
    
    // 驗證結果
    $stmt = $vectorDb->query('SELECT COUNT(*) as count FROM embeddings');
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $results[] = "完成！現在資料庫中有 {$count} 個向量嵌入";
    
    // 清理重複的文檔（移除原始的大文檔，保留分塊）
    $results[] = "清理處理...";
    $stmt = $documentStore->query('SELECT doc_id FROM documents WHERE doc_id LIKE "%_chunk_%"');
    $chunkDocs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($chunkDocs as $chunkDoc) {
        // 獲取原始文檔 ID
        $originalDocId = preg_replace('/_chunk_\d+$/', '', $chunkDoc);
        
        // 如果有分塊，刪除原始文檔
        $stmt = $documentStore->prepare('DELETE FROM documents WHERE doc_id = ? AND doc_id NOT LIKE "%_chunk_%"');
        $stmt->execute([$originalDocId]);
    }
    
} catch (Exception $e) {
    $results[] = "發生錯誤：" . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>改進版向量嵌入修復</title>
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
        <h1>改進版向量嵌入修復</h1>
        
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
            <a href="index.php" class="button">返回首頁</a>
            <a href="vector_test.php" class="button">再次測試向量</a>
        </div>
    </div>
</body>
</html>