<?php
// admin_dashboard.php
session_start();
require_once "config.php";

// Check if admin is logged in
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Get document count
$db = new SQLite3("document_store.sqlite");
$result = $db->query("SELECT COUNT(*) as doc_count FROM documents");
$row = $result->fetchArray(SQLITE3_ASSOC);
$doc_count = $row['doc_count'] ?? 0;
$db->close();

// Get recent queries
$db = new SQLite3("vector_db.sqlite");
$result = $db->query("SELECT query_text, timestamp FROM query_history ORDER BY timestamp DESC LIMIT 5");
$recent_queries = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recent_queries[] = $row;
}
$db->close();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理控制台 | Gemini RAG 系統</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar">
            <div class="logo">Gemini RAG</div>
            <ul class="nav-links">
                <li class="active"><a href="admin_dashboard.php">控制台</a></li>
                <li><a href="admin_documents.php">知識庫文檔</a></li>
                <li><a href="admin_upload.php">上傳文檔</a></li>
                <li><a href="admin_settings.php">系統設置</a></li>
                <li><a href="admin_logout.php">登出</a></li>
            </ul>
        </nav>
        
        <main class="content">
            <header class="content-header">
                <h1>管理控制台</h1>
                <div class="user-info">
                    管理員: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?>
                </div>
            </header>
            
            <div class="dashboard">
                <div class="stats-cards">
                    <div class="card">
                        <div class="card-body">
                            <h3>知識庫文檔</h3>
                            <div class="stat"><?php echo $doc_count; ?></div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h3>今日查詢</h3>
                            <div class="stat">--</div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h3>系統狀態</h3>
                            <div class="stat">在線</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="card">
                        <div class="card-header">
                            <h3>最近查詢</h3>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>查詢內容</th>
                                        <th>時間</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_queries as $query): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($query['query_text']); ?></td>
                                        <td><?php echo htmlspecialchars($query['timestamp']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_queries)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center">暫無查詢記錄</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>