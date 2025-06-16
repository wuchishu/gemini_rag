<?php
// admin/settings.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Load required files
require_once 'env_loader.php';
require_once 'config.php';

$message = '';
$messageClass = '';

// Get current settings
$currentSettings = [
    'api_key' => getenv('GEMINI_API_KEY') ?: $config['gemini']['api_key'] ?? '',
    'admin_username' => getenv('ADMIN_USERNAME') ?: $config['admin']['username'] ?? 'admin',
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $apiKey = trim($_POST['api_key'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    if (empty($apiKey)) {
        $message = 'API 密鑰不能為空';
        $messageClass = 'danger';
    } elseif (empty($adminUsername)) {
        $message = '管理員用戶名不能為空';
        $messageClass = 'danger';
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $message = '新密碼和確認密碼不匹配';
        $messageClass = 'danger';
    } else {
        // Update config.php
        $updatedConfig = $config;
        $updatedConfig['gemini']['api_key'] = $apiKey;
        $updatedConfig['admin']['username'] = $adminUsername;
        
        if (!empty($newPassword)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatedConfig['admin']['password_hash'] = $passwordHash;
        }
        
        // Write updated config to file
        $configContent = "<?php\nreturn " . var_export($updatedConfig, true) . ";\n";
        
        if (file_put_contents('../config.php', $configContent)) {
            $message = '設置已成功更新';
            $messageClass = 'success';
            $currentSettings['api_key'] = $apiKey;
            $currentSettings['admin_username'] = $adminUsername;
        } else {
            $message = '無法寫入配置文件，請檢查文件權限';
            $messageClass = 'danger';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系統設置 | 管理控制台</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <nav class="sidebar">
            <div class="logo">Gemini RAG</div>
            <ul class="nav-links">
                <li><a href="index.php">控制台</a></li>
                <li><a href="documents.php">知識庫管理</a></li>
                <li><a href="upload.php">上傳文檔</a></li>
                <li class="active"><a href="settings.php">系統設置</a></li>
                <li><a href="logout.php">登出</a></li>
            </ul>
        </nav>
        
        <main class="content">
            <header class="content-header">
                <h1>系統設置</h1>
                <div class="user-info">
                    管理員: <?php echo htmlspecialchars($_SESSION["admin_username"] ?? "Admin"); ?>
                </div>
            </header>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageClass; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>基本設置</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group">
                            <label for="api_key">Gemini API 密鑰</label>
                            <input type="text" id="api_key" name="api_key" class="form-control" value="<?php echo htmlspecialchars($currentSettings['api_key']); ?>" required>
                            <small class="form-text text-muted">Gemini API 密鑰用於與 Google 的 Gemini AI 服務通信。</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_username">管理員用戶名</label>
                            <input type="text" id="admin_username" name="admin_username" class="form-control" value="<?php echo htmlspecialchars($currentSettings['admin_username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">新密碼</label>
                            <input type="password" id="new_password" name="new_password" class="form-control">
                            <small class="form-text text-muted">如果不修改密碼，請留空。</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">確認新密碼</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">保存設置</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3>系統信息</h3>
                </div>
                <div class="card-body">
                    <div class="system-info">
                        <p><strong>PHP 版本:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>SQLite 版本:</strong> <?php echo SQLite3::version()['versionString']; ?></p>
                        <p><strong>文檔存儲:</strong> <?php echo realpath(__DIR__ . '/../document_store.sqlite'); ?></p>
                        <p><strong>向量資料庫:</strong> <?php echo realpath(__DIR__ . '/../vector_db.sqlite'); ?></p>
                        <p><strong>上傳目錄:</strong> <?php echo realpath(__DIR__ . '/../uploads'); ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>