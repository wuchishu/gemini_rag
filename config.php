<?php
/**
 * RAG 系統配置文件
 * 
 * 此文件包含系統所需的配置參數
 */

return [
    // Gemini API 配置
    'gemini' => [
        'api_key' => getenv('AIzaSyDFGB6VvWpOM4Pv7mnqT-zYNqLsYle-81w') ?: '',
        'embedding_model' => 'models/embedding-001',
        'generation_model' => 'models/gemini-1.5-pro',
        'temperature' => 0.2,  // 生成溫度，較低值產生更確定性的輸出
        'max_output_tokens' => 1024,  // 最大輸出令牌數
    ],
    
    // 資料庫配置
    'database' => [
        'vector_db_path' => __DIR__ . '/data/vector_db.sqlite',
        'document_db_path' => __DIR__ . '/data/document_store.sqlite',
    ],
    
    // 索引配置
    'indexing' => [
        'chunk_size' => 1000,  // 文檔分塊大小（字符數）
        'chunk_overlap' => 200,  // 塊重疊大小
    ],
    
    // 檢索配置
    'retrieval' => [
        'top_k' => 3,  // 檢索的文檔數量
        'similarity_threshold' => 0.7,  // 相似度閾值，低於此值的文檔將被過濾
    ],
];
?>

<!-- 安裝說明 -->
<!--
## 安裝與設置指南

### 系統要求

- PHP 7.4 或更高版本
- SQLite 擴展
- cURL 擴展
- JSON 擴展

### 安裝步驟

1. **設置項目結構**

   創建以下目錄結構：
   ```
   gemini-rag-system/
   ├── src/
   │   ├── gemini_rag_system.php  (核心系統類)
   │   └── config.php             (配置文件)
   ├── public/
   │   └── index.php              (網頁介面)
   ├── data/                      (資料庫目錄)
   └── composer.json
   ```

2. **安裝依賴**

   可以使用 Composer 管理依賴：
   ```json
   {
     "require": {
       "php": ">=7.4",
       "ext-curl": "*",
       "ext-json": "*",
       "ext-pdo": "*",
       "ext-sqlite3": "*"
     }
   }
   ```

   運行：
   ```
   composer install
   ```

3. **設置 API 密鑰**

   創建 `.env` 文件並添加 Gemini API 密鑰：
   ```
   GEMINI_API_KEY=your_api_key_here
   ```
   
   要獲取 Gemini API 密鑰，請訪問 Google AI Studio (https://ai.google.dev/) 並註冊一個帳戶。

4. **設置資料目錄權限**

   確保 `data` 目錄可寫：
   ```bash
   chmod 755 data
   ```

5. **運行系統**

   對於開發環境，可以使用 PHP 內建服務器：
   ```bash
   php -S localhost:8000 -t public
   ```

   然後在瀏覽器中訪問 `http://localhost:8000`。

### 關於向量存儲

本示例使用 SQLite 存儲向量嵌入，這適用於小型應用和原型開發。對於生產環境，建議使用專用的向量資料庫，例如：

- Pinecone
- Milvus
- Chroma
- PostgreSQL 搭配 pgvector 擴展

這些解決方案提供更高效的向量搜索和擴展能力。
-->