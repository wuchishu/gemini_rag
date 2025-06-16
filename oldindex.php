<?php
/**
 * RAG 系統網頁界面（整合 Word 處理功能）
 * 
 * 此文件提供一個升級版的網頁介面，用於與 Gemini API RAG 系統交互
 * 並支持 Word 文檔的上傳和處理
 */
// 載入環境變數
require_once 'env_loader.php';
// 載入 RAG 系統核心類
require_once 'gemini_rag_system.php';

// 載入 Word 處理功能
require_once 'word_handler.php';

// 載入配置
$config = require_once 'config.php';

// API 密鑰
$apiKey = getenv('GEMINI_API_KEY') ?: $config['gemini']['api_key'] ?? '';

// 初始化 RAG 系統
$ragSystem = new GeminiRAGSystem($apiKey);

// 處理文本直接輸入
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_text') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $metadata = [];
    
    if (!empty($title) && !empty($content)) {
        // 生成唯一文檔 ID
        $docId = 'doc_' . time() . '_' . mt_rand(1000, 9999);
        
        // 添加文檔到系統
        $success = $ragSystem->addDocument($docId, $title, $content, $metadata);
        $uploadMessage = $success ? '文檔添加成功！' : '文檔添加失敗，請重試。';
        $messageClass = $success ? 'success' : 'error';
    } else {
        $uploadMessage = '標題和內容不能為空！';
        $messageClass = 'error';
    }
}

// 處理 Word 文件上傳
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_word') {
    // 檢查是否已安裝必要的依賴項
    if (!checkDependencies()) {
        $uploadMessage = '未安裝必要的依賴項，請先安裝 PhpOffice/PhpWord。';
        $messageClass = 'error';
        $showInstallGuide = true;
    } else {
        // 處理文件上傳
        $uploadResult = handleFileUpload('word_file');
        
        if ($uploadResult['success']) {
            // 添加文檔到 RAG 系統
            $addResult = addWordDocumentToRAG(
                $ragSystem, 
                $uploadResult['content'], 
                $_POST['title'] ?: $uploadResult['title'],
                ['source_file' => $uploadResult['filePath']]
            );
            
            if ($addResult['success']) {
                $uploadMessage = '文件上傳並處理成功！文檔已添加到系統。';
                $messageClass = 'success';
            } else {
                $uploadMessage = '文件處理成功，但添加到系統失敗: ' . $addResult['message'];
                $messageClass = 'error';
            }
        } else {
            $uploadMessage = '文件上傳或處理失敗: ' . $uploadResult['message'];
            $messageClass = 'error';
        }
    }
}

// 處理查詢請求
$answer = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'query') {
    $query = $_POST['query'] ?? '';
    
    if (!empty($query)) {
        $answer = $ragSystem->processQuery($query);
    }
}

// 獲取所有文檔
function getAllDocuments($documentStore) {
    try {
        $stmt = $documentStore->query('SELECT * FROM documents ORDER BY created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// 添加 getDocumentStore 方法到 GeminiRAGSystem 類（如果不存在）
if (!method_exists($ragSystem, 'getDocumentStore')) {
    // 使用反射獲取私有屬性
    class_exists('ReflectionClass') or die('需要 Reflection 擴展');
    $reflector = new ReflectionClass('GeminiRAGSystem');
    $property = $reflector->getProperty('documentStore');
    $property->setAccessible(true);
    $documentStore = $property->getValue($ragSystem);
} else {
    $documentStore = $ragSystem->getDocumentStore();
}

$allDocuments = $documentStore ? getAllDocuments($documentStore) : [];

?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini RAG 系統</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px 20px;
        }
        .col {
            flex: 1;
            min-width: 300px;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="file"], textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 150px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .result {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
            margin-top: 15px;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            border-left: 4px solid #4CAF50;
        }
        .error {
            background-color: #f2dede;
            border-left: 4px solid #d9534f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 15px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
        }
        .tab.active {
            background-color: #fff;
            border-color: #ddd;
            margin-bottom: -1px;
        }
        .tab-content {
            display: none;
            padding: 15px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }
        .tab-content.active {
            display: block;
        }
        .installation-guide {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            border-left: 4px solid #17a2b8;
        }
        .installation-guide pre {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gemini RAG 系統示範</h1>
        
        <?php if (isset($uploadMessage)): ?>
            <div class="message <?php echo $messageClass ?? 'info'; ?>">
                <?php echo htmlspecialchars($uploadMessage); ?>
            </div>
            
            <?php if (isset($showInstallGuide) && $showInstallGuide): ?>
                <?php displayInstallationGuide(); ?>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- 文檔添加區域 -->
            <div class="col">
                <h2>添加新文檔</h2>
                
                <div class="tabs">
                    <div class="tab active" data-tab="text-input">文本輸入</div>
                    <div class="tab" data-tab="file-upload">Word 文件上傳</div>
                </div>
                
                <!-- 文本輸入表單 -->
                <div class="tab-content active" id="text-input">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="upload_text">
                        <div class="form-group">
                            <label for="title">文檔標題</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="content">文檔內容</label>
                            <textarea id="content" name="content" required></textarea>
                        </div>
                        <button type="submit">添加文檔</button>
                    </form>
                </div>
                
                <!-- Word 文件上傳表單 -->
                <div class="tab-content" id="file-upload">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_word">
                        <div class="form-group">
                            <label for="word_file">選擇 Word 文件</label>
                            <input type="file" id="word_file" name="word_file" accept=".doc,.docx" required>
                        </div>
                        <div class="form-group">
                            <label for="title_word">文檔標題（可選，默認使用文件名）</label>
                            <input type="text" id="title_word" name="title">
                        </div>
                        <button type="submit">上傳並處理</button>
                    </form>
                </div>
            </div>
            
            <!-- 查詢區域 -->
            <div class="col">
                <h2>查詢知識庫</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="query">
                    <div class="form-group">
                        <label for="query">您的問題</label>
                        <input type="text" id="query" name="query" required>
                    </div>
                    <button type="submit">提交問題</button>
                </form>
                
                <?php if (!empty($answer)): ?>
                    <h3>回答：</h3>
                    <div class="result">
                        <?php echo nl2br(htmlspecialchars($answer)); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 已添加的文檔列表 -->
        <h2>已添加的文檔</h2>
        <?php if (!empty($allDocuments)): ?>
            <table>
                <thead>
                    <tr>
                        <th>文檔 ID</th>
                        <th>標題</th>
                        <th>添加時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allDocuments as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['doc_id']); ?></td>
                            <td><?php echo htmlspecialchars($doc['title']); ?></td>
                            <td><?php echo htmlspecialchars($doc['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>暫無文檔。</p>
        <?php endif; ?>
    </div>
    
    <script>
        // 標籤切換功能
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // 移除所有活動狀態
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // 添加活動狀態到當前標籤
                    this.classList.add('active');
                    
                    // 顯示相關內容
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>