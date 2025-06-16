<?php
// admin/documents.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Load required files
require_once 'env_loader.php';
require_once 'config.php';
require_once 'gemini_rag_system.php';

// API key
$apiKey = getenv('GEMINI_API_KEY') ?: $config['gemini']['api_key'] ?? '';

// Initialize RAG system
$ragSystem = new GeminiRAGSystem($apiKey);

// Get document store
if (!method_exists($ragSystem, 'getDocumentStore')) {
    // Use reflection to access private property if method doesn't exist
    class_exists('ReflectionClass') or die('需要 Reflection 擴展');
    $reflector = new ReflectionClass('GeminiRAGSystem');
    $property = $reflector->getProperty('documentStore');
    $property->setAccessible(true);
    $documentStore = $property->getValue($ragSystem);
} else {
    $documentStore = $ragSystem->getDocumentStore();
}

$message = '';
$messageClass = '';

// Handle document deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['doc_id'])) {
    $docId = $_POST['doc_id'];
    
    try {
        // Delete document from document store
        $stmt = $documentStore->prepare('DELETE FROM documents WHERE doc_id = ?');
        $stmt->execute([$docId]);
        
        // Delete embeddings from vector database
        $vectorDb = new PDO('sqlite:' . __DIR__ . '/../vector_db.sqlite');
        $vectorDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $vectorDb->prepare('DELETE FROM embeddings WHERE doc_id = ?');
        $stmt->execute([$docId]);
        
        $message = '文檔已成功刪除';
        $messageClass = 'success';
    } catch (Exception $e) {
        $message = '刪除文檔時出錯: ' . $e->getMessage();
        $messageClass = 'danger';
    }
}

// View document details if ID provided in GET
$viewDocId = $_GET['view'] ?? null;
$documentDetails = null;

if ($viewDocId) {
    try {
        $stmt = $documentStore->prepare('SELECT * FROM documents WHERE doc_id = ?');
        $stmt->execute([$viewDocId]);
        $documentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $message = '獲取文檔詳情時出錯: ' . $e->getMessage();
        $messageClass = 'danger';
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total document count
$totalDocs = 0;
try {
    $result = $documentStore->query('SELECT COUNT(*) as count FROM documents');
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $totalDocs = $row['count'] ?? 0;
} catch (Exception $e) {
    $message = '獲取文檔數量時出錯: ' . $e->getMessage();
    $messageClass = 'danger';
}

$totalPages = ceil($totalDocs / $limit);

// Get documents with pagination
$documents = [];
try {
    $stmt = $documentStore->prepare('SELECT * FROM documents ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = '獲取文檔列表時出錯: ' . $e->getMessage();
    $messageClass = 'danger';
}

// Function to truncate long text
function truncateText($text, $length = 100) {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>知識庫文檔 | 管理控制台</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar">
            <div class="logo">Gemini RAG</div>
            <ul class="nav-links">
                <li><a href="index.php">控制台</a></li>
                <li class="active"><a href="documents.php">知識庫管理</a></li>
                <li><a href="upload.php">上傳文檔</a></li>
                <li><a href="settings.php">系統設置</a></li>
                <li><a href="logout.php">登出</a></li>
            </ul>
        </nav>
        
        <main class="content">
            <header class="content-header">
                <h1>知識庫文檔管理</h1>
                <div class="user-info">
                    管理員: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageClass; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($documentDetails): ?>
                <!-- Document Details View -->
                <div class="card">
                    <div class="card-header">
                        <h3>文檔詳情</h3>
                        <div class="card-actions">
                            <a href="documents.php" class="btn btn-secondary btn-sm">返回列表</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="document-details">
                            <div class="detail-item">
                                <strong>文檔 ID:</strong>
                                <span><?php echo htmlspecialchars($documentDetails['doc_id']); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>標題:</strong>
                                <span><?php echo htmlspecialchars($documentDetails['title']); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>添加時間:</strong>
                                <span><?php echo htmlspecialchars($documentDetails['created_at']); ?></span>
                            </div>
                            <?php if (!empty($documentDetails['metadata'])): ?>
                                <div class="detail-item">
                                    <strong>元數據:</strong>
                                    <pre><?php echo htmlspecialchars($documentDetails['metadata']); ?></pre>
                                </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <strong>內容:</strong>
                                <div class="document-content">
                                    <?php echo nl2br(htmlspecialchars($documentDetails['content'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="document-actions">
                            <form method="post" action="" onsubmit="return confirm('確定要刪除這個文檔嗎？此操作不可撤銷。');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($documentDetails['doc_id']); ?>">
                                <button type="submit" class="btn btn-danger">刪除文檔</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Documents List View -->
                <div class="card">
                    <div class="card-header">
                        <h3>文檔列表</h3>
                        <div class="card-actions">
                            <a href="admin_upload.php" class="btn btn-primary btn-sm">添加新文檔</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="empty-state">
                                <p>知識庫中暫無文檔。點擊 "添加新文檔" 來開始建立知識庫。</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>標題</th>
                                            <th>內容預覽</th>
                                            <th>添加時間</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $document): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($document['title']); ?></td>
                                            <td><?php echo htmlspecialchars(truncateText($document['content'])); ?></td>
                                            <td><?php echo htmlspecialchars($document['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?view=<?php echo urlencode($document['doc_id']); ?>" class="btn btn-info btn-sm">查看</a>
                                                    <form method="post" action="" onsubmit="return confirm('確定要刪除這個文檔嗎？此操作不可撤銷。');" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="doc_id" value="<?php echo htmlspecialchars($document['doc_id']); ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">刪除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="page-link">&laquo; 上一頁</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i === $page): ?>
                                        <span class="page-link active"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?php echo $i; ?>" class="page-link"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="page-link">下一頁 &raquo;</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>