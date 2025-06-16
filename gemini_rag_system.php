<?php
/**
 * Gemini API RAG 系統
 * 
 * 此系統實現了一個基本的 Retrieval-Augmented Generation (RAG) 系統，
 * 使用 PHP 來與 Gemini API 集成，實現文檔檢索和生成功能。
 */

if (!class_exists('GeminiRAGSystem')) {
    class GeminiRAGSystem {
        private $apiKey;
        private $embeddingModel;
        private $generationModel;
        private $vectorDb; // 向量資料庫連接器
        private $documentStore; // 文檔存儲

        /**
         * 構造函數
         * 
         * @param string $apiKey Gemini API 密鑰
         * @param string $embeddingModel 使用的嵌入模型名稱
         * @param string $generationModel 使用的生成模型名稱
         */
        public function __construct(string $apiKey, string $embeddingModel = 'models/embedding-001', string $generationModel = 'models/gemini-1.5-pro') {
            $this->apiKey = $apiKey;
            $this->embeddingModel = $embeddingModel;
            $this->generationModel = $generationModel;
            
            // 初始化向量資料庫連接器 (範例使用 SQLite，生產環境建議使用專用向量資料庫)
            $this->initVectorDb();
            
            // 初始化文檔存儲
            $this->initDocumentStore();
        }

        /**
         * 初始化向量資料庫
         */
        private function initVectorDb() {
            // 這裡使用 SQLite 作為簡單示範
            // 實際應用中，建議使用專用向量資料庫，如 Pinecone、Milvus、pgvector 等
            $this->vectorDb = new PDO('sqlite:' . __DIR__ . '/vector_db.sqlite');
            $this->vectorDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 創建向量表（如果不存在）
            $this->vectorDb->exec('CREATE TABLE IF NOT EXISTS embeddings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_id TEXT NOT NULL,
                embedding TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        }

        /**
         * 初始化文檔存儲
         */
        private function initDocumentStore() {
            // 使用 SQLite 存儲文檔內容
            $this->documentStore = new PDO('sqlite:' . __DIR__ . '/document_store.sqlite');
            $this->documentStore->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 創建文檔表（如果不存在）
            $this->documentStore->exec('CREATE TABLE IF NOT EXISTS documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_id TEXT NOT NULL UNIQUE,
                title TEXT,
                content TEXT NOT NULL,
                metadata TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        }

        /**
         * 獲取文本的嵌入向量
         * 
         * @param string $text 需要嵌入的文本
         * @return array 嵌入向量
         */
        public function getEmbedding(string $text): array {
            $url = "https://generativelanguage.googleapis.com/v1beta/{$this->embeddingModel}:embedContent?key={$this->apiKey}";
            
            $data = [
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];
            
            $response = $this->makeApiRequest($url, $data);
            
            if (isset($response['embedding']['values'])) {
                return $response['embedding']['values'];
            }
            
            throw new Exception("無法獲取嵌入向量: " . json_encode($response));
        }

        /**
         * 向 Gemini API 發送請求
         * 
         * @param string $url API 端點
         * @param array $data 請求數據
         * @return array 響應數據
         */
        private function makeApiRequest(string $url, array $data): array {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($statusCode !== 200) {
                throw new Exception("API 請求失敗，狀態碼: $statusCode, 響應: $response");
            }
            
            return json_decode($response, true);
        }

        /**
         * 添加文檔到 RAG 系統
         * 
         * @param string $docId 文檔 ID
         * @param string $title 文檔標題
         * @param string $content 文檔內容
         * @param array $metadata 文檔元數據
         * @return bool 是否成功
         */
        public function addDocument(string $docId, string $title, string $content, array $metadata = []): bool {
            try {
                // Check content size - reduced threshold
                if (strlen($content) > 15000) { // Reduced from 30000
                    // Split content into smaller chunks
                    $chunks = $this->splitContent($content, 10000); // Reduced from 25000
                    
                    foreach ($chunks as $index => $chunk) {
                        $chunkId = $docId . '_part_' . ($index + 1);
                        $chunkTitle = $title . ' (Part ' . ($index + 1) . ')';
                        $chunkMetadata = array_merge($metadata, ['chunk_index' => $index + 1]);
                        
                        // Process each chunk
                        if (!$this->addDocumentChunk($chunkId, $chunkTitle, $chunk, $chunkMetadata)) {
                            return false;
                        }
                    }
                    return true;
                }
                
                // For small documents, process normally
                return $this->addDocumentChunk($docId, $title, $content, $metadata);
            } catch (Exception $e) {
                error_log("添加文檔失敗: " . $e->getMessage());
                return false;
            }
        }

        /**
         * 分割文檔內容為較小的塊
         * 
         * @param string $content 文檔內容
         * @param int $maxLength 每塊的最大長度
         * @return array 分割後的文檔塊
         */
        private function splitContent(string $content, int $maxLength = 10000): array {
            $chunks = [];
            $currentChunk = '';
            $sentences = preg_split('/(?<=[。！？.!?])\s+/', $content);
            
            foreach ($sentences as $sentence) {
                // If adding this sentence would exceed max length, start a new chunk
                if (strlen($currentChunk . $sentence) > $maxLength && !empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }
                $currentChunk .= $sentence . ' ';
            }
            
            if (!empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
            }
            
            // Additional check: if a single chunk is still too large, split by characters
            $finalChunks = [];
            foreach ($chunks as $chunk) {
                if (strlen($chunk) > $maxLength) {
                    // Split long chunks by character count
                    $subChunks = str_split($chunk, $maxLength);
                    $finalChunks = array_merge($finalChunks, $subChunks);
                } else {
                    $finalChunks[] = $chunk;
                }
            }
            
            return $finalChunks;
        }
        
        /**
         * 添加文檔塊到系統
         * 
         * @param string $docId 文檔 ID
         * @param string $title 文檔標題
         * @param string $content 文檔內容
         * @param array $metadata 文檔元數據
         * @return bool 是否成功
         */
        private function addDocumentChunk(string $docId, string $title, string $content, array $metadata = []): bool {
            try {
                // 1. 存儲文檔內容
                $stmt = $this->documentStore->prepare('INSERT OR REPLACE INTO documents (doc_id, title, content, metadata) VALUES (?, ?, ?, ?)');
                $stmt->execute([$docId, $title, $content, json_encode($metadata)]);
                
                // 2. 為文檔生成嵌入向量
                $embedding = $this->getEmbedding($content);
                
                // 3. 存儲嵌入向量
                $stmt = $this->vectorDb->prepare('INSERT INTO embeddings (doc_id, embedding) VALUES (?, ?)');
                $stmt->execute([$docId, json_encode($embedding)]);
                
                return true;
            } catch (Exception $e) {
                error_log("添加文檔分塊失敗: " . $e->getMessage());
                return false;
            }
        }

        /**
         * 計算兩個向量之間的餘弦相似度
         * 
         * @param array $vec1 向量 1
         * @param array $vec2 向量 2
         * @return float 相似度分數
         */
        private function cosineSimilarity(array $vec1, array $vec2): float {
            $dotProduct = 0;
            $magnitude1 = 0;
            $magnitude2 = 0;
            
            foreach ($vec1 as $i => $val1) {
                $dotProduct += $val1 * $vec2[$i];
                $magnitude1 += $val1 * $val1;
                $magnitude2 += $vec2[$i] * $vec2[$i];
            }
            
            $magnitude1 = sqrt($magnitude1);
            $magnitude2 = sqrt($magnitude2);
            
            if ($magnitude1 == 0 || $magnitude2 == 0) {
                return 0;
            }
            
            return $dotProduct / ($magnitude1 * $magnitude2);
        }

        /**
         * 檢索與查詢最相似的文檔
         * 
         * @param string $query 查詢文本
         * @param int $limit 返回結果數量
         * @return array 相關文檔
         */
        public function retrieveDocuments(string $query, int $limit = 3): array {
            try {
                // 1. 獲取查詢的嵌入向量
                $queryEmbedding = $this->getEmbedding($query);
                
                // 2. 從資料庫獲取所有嵌入向量
                $stmt = $this->vectorDb->query('SELECT doc_id, embedding FROM embeddings');
                $results = [];
                
                // 3. 計算相似度並排序
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $docEmbedding = json_decode($row['embedding'], true);
                    $similarity = $this->cosineSimilarity($queryEmbedding, $docEmbedding);
                    
                    $results[] = [
                        'doc_id' => $row['doc_id'],
                        'similarity' => $similarity
                    ];
                }
                
                // 4. 按相似度排序
                usort($results, function($a, $b) {
                    return $b['similarity'] <=> $a['similarity'];
                });
                
                // 5. 獲取前 N 個文檔內容
                $topResults = array_slice($results, 0, $limit);
                $documents = [];
                
                foreach ($topResults as $result) {
                    $stmt = $this->documentStore->prepare('SELECT * FROM documents WHERE doc_id = ?');
                    $stmt->execute([$result['doc_id']]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($doc) {
                        $doc['similarity'] = $result['similarity'];
                        $doc['metadata'] = json_decode($doc['metadata'], true);
                        $documents[] = $doc;
                    }
                }
                
                return $documents;
            } catch (Exception $e) {
                error_log("檢索文檔失敗: " . $e->getMessage());
                return [];
            }
        }

        /**
         * 使用 Gemini API 生成回答
         * 
         * @param string $query 用戶查詢
         * @param array $context 檢索到的上下文
         * @return string 生成的回答
         */
        public function generateAnswer(string $query, array $context): string {
            // 構建提示詞
            $contextText = "";
            foreach ($context as $doc) {
                $contextText .= "標題: {$doc['title']}\n";
                $contextText .= "內容: {$doc['content']}\n\n";
            }
            
            $prompt = "基於以下信息回答問題。如果不知道答案，請說不知道，不要編造。\n\n";
            $prompt .= "上下文信息:\n$contextText\n";
            $prompt .= "問題: $query\n";
            $prompt .= "回答:";
            
            $url = "https://generativelanguage.googleapis.com/v1beta/{$this->generationModel}:generateContent?key={$this->apiKey}";
            
            $data = [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topP' => 0.8,
                    'topK' => 40,
                    'maxOutputTokens' => 1024
                ]
            ];
            
            $response = $this->makeApiRequest($url, $data);
            
            if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return $response['candidates'][0]['content']['parts'][0]['text'];
            }
            
            throw new Exception("無法生成回答: " . json_encode($response));
        }

        /**
         * 執行完整的 RAG 流程
         * 
         * @param string $query 用戶查詢
         * @param int $limit 檢索文檔數量
         * @return string 生成的回答
         */
        public function processQuery(string $query, int $limit = 3): string {
            try {
                // 1. 檢索相關文檔
                $relevantDocs = $this->retrieveDocuments($query, $limit);
                
                if (empty($relevantDocs)) {
                    return "我找不到相關的信息來回答您的問題。";
                }
                
                // 2. 使用檢索到的文檔和查詢來生成回答
                return $this->generateAnswer($query, $relevantDocs);
                
            } catch (Exception $e) {
                error_log("處理查詢失敗: " . $e->getMessage());
                return "處理您的請求時出現錯誤: " . $e->getMessage();
            }
        }
        
        /**
         * 獲取文檔存儲對象
         * 
         * @return PDO 文檔存儲對象
         */
        public function getDocumentStore() {
            return $this->documentStore;
        }
    }
}

