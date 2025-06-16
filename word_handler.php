<?php
/**
 * Word 文件處理器
 * 
 * 這個文件提供處理 Word 文件的功能，用於提取文本內容並添加到 RAG 系統
 */

if (!class_exists('WordHandler')) {
    class WordHandler {
        public function extractText($filePath) {
            // 檢查文件是否存在
            if (!file_exists($filePath)) {
                throw new Exception("文件不存在: " . $filePath);
            }
            
            // 檢查文件擴展名
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['doc', 'docx'])) {
                throw new Exception("不支持的文件格式: " . $fileExtension);
            }
            
            try {
                // 使用 PhpWord 讀取文件
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                
                // 提取文本
                $text = '';
                $sections = $phpWord->getSections();
                foreach ($sections as $section) {
                    $elements = $section->getElements();
                    foreach ($elements as $element) {
                        if (method_exists($element, 'getElements')) {
                            $elementsInner = $element->getElements();
                            foreach ($elementsInner as $elementInner) {
                                if (method_exists($elementInner, 'getText')) {
                                    $elementText = $elementInner->getText();
                                    if (is_array($elementText)) {
                                        $elementText = json_encode($elementText);
                                    }
                                    $text .= $elementText . ' ';
                                }
                            }
                        } elseif (method_exists($element, 'getText')) {
                            $elementText = $element->getText();
                            if (is_array($elementText)) {
                                $elementText = json_encode($elementText);
                            }
                            $text .= $elementText . ' ';
                        }
                    }
                }
                
                // 清理文本
                $text = trim($text);
                
                return $text;
            } catch (Exception $e) {
                throw new Exception("處理文件時發生錯誤: " . $e->getMessage());
            }
        }
    }
}