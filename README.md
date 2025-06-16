# Gemini RAG 系統設置和使用說明

這個文檔提供了如何設置和使用基於 Gemini API 的 RAG 系統的詳細說明，包括如何處理 Word 文件。

## 檔案結構

系統包含以下檔案：

1. `gemini_rag_system.php` - 核心 RAG 系統類
2. `word_handler.php` - Word 文件處理功能
3. `index.php` - 網頁界面
4. `config.php` - 配置文件
5. `composer.json` - Composer 依賴配置

## 安裝步驟

### 1. 準備環境

確保您的系統符合以下要求：
- PHP 7.4 或更高版本
- 啟用 PDO、SQLite、cURL 和 JSON 擴展
- Composer（用於安裝依賴）

### 2. 創建目錄結構

```
gemini-rag-system/
├── gemini_rag_system.php
├── word_handler.php
├── index.php
├── config.php
├── composer.json
└── data/         (將自動創建)
└── uploads/      (將自動創建)
```

### 3. 安裝依賴

在項目根目錄運行：

```bash
composer install
```

這將安裝處理 Word 文件所需的 PhpOffice/PhpWord 庫。

### 4. 設置 Gemini API 密鑰

您需要一個 Gemini API 密鑰才能使用系統。有兩種方式設置 API 密鑰：

#### 方法 1: 環境變數

設置環境變數 `GEMINI_API_KEY`：

```bash
export GEMINI_API_KEY=your_api_key_here
```

#### 方法 2: 在配置文件中設置

編輯 `config.php` 文件，找到以下部分並填入您的 API 密鑰：

```php
'gemini' => [
    'api_key' => 'your_api_key_here', // 填入您的 API 密鑰
    ...
]
```

### 5. 設置目錄權限

確保 `data` 和 `uploads` 目錄可寫：

```bash
mkdir -p data uploads
chmod 755 data uploads
```

### 6. 啟動 Web 服務器

對於開發環境，可以使用 PHP 內建服務器：

```bash
php -S localhost:8000
```

然後在瀏覽器中訪問 `http://localhost:8000`。

## 使用說明

### 添加文檔到知識庫

有兩種方式添加文檔：

#### 1. 直接輸入文本

1. 在"文本輸入"標籤中，填寫文檔標題和內容
2. 點擊"添加文檔"按鈕

#### 2. 上傳 Word 文件

1. 切換到"Word 文件上傳"標籤
2. 選擇 Word 文件（.doc 或 .docx 格式）
3. 可選填寫文檔標題（如果留空，將使用文件名）
4. 點擊"上傳並處理"按鈕
5. 系統會自動提取文件內容並添加到知識庫

### 查詢知識庫

1. 在"您的問題"輸入框中輸入查詢內容
2. 點擊"提交問題"按鈕
3. 系統將檢索相關文檔並使用 Gemini API 生成回答

### 查看已添加的文檔

在頁面底部的表格中可以看到所有已添加的文檔，包括：
- 文檔 ID
- 標題
- 添加時間

## 故障排除

### Word 文件處理問題

如果遇到 Word 文件處理問題，請確保：

1. PhpOffice/PhpWord 庫已正確安裝
2. 上傳的文件是有效的 .doc 或 .docx 格式
3. PHP 有足夠的內存處理文件（大文件可能需要調整 `php.ini` 中的 `memory_limit`）

### API 連接問題

如果遇到 API 連接問題，請檢查：

1. API 密鑰是否正確設置
2. 網絡連接是否正常
3. Gemini API 服務是否可用

## 獲取 Gemini API 密鑰

要獲取 Gemini API 密鑰，請訪問 [Google AI Studio](https://ai.google.dev/) 並註冊一個帳戶。

## 系統限制

- 目前使用 SQLite 作為向量和文檔存儲，適用於小型應用或原型開發
- 對於大型應用，建議使用專用向量數據庫（如 Pinecone、Milvus 或 PostgreSQL 搭配 pgvector）