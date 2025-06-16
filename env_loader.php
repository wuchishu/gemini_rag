<?php
/**
 * 環境變數加載器
 * 
 * 此文件用於加載 .env 文件中的環境變數
 */

/**
 * 加載 .env 文件中的環境變數
 */
function loadEnv() {
    $envFile = __DIR__ . '/.env';
    
    if (!file_exists($envFile)) {
        error_log(".env 文件不存在：{$envFile}");
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // 跳過注釋行
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // 解析 KEY=VALUE 格式
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // 移除引號（如果有）
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            // 設置環境變數
            if (!empty($name)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    
    return true;
}

// 自動加載環境變數
loadEnv();