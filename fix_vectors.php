<?php
/**
 * 修復向量嵌入程式
 * 用於重新生成所有文檔的向量嵌入
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
            // 生成嵌入向量
            $embedding = $ragSystem->getEmbedding($doc['content']);
            
            // 存儲嵌入向量
            $stmt = $vectorDb->prepare('INSERT INTO embeddings (doc_id, embedding) VALUES (?, ?)');
            $stmt->execute([$doc['doc_id'], json_encode($embedding)]);
            
            $results[] = "成功為文檔 {$doc['doc_id']} 生成向量嵌入";
            
            // 避免 API 限制，添加短暫延遲
            usleep(500000); // 0.5 秒延遲
            
        } catch (Exception $e) {
            $results[] = "錯誤：無法為文檔 {$doc['doc_id']} 生成向量嵌入 - " . $e->getMessage();
        }
    }
    
    // 驗證結果
    $stmt = $vectorDb->query('SELECT COUNT(*) as count FROM embeddings');
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $results[] = "完成！現在資料庫中有 {$count} 個向量嵌入";
    
} catch (Exception $e) {
    $results[] = "發生錯誤：" . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修復向量嵌入</title>
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
        <h1>修復向量嵌入</h1>
        
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