// 如果直接運行此腳本，則執行示例
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // 載入環境變數
    if (file_exists(__DIR__ . '/env_loader.php')) {
        require_once __DIR__ . '/env_loader.php';
    }
    
    $apiKey = getenv('GEMINI_API_KEY') ?: '';
    
    // 初始化 RAG 系統
    $rag = new GeminiRAGSystem($apiKey);
    
    // 添加示例文檔
    $rag->addDocument(
        'doc1',
        'PHP 簡介',
        'PHP 是一種廣泛使用的開源腳本語言，特別適合於 Web 開發並可嵌入 HTML 中。PHP 代碼在服務器上執行，並將結果以純 HTML 形式發送到客戶端。',
        ['author' => 'PHP 文檔團隊', 'category' => '編程語言']
    );
    
    $rag->addDocument(
        'doc2',
        'Gemini API 入門',
        'Gemini 是 Google 開發的多模態 AI 模型，可以理解和生成文本、圖像、代碼等。通過 Gemini API，開發者可以輕鬆將這些 AI 能力集成到自己的應用中。',
        ['author' => 'Google AI 團隊', 'category' => 'AI API']
    );
    
    // 處理用戶查詢
    $query = "PHP 可以用來做什麼？";
    $answer = $rag->processQuery($query);
    
    echo "問題: $query\n";
    echo "回答: $answer\n";
}
?>