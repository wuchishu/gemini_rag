<?php
// admin/upload.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Load required files
require_once 'vendor/autoload.php';  // Move this to the top
require_once 'env_loader.php';
require_once 'config.php';
require_once 'gemini_rag_system.php';
require_once 'word_handler.php';
// API key
$apiKey = getenv('GEMINI_API_KEY') ?: $config['gemini']['api_key'] ?? '';

// Initialize RAG system
$ragSystem = new GeminiRAGSystem($apiKey);

$message = '';
$messageClass = '';

// Process text input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_text') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (!empty($title) && !empty($content)) {
        // Generate unique document ID
        $docId = 'doc_' . time() . '_' . mt_rand(1000, 9999);
        
        // Add document to system
        $metadata = [
            'source' => 'manual_input',
            'added_by' => $_SESSION['admin_username'],
            'added_on' => date('Y-m-d H:i:s')
        ];
        
        $success = $ragSystem->addDocument($docId, $title, $content, $metadata);
        
        if ($success) {
            $message = '文檔 "' . htmlspecialchars($title) . '" 已成功添加到知識庫！';
            $messageClass = 'success';
        } else {
            $message = '文檔添加失敗，請重試。';
            $messageClass = 'danger';
        }
    } else {
        $message = '標題和內容不能為空！';
        $messageClass = 'danger';
    }
}

// Process Word document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_word') {
    if (!isset($_FILES['word_file']) || $_FILES['word_file']['error'] !== UPLOAD_ERR_OK) {
        $message = '文件上傳失敗: ' . ($_FILES['word_file']['error'] ?? 'Unknown error');
        $messageClass = 'danger';
    } else {
        $file = $_FILES['word_file'];
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        
        // Check file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExt !== 'doc' && $fileExt !== 'docx') {
            $message = '只支持 .doc 和 .docx 文件格式';
            $messageClass = 'danger';
        } else {
            // Create uploads directory if it doesn't exist
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $newFileName = time() . '_' . $fileName;
            $uploadFilePath = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpPath, $uploadFilePath)) {
                try {
                    // Extract text from Word document
                    $wordHandler = new WordHandler();
                    $extractedContent = $wordHandler->extractText($uploadFilePath);
                    
                    if (empty($extractedContent)) {
                        $message = '無法從文檔中提取文本內容';
                        $messageClass = 'danger';
                    } else {
                        // Generate document ID
                        $docId = 'doc_' . time() . '_' . mt_rand(1000, 9999);
                        
                        // Get title from form or use filename
                        $title = $_POST['title'] ?: pathinfo($fileName, PATHINFO_FILENAME);
                        
                        // Add metadata
                        $metadata = [
                            'source' => 'word_upload',
                            'original_file' => $fileName,
                            'file_path' => $uploadFilePath,
                            'added_by' => $_SESSION['admin_username'],
                            'added_on' => date('Y-m-d H:i:s')
                        ];
                        
                        // Add document to RAG system
                        $success = $ragSystem->addDocument($docId, $title, $extractedContent, $metadata);
                        
                        if ($success) {
                            $message = '文件 "' . htmlspecialchars($fileName) . '" 已成功上傳並添加到知識庫！';
                            $messageClass = 'success';
                        } else {
                            $message = '文件處理成功，但添加到知識庫失敗';
                            $messageClass = 'danger';
                        }
                    }
                } catch (Exception $e) {
                    $message = '處理文件時出錯: ' . $e->getMessage();
                    $messageClass = 'danger';
                }
            } else {
                $message = '移動上傳文件失敗';
                $messageClass = 'danger';
            }
        }
    }
}

function checkWordDependencies() {
    // Check if PhpWord class exists (with namespace)
    return class_exists('PhpOffice\PhpWord\PhpWord') || 
           class_exists('\PhpOffice\PhpWord\PhpWord');
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上傳文檔 | 管理控制台</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar">
            <div class="logo">Gemini RAG</div>
            <ul class="nav-links">
                <li><a href="admin_dashboard.php">控制台</a></li>
                <li><a href="admin_documents.php">知識庫管理</a></li>
                <li class="active"><a href="admin_upload.php">上傳文檔</a></li>
                <li><a href="admin_settings.php">系統設置</a></li>
                <li><a href="admin_logout.php">登出</a></li>
            </ul>
        </nav>
        
        <main class="content">
            <header class="content-header">
                <h1>上傳文檔</h1>
                <div class="user-info">
                    管理員: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageClass; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab active" data-tab="text-input">文本輸入</button>
                <button class="tab" data-tab="word-upload">Word 文件上傳</button>
            </div>
            
            <div class="tab-content active" id="text-input">
                <div class="card">
                    <div class="card-header">
                        <h3>直接輸入文檔</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="upload_text">
                            
                            <div class="form-group">
                                <label for="title">文檔標題</label>
                                <input type="text" id="title" name="title" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="content">文檔內容</label>
                                <textarea id="content" name="content" class="form-control" rows="12" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">添加到知識庫</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="word-upload">
                <div class="card">
                    <div class="card-header">
                        <h3>上傳 Word 文件</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!checkWordDependencies()): ?>
                            <div class="alert alert-warning">
                                <h4>缺少 PhpWord 依賴項</h4>
                                <p>需要安裝 PhpOffice/PhpWord 以處理 Word 文件。請使用 Composer 安裝:</p>
                                <pre>composer require phpoffice/phpword</pre>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_word">
                                
                                <div class="form-group">
                                    <label for="word_file">選擇 Word 文件</label>
                                    <input type="file" id="word_file" name="word_file" class="form-control-file" accept=".doc,.docx" required>
                                    <small class="form-text text-muted">支持的格式: .doc, .docx</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="title_word">文檔標題 (可選)</label>
                                    <input type="text" id="title_word" name="title" class="form-control">
                                    <small class="form-text text-muted">如果不指定，將使用文件名作為標題</small>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">上傳並處理</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to current tab and content
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
    });
    </script>
</body>
</html>