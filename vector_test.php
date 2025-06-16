<?php
/**
 * 向量嵌入測試程式
 * 用於診斷向量資料庫問題
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

// 檢查向量資料庫
try {
    $vectorDb = new PDO('sqlite:vector_db.sqlite');
    $vectorDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 獲取所有嵌入
    $stmt = $vectorDb->query('SELECT id, doc_id, embedding FROM embeddings');
    $embeddings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 檢查文檔資料庫
    $documentStore = new PDO('sqlite:document_store.sqlite');
    $documentStore->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 獲取所有文檔
    $stmt = $documentStore->query('SELECT doc_id, title, SUBSTR(content, 1, 200) as content_preview FROM documents');
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("資料庫錯誤: " . $e->getMessage());
}

// 測試查詢
$testQuery = "有那些入學管道？";
$testResult = [];

try {
    // 獲取查詢的嵌入向量
    $queryEmbedding = $ragSystem->getEmbedding($testQuery);
    $testResult['query_embedding_length'] = count($queryEmbedding);
    $testResult['query_embedding_sample'] = array_slice($queryEmbedding, 0, 5);
    
    // 手動計算相似度
    $similarities = [];
    foreach ($embeddings as $embedding) {
        $docEmbedding = json_decode($embedding['embedding'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $similarities[] = [
                'doc_id' => $embedding['doc_id'],
                'error' => 'JSON decode error: ' . json_last_error_msg()
            ];
            continue;
        }
        
        // 計算餘弦相似度
        $similarity = cosineSimilarity($queryEmbedding, $docEmbedding);
        $similarities[] = [
            'doc_id' => $embedding['doc_id'],
            'similarity' => $similarity,
            'doc_embedding_length' => count($docEmbedding)
        ];
    }
    
    // 按相似度排序
    usort($similarities, function($a, $b) {
        return ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0);
    });
    
    $testResult['similarities'] = $similarities;
    
} catch (Exception $e) {
    $testResult['error'] = $e->getMessage();
}

// 餘弦相似度計算函數
function cosineSimilarity($vec1, $vec2) {
    $dotProduct = 0;
    $magnitude1 = 0;
    $magnitude2 = 0;
    
    if (count($vec1) !== count($vec2)) {
        return 0;
    }
    
    foreach ($vec1 as $i => $val1) {
        $dotProduct += $val1 * $vec2[$i];
        $magnitude1 += $val1 * $val1;
        $magnitude2 += $vec2[$i] * $vec2[$i];
    }
    
    $magnitude1 = sqrt($magnitude1);
    $magnitude2 = sqrt($magnitude2);
    
    if ($magnitude1 == 0 || $magnitude2 == 0) {
        return 0;
    }
    
    return $dotProduct / ($magnitude1 * $magnitude2);
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>向量嵌入測試</title>
    <style>
        body {
            font-family: "Microsoft JhengHei", "微軟正黑體", sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .warning {
            color: #dc3545;
            font-weight: bold;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>向量嵌入測試報告</h1>
        
        <h2>1. 文檔資訊</h2>
        <table>
            <tr>
                <th>文檔 ID</th>
                <th>標題</th>
                <th>內容預覽</th>
            </tr>
            <?php foreach ($documents as $doc): ?>
            <tr>
                <td><?php echo htmlspecialchars($doc['doc_id']); ?></td>
                <td><?php echo htmlspecialchars($doc['title']); ?></td>
                <td><?php echo htmlspecialchars($doc['content_preview']); ?>...</td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>2. 向量嵌入資訊</h2>
        <p>總共找到 <?php echo count($embeddings); ?> 個向量嵌入</p>
        
        <h2>3. 測試查詢：「<?php echo htmlspecialchars($testQuery); ?>」</h2>
        
        <?php if (isset($testResult['error'])): ?>
            <p class="warning">錯誤：<?php echo htmlspecialchars($testResult['error']); ?></p>
        <?php else: ?>
            <p>查詢向量長度：<?php echo $testResult['query_embedding_length']; ?></p>
            <p>查詢向量樣本：<?php echo implode(', ', array_map(function($v) { return number_format($v, 6); }, $testResult['query_embedding_sample'])); ?>...</p>
            
            <h3>相似度計算結果：</h3>
            <table>
                <tr>
                    <th>文檔 ID</th>
                    <th>相似度</th>
                    <th>向量長度</th>
                    <th>錯誤訊息</th>
                </tr>
                <?php foreach ($testResult['similarities'] as $similarity): ?>
                <tr>
                    <td><?php echo htmlspecialchars($similarity['doc_id']); ?></td>
                    <td>
                        <?php if (isset($similarity['similarity'])): ?>
                            <?php echo number_format($similarity['similarity'], 6); ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $similarity['doc_embedding_length'] ?? 'N/A'; ?>
                    </td>
                    <td>
                        <?php if (isset($similarity['error'])): ?>
                            <span class="warning"><?php echo htmlspecialchars($similarity['error']); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        
        <h2>4. 檢查向量嵌入匹配</h2>
        <?php
        // 檢查文檔和嵌入的匹配
        $documentIds = array_column($documents, 'doc_id');
        $embeddingIds = array_column($embeddings, 'doc_id');
        
        $missingEmbeddings = array_diff($documentIds, $embeddingIds);
        $extraEmbeddings = array_diff($embeddingIds, $documentIds);
        ?>
        
        <?php if (empty($missingEmbeddings) && empty($extraEmbeddings)): ?>
            <p class="success">所有文檔都有對應的向量嵌入 ✓</p>
        <?php else: ?>
            <?php if (!empty($missingEmbeddings)): ?>
                <p class="warning">以下文檔缺少向量嵌入：</p>
                <ul>
                    <?php foreach ($missingEmbeddings as $docId): ?>
                        <li><?php echo htmlspecialchars($docId); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($extraEmbeddings)): ?>
                <p class="warning">以下向量嵌入沒有對應的文檔：</p>
                <ul>
                    <?php foreach ($extraEmbeddings as $docId): ?>
                        <li><?php echo htmlspecialchars($docId); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
        
        <p><a href="index.php">返回首頁</a></p>
    </div>
</body>
</html>