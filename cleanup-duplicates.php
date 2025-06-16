<?php
/**
 * 清理重複文件程式
 * 用於清理重複的文檔和向量嵌入
 */

// 載入環境變數
require_once 'env_loader.php';
// 載入 RAG 系統核心類
require_once 'gemini_rag_system.php';
// 載入配置
$config = require_once 'config.php';

// 開始清理過程
$results = [];

try {
    // 連接資料庫
    $vectorDb = new PDO('sqlite:vector_db.sqlite');
    $vectorDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $documentStore = new PDO('sqlite:document_store.sqlite');
    $documentStore->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. 分析重複文件
    $stmt = $documentStore->query('SELECT doc_id, title, LENGTH(content) as content_length FROM documents ORDER BY title, doc_id');
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $groupedDocs = [];
    foreach ($documents as $doc) {
        $baseTitle = preg_replace('/\s*\(塊 \d+\)\s*$/', '', $doc['title']);
        $baseTitle = preg_replace('/\s*\(Part \d+\)\s*$/', '', $baseTitle);
        if (!isset($groupedDocs[$baseTitle])) {
            $groupedDocs[$baseTitle] = [];
        }
        $groupedDocs[$baseTitle][] = $doc;
    }
    
    $results[] = "找到 " . count($groupedDocs) . " 組文檔";
    
    // 2. 處理每組重複文件
    foreach ($groupedDocs as $baseTitle => $docs) {
        if (count($docs) > 1) {
            $results[] = "處理重複組：{$baseTitle} (" . count($docs) . " 個文件)";
            
            // 找到最大的文件（假設它是最完整的）
            $largestDoc = null;
            $maxLength = 0;
            
            foreach ($docs as $doc) {
                if ($doc['content_length'] > $maxLength) {
                    $maxLength = $doc['content_length'];
                    $largestDoc = $doc;
                }
            }
            
            // 保留最大的文件，刪除其他
            foreach ($docs as $doc) {
                if ($doc['doc_id'] !== $largestDoc['doc_id']) {
                    // 刪除文檔
                    $stmt = $documentStore->prepare('DELETE FROM documents WHERE doc_id = ?');
                    $stmt->execute([$doc['doc_id']]);
                    
                    // 刪除對應的向量嵌入
                    $stmt = $vectorDb->prepare('DELETE FROM embeddings WHERE doc_id = ?');
                    $stmt->execute([$doc['doc_id']]);
                    
                    $results[] = "刪除文檔：{$doc['doc_id']} ({$doc['title']})";
                }
            }
            
            $results[] = "保留文檔：{$largestDoc['doc_id']} ({$largestDoc['title']})";
        }
    }
    
    // 3. 清理孤立的向量嵌入
    $stmt = $vectorDb->query('SELECT doc_id FROM embeddings');
    $embeddings = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($embeddings as $embeddingDocId) {
        $stmt = $documentStore->prepare('SELECT COUNT(*) FROM documents WHERE doc_id = ?');
        $stmt->execute([$embeddingDocId]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            // 刪除孤立的向量嵌入
            $stmt = $vectorDb->prepare('DELETE FROM embeddings WHERE doc_id = ?');
            $stmt->execute([$embeddingDocId]);
            $results[] = "刪除孤立的向量嵌入：{$embeddingDocId}";
        }
    }
    
    // 4. 顯示清理後的統計
    $stmt = $documentStore->query('SELECT COUNT(*) as count FROM documents');
    $docCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $vectorDb->query('SELECT COUNT(*) as count FROM embeddings');
    $embeddingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $results[] = "清理完成！";
    $results[] = "剩餘文檔數量：{$docCount}";
    $results[] = "剩餘向量嵌入數量：{$embeddingCount}";
    
} catch (Exception $e) {
    $results[] = "發生錯誤：" . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清理重複文件</title>
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
        .result.warning {
            background-color: #fff3cd;
            color: #856404;
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
        <h1>清理重複文件</h1>
        
        <?php foreach ($results as $result): ?>
            <div class="result <?php 
                if (strpos($result, '錯誤') === 0) echo 'error';
                elseif (strpos($result, '成功') === 0 || strpos($result, '保留') === 0) echo 'success';
                elseif (strpos($result, '刪除') === 0) echo 'warning';
                else echo 'info';
            ?>">
                <?php echo htmlspecialchars($result); ?>
            </div>
        <?php endforeach; ?>
        
        <div class="actions">
            <a href="fix_vectors_chunked.php" class="button">重新生成向量嵌入</a>
            <a href="vector_test.php" class="button">測試向量</a>
            <a href="index.php" class="button">返回首頁</a>
        </div>
    </div>
</body>
</html>