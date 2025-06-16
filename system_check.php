<?php
/**
 * 系統檢查程式
 * 用於診斷 Gemini RAG 系統的問題
 */

// 載入環境變數
require_once 'env_loader.php';
// 載入 RAG 系統核心類
require_once 'gemini_rag_system.php';
// 載入配置
$config = require_once 'config.php';

// 檢查項目結果
$checks = [];

// 1. 檢查 API 密鑰
$apiKey = getenv('GEMINI_API_KEY') ?: $config['gemini']['api_key'] ?? '';
$checks['api_key'] = !empty($apiKey) ? '✓ API 密鑰已設定' : '✗ API 密鑰未設定';

// 2. 檢查資料庫檔案
$checks['vector_db'] = file_exists('vector_db.sqlite') ? '✓ 向量資料庫存在' : '✗ 向量資料庫不存在';
$checks['document_store'] = file_exists('document_store.sqlite') ? '✓ 文檔資料庫存在' : '✗ 文檔資料庫不存在';

// 3. 檢查資料庫內容
try {
    $vectorDb = new SQLite3('vector_db.sqlite');
    $embeddingCount = $vectorDb->querySingle('SELECT COUNT(*) FROM embeddings');
    $checks['embeddings'] = $embeddingCount > 0 ? "✓ 找到 {$embeddingCount} 個向量嵌入" : '✗ 沒有向量嵌入資料';
    $vectorDb->close();
} catch (Exception $e) {
    $checks['embeddings'] = '✗ 無法讀取向量資料庫';
}

try {
    $documentStore = new SQLite3('document_store.sqlite');
    $documentCount = $documentStore->querySingle('SELECT COUNT(*) FROM documents');
    $checks['documents'] = $documentCount > 0 ? "✓ 找到 {$documentCount} 個文檔" : '✗ 沒有文檔資料';
    $documentStore->close();
} catch (Exception $e) {
    $checks['documents'] = '✗ 無法讀取文檔資料庫';
}

// 4. 檢查必要的 PHP 擴展
$checks['pdo'] = extension_loaded('pdo') ? '✓ PDO 擴展已載入' : '✗ PDO 擴展未載入';
$checks['sqlite3'] = extension_loaded('sqlite3') ? '✓ SQLite3 擴展已載入' : '✗ SQLite3 擴展未載入';
$checks['curl'] = extension_loaded('curl') ? '✓ CURL 擴展已載入' : '✗ CURL 擴展未載入';
$checks['mbstring'] = extension_loaded('mbstring') ? '✓ mbstring 擴展已載入' : '✗ mbstring 擴展未載入';

// 5. 測試 Gemini API 連接
if (!empty($apiKey)) {
    try {
        $ragSystem = new GeminiRAGSystem($apiKey);
        $testEmbedding = $ragSystem->getEmbedding('測試文本');
        $checks['api_connection'] = '✓ Gemini API 連接正常';
    } catch (Exception $e) {
        $checks['api_connection'] = '✗ Gemini API 連接失敗: ' . $e->getMessage();
    }
} else {
    $checks['api_connection'] = '✗ 無法測試 API 連接（缺少 API 密鑰）';
}

// 6. 檢查文件權限
$checks['vector_db_writable'] = is_writable('vector_db.sqlite') ? '✓ 向量資料庫可寫入' : '✗ 向量資料庫無法寫入';
$checks['document_store_writable'] = is_writable('document_store.sqlite') ? '✓ 文檔資料庫可寫入' : '✗ 文檔資料庫無法寫入';
$checks['current_dir_writable'] = is_writable('.') ? '✓ 當前目錄可寫入' : '✗ 當前目錄無法寫入';

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系統檢查 - 僑光科大招生查詢系統</title>
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
        .check-item {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .check-item.success {
            background-color: #d4edda;
            color: #155724;
        }
        .check-item.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .solution {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
        .solution h2 {
            margin-top: 0;
            color: #495057;
        }
        .solution ul {
            margin-bottom: 0;
        }
        .solution li {
            margin: 10px 0;
        }
        .code {
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>系統檢查報告</h1>
        
        <?php foreach ($checks as $check => $result): ?>
            <div class="check-item <?php echo strpos($result, '✓') === 0 ? 'success' : 'error'; ?>">
                <?php echo $result; ?>
            </div>
        <?php endforeach; ?>
        
        <div class="solution">
            <h2>解決方案</h2>
            
            <?php if (strpos($checks['api_key'], '✗') === 0): ?>
                <h3>1. 設定 API 密鑰</h3>
                <ul>
                    <li>在 <span class="code">.env</span> 文件中設定：<br>
                        <span class="code">GEMINI_API_KEY=your_api_key_here</span>
                    </li>
                    <li>或在 <span class="code">config.php</span> 中設定：<br>
                        <span class="code">'api_key' => 'your_api_key_here'</span>
                    </li>
                </ul>
            <?php endif; ?>
            
            <?php if (strpos($checks['documents'], '✗') === 0): ?>
                <h3>2. 載入招生文檔</h3>
                <ul>
                    <li>使用管理介面上傳招生文檔</li>
                    <li>或使用以下命令行載入：<br>
                        <span class="code">php admin_upload_documents.php</span>
                    </li>
                </ul>
            <?php endif; ?>
            
            <?php if (strpos($checks['vector_db_writable'], '✗') === 0 || strpos($checks['document_store_writable'], '✗') === 0): ?>
                <h3>3. 修正文件權限</h3>
                <ul>
                    <li>Linux/Mac：<br>
                        <span class="code">chmod 666 vector_db.sqlite document_store.sqlite</span>
                    </li>
                    <li>Windows：確保目錄有寫入權限</li>
                </ul>
            <?php endif; ?>
            
            <?php
            $missingExtensions = [];
            if (strpos($checks['pdo'], '✗') === 0) $missingExtensions[] = 'pdo';
            if (strpos($checks['sqlite3'], '✗') === 0) $missingExtensions[] = 'sqlite3';
            if (strpos($checks['curl'], '✗') === 0) $missingExtensions[] = 'curl';
            if (strpos($checks['mbstring'], '✗') === 0) $missingExtensions[] = 'mbstring';
            
            if (!empty($missingExtensions)):
            ?>
                <h3>4. 安裝缺少的 PHP 擴展</h3>
                <ul>
                    <li>Linux：<br>
                        <span class="code">sudo apt-get install php-<?php echo implode(' php-', $missingExtensions); ?></span>
                    </li>
                    <li>Windows：在 php.ini 中啟用：<br>
                        <?php foreach ($missingExtensions as $ext): ?>
                            <span class="code">extension=<?php echo $ext; ?></span><br>
                        <?php endforeach; ?>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php">返回首頁</a>
        </p>
    </div>
</body>
</html>