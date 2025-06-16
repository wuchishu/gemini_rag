<?php
// admin/upload.php
session_start();
require_once "../config.php";
require_once "../word_handler.php";
require_once "../gemini_rag_system.php";

// Check if admin is logged in
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['doc_title']) && isset($_POST['doc_content'])) {
        // Handle text input
        $title = $_POST['doc_title'];
        $content = $_POST['doc_content'];
        
        try {
            $rag = new GeminiRAGSystem();
            $rag->addDocument($title, $content);
            $message = "文檔已成功添加到知識庫";
        } catch (Exception $e) {
            $error = "錯誤: " . $e->getMessage();
        }
    } elseif (isset($_FILES['word_file']) && $_FILES['word_file']['error'] == 0) {
        // Handle file upload
        $file = $_FILES['word_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        
        // Check file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['doc', 'docx'];
        
        if (in_array($fileExt, $allowedExts)) {
            if ($fileSize < 5000000) { // 5MB limit
                $uploadDir = "../uploads/";
                $uploadPath = $uploadDir . $fileName;
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    try {
                        $wordHandler = new WordHandler();
                        $extractedContent = $wordHandler->extractText($uploadPath);
                        
                        $rag = new GeminiRAGSystem();
                        $rag->addDocument(pathinfo($fileName, PATHINFO_FILENAME), $extractedContent);
                        
                        $message = "Word文件已成功上傳並添加到知識庫";
                    } catch (Exception $e) {
                        $error = "處理文件時出錯: " . $e->getMessage();
                    }
                } else {
                    $error = "上傳文件失敗";
                }
            } else {
                $error = "文件太大 (最大 5MB)";
            }
        } else {
            $error = "不支持的文件格式 (僅支持 .doc 和 .docx)";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上傳文檔 | Gemini RAG 系統</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar">
            <div class="logo">Gemini RAG</div>
            <ul class="nav-links">
                <li><a href="index.php">控制台</a></li>
                <li><a href="documents.php">知識庫文檔</a></li>
                <li class="active"><a href="upload.php">上傳文檔</a></li>
                <li><a href="queries.php">查詢記錄</a></li>
                <li><a href="settings.php">系統設置</a></li>
                <li><a href="logout.php">登出</a></li>
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
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-btn active" data-tab="text-input">文本輸入</button>
                <button class="tab-btn" data-tab="file-upload">Word文件上傳</button>
            </div>
            
            <div class="tab-content active" id="text-input">
                <div class="card">
                    <div class="card-header">
                        <h3>直接輸入文檔內容</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="doc_title">文檔標題</label>
                                <input type="text" id="doc_title" name="doc_title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="doc_content">文檔內容</label>
                                <textarea id="doc_content" name="doc_content" rows="15" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">添加到知識庫</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="file-upload">
                <div class="card">
                    <div class="card-header">
                        <h3>上傳Word文件</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="word_file">選擇Word文件</label>
                                <input type="file" id="word_file" name="word_file" accept=".doc,.docx" required>
                                <small>支持的格式: .doc, .docx (最大 5MB)</small>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">上傳文件</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Deactivate all tabs
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Activate selected tab
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
    });
    </script>
</body>
</html>