<?php
// admin_login.php
session_start();

// Load environment variables and configuration
require_once 'env_loader.php';
$config = require_once 'config.php';

// Check if admin credentials are set
$admin_username = getenv('ADMIN_USERNAME') ?: ($config['admin']['username'] ?? 'admin');
$admin_password_hash = getenv('ADMIN_PASSWORD_HASH') ?: ($config['admin']['password_hash'] ?? password_hash('admin', PASSWORD_DEFAULT));

$error = '';

// Process login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Simple authentication
    if ($username === $admin_username && password_verify($password, $admin_password_hash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        
        // Redirect to admin dashboard
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = '帳號或密碼錯誤';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理員登入 | admission RAG 系統</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h1>Gemini RAG 系統</h1>
            <h2>管理員登入</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="username">帳號</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密碼</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">登入</button>
                </div>
            </form>
            
            <div class="back-link">
                <a href="index.php">返回查詢系統</a>
            </div>
        </div>
    </div>
</body>
</html>