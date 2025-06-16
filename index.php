<?php
/**
 * 僑光科大招生查詢系統 (Gemini RAG-based)
 * 
 * 此文件提供用戶查詢界面，專注於招生相關查詢。
 * 文檔管理功能已移至管理員界面。
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

// 處理查詢請求
$answer = '';
$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    
    if (!empty($query)) {
        // 記錄查詢
        recordQuery($query);
        
        // 處理查詢
        $answer = $ragSystem->processQuery($query);
    }
}

/**
 * 記錄查詢到資料庫
 * 
 * @param string $queryText 查詢文本
 */
function recordQuery($queryText) {
    try {
        $db = new SQLite3('vector_db.sqlite');
        
        // 確保查詢歷史表存在
        $db->exec('CREATE TABLE IF NOT EXISTS query_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            query_text TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address TEXT
        )');
        
        // 插入查詢記錄
        $stmt = $db->prepare('INSERT INTO query_history (query_text, ip_address) VALUES (:query, :ip)');
        $stmt->bindValue(':query', $queryText, SQLITE3_TEXT);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
        $stmt->execute();
        
        $db->close();
    } catch (Exception $e) {
        // 靜默處理錯誤
    }
}

/**
 * 獲取最近查詢記錄
 * 
 * @param int $limit 限制數量
 * @return array 查詢記錄
 */
function getRecentQueries($limit = 5) {
    $queries = [];
    try {
        $db = new SQLite3('vector_db.sqlite');
        
        // 檢查表是否存在
        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='query_history'");
        
        if ($tableExists) {
            // 獲取當前 IP 的最近查詢
            $stmt = $db->prepare('SELECT query_text, timestamp FROM query_history 
                                  WHERE ip_address = :ip 
                                  ORDER BY timestamp DESC LIMIT :limit');
            $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $queries[] = $row;
            }
        }
        
        $db->close();
    } catch (Exception $e) {
        // 靜默處理錯誤
    }
    
    return $queries;
}

// 獲取最近查詢記錄
$recentQueries = getRecentQueries(5);

// 獲取常見問題建議
$suggestedQueries = [
    '有那些入學管道？',
    '僑光有那些系',
    '入學管道',
    '甄選入學的方法'
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>僑光科大招生查詢系統 - Gemini RAG</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <h1>僑光科技大學 - 招生查詢系統</h1>
            </div>
            <div class="admin-link">
                <a href="admin_login.php">管理入口</a>
            </div>
        </header>
        
        <main>
            <section class="query-section">
                <div class="query-card">
                    <h2>查詢僑光科大招生資訊</h2>
                    <p class="description">
                        請輸入您想了解的招生相關問題，例如：「哪些科系有招收僑生？」、「商管學院有哪些系？」或「入學申請時間是什麼時候？」
                    </p>
                    
                    <form method="post" action="">
                        <div class="search-container">
                            <input type="text" id="query" name="query" placeholder="輸入您的問題..." value="<?php echo htmlspecialchars($query); ?>" required>
                            <button type="submit">查詢</button>
                        </div>
                    </form>
                    
                    <div class="suggestions">
                        <h3>熱門問題</h3>
                        <div class="suggestion-tags">
                            <?php foreach ($suggestedQueries as $suggestedQuery): ?>
                            <button class="suggestion-tag" onclick="fillQuery('<?php echo htmlspecialchars(addslashes($suggestedQuery)); ?>')"><?php echo htmlspecialchars($suggestedQuery); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($answer)): ?>
                <div class="result-card">
                    <h3>查詢結果</h3>
                    <div class="result-content">
                        <?php echo nl2br(htmlspecialchars($answer)); ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            
            <aside class="history-section">
                <div class="history-card">
                    <h3>最近查詢記錄</h3>
                    <?php if (!empty($recentQueries)): ?>
                    <ul class="history-list">
                        <?php foreach ($recentQueries as $recentQuery): ?>
                        <li>
                            <div class="query-text" onclick="fillQuery('<?php echo htmlspecialchars(addslashes($recentQuery['query_text'])); ?>')">
                                <?php echo htmlspecialchars($recentQuery['query_text']); ?>
                            </div>
                            <div class="query-time">
                                <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($recentQuery['timestamp']))); ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="no-history">您尚未進行任何查詢</p>
                    <?php endif; ?>
                </div>
            </aside>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> 僑光科技大學 - 智能招生查詢系統</p>
        </footer>
    </div>
    
    <script>
    function fillQuery(text) {
        document.getElementById('query').value = text;
    }
    </script>
</body>
</html>