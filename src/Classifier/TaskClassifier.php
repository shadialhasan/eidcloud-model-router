<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter\Classifier;

/**
 * High-speed heuristic and rule-based AI task classifier in pure PHP.
 */
class TaskClassifier
{
    // Pattern dictionaries
    private const ARABIC_UNICODE_PATTERN = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    private const CODE_KEYWORDS = [
        'code', 'script', 'programming', 'php', 'python', 'javascript', 'typescript', 'sql', 'html', 'css', 'rust', 'golang', 'c++', 'c#', 'java',
        'function', 'class', 'method', 'variable', 'query', 'debug', 'refactor', 'syntax', 'regex',
        'select *', 'insert into', 'dockerfile', 'bash', 'powershell', 'npm', 'composer', 'git', 'endpoint',
        'api route', 'controller', 'middleware', 'bug fix', 'unit test', 'pytest', 'phpunit', 'stacktrace',
        'exception', 'compiler', 'algorithm', 'recursion', 'binary search', 'async', 'promise'
    ];

    private const CODE_PATTERNS = [
        '/<\?php/i',
        '/```(?:php|python|js|ts|sql|html|css|cpp|cs|java|go|rust|sh|json)/i',
        '/\b(?:function|def|class|interface|import|export|public function|private function)\b/i',
        '/\b(?:SELECT\s+.+\s+FROM|UPDATE\s+.+\s+SET|DELETE\s+FROM|INSERT\s+INTO)\b/i',
        '/\b(?:console\.log|var_dump|print\(|echo\s+[\'"])/i',
        '/\{[\s\S]*;[\s\S]*\}/',
    ];

    private const VISION_KEYWORDS = [
        'image', 'picture', 'photo', 'diagram', 'chart', 'screenshot', 'graph', 'visual', 'ocr',
        'infographic', 'drawing', 'illustration', 'detect object', 'segmentation', 'bounding box',
        'extract diagram', 'analyze image', 'describe image', 'read receipt', 'scanned document',
        'jpg', 'jpeg', 'png', 'webp', 'svg'
    ];

    private const JSON_KEYWORDS = [
        'json', 'schema', 'extract json', 'structured data', 'key-value', 'parse json',
        'json schema', 'json format', 'yaml', 'convert to json', 'output in json', 'csv to json',
        'records strictly as json', 'as json'
    ];

    private const JSON_PATTERNS = [
        '/^\s*[\{\[](?:.*:.*|.*,.*)[\}\]]\s*$/s',
        '/output\s+(?:only\s+)?(?:as\s+|in\s+)?json/i',
        '/(?:strictly\s+)?valid\s+json/i',
        '/format\s*:\s*json/i',
        '/strictly\s+as\s+json/i',
        '/\bas\s+json\b/i',
    ];

    private const REASONING_KEYWORDS = [
        'prove', 'theorem', 'step-by-step reasoning', 'chain of thought', 'logic puzzle',
        'syllogism', 'deductive', 'inductive', 'mathematical proof', 'solve equation'
    ];

    /**
     * Classify input prompt into an actionable task category.
     *
     * @param string $prompt User prompt / input text
     * @param array<string, mixed> $context Additional metadata (e.g. ['has_images' => true])
     * @return TaskClassification
     */
    public function classify(string $prompt, array $context = []): TaskClassification
    {
        $promptClean = trim($prompt);
        $features = [];

        // 1. Direct explicit context checks (e.g. attached images/media)
        if (!empty($context['has_images']) || !empty($context['media'])) {
            $features[] = 'context_has_images';
            return new TaskClassification(
                category: 'vision',
                confidence: 0.99,
                detectedFeatures: $features,
                requiredCapability: 'vision'
            );
        }

        // 2. Arabic Language Detection
        $arabicMatches = preg_match_all(self::ARABIC_UNICODE_PATTERN, $promptClean);
        $promptLength = mb_strlen($promptClean, 'UTF-8');

        if ($promptLength > 0 && ($arabicMatches / max(1, $promptLength)) > 0.25) {
            $features[] = 'arabic_script_dominant';
            // Check if it's asking for code or vision in Arabic
            if ($this->hasKeywordMatch($promptClean, ['كود', 'برمجة', 'دالة', 'خوارزمية', 'sql', 'php', 'بايثون'])) {
                $features[] = 'arabic_code_request';
                return new TaskClassification(
                    category: 'code',
                    confidence: 0.92,
                    detectedFeatures: $features,
                    requiredCapability: 'code'
                );
            }
            if ($this->hasKeywordMatch($promptClean, ['صورة', 'مخطط', 'رسم بياني', 'لقطة شاشة'])) {
                $features[] = 'arabic_vision_request';
                return new TaskClassification(
                    category: 'vision',
                    confidence: 0.92,
                    detectedFeatures: $features,
                    requiredCapability: 'vision'
                );
            }
            return new TaskClassification(
                category: 'arabic',
                confidence: min(0.98, 0.70 + ($arabicMatches / $promptLength) * 0.3),
                detectedFeatures: $features,
                requiredCapability: 'arabic'
            );
        }

        // 3. Vision Detection
        $visionScore = 0;
        foreach (self::VISION_KEYWORDS as $vk) {
            if (stripos($promptClean, $vk) !== false) {
                $visionScore += 2;
                $features[] = 'vision_kw:' . $vk;
            }
        }
        if ($visionScore >= 2) {
            return new TaskClassification(
                category: 'vision',
                confidence: min(0.97, 0.65 + ($visionScore * 0.1)),
                detectedFeatures: $features,
                requiredCapability: 'vision'
            );
        }

        // 4. JSON / Structured Data Extraction
        $jsonScore = 0;
        foreach (self::JSON_PATTERNS as $pattern) {
            if (preg_match($pattern, $promptClean)) {
                $jsonScore += 3;
                $features[] = 'json_pattern_match';
            }
        }
        foreach (self::JSON_KEYWORDS as $jk) {
            if (stripos($promptClean, $jk) !== false) {
                $jsonScore += 2;
                $features[] = 'json_kw:' . $jk;
            }
        }
        if ($jsonScore >= 3) {
            return new TaskClassification(
                category: 'json',
                confidence: min(0.96, 0.60 + ($jsonScore * 0.1)),
                detectedFeatures: $features,
                requiredCapability: 'json'
            );
        }

        // 5. Code Detection
        $codeScore = 0;
        foreach (self::CODE_PATTERNS as $pattern) {
            if (preg_match($pattern, $promptClean)) {
                $codeScore += 3;
                $features[] = 'code_pattern_match';
            }
        }
        foreach (self::CODE_KEYWORDS as $ck) {
            if (stripos($promptClean, $ck) !== false) {
                $codeScore += 1;
                $features[] = 'code_kw:' . $ck;
            }
        }
        if ($codeScore >= 2) {
            return new TaskClassification(
                category: 'code',
                confidence: min(0.98, 0.60 + ($codeScore * 0.08)),
                detectedFeatures: $features,
                requiredCapability: 'code'
            );
        }

        // 6. Reasoning / Math Detection
        foreach (self::REASONING_KEYWORDS as $rk) {
            if (stripos($promptClean, $rk) !== false) {
                $features[] = 'reasoning_kw:' . $rk;
                return new TaskClassification(
                    category: 'reasoning',
                    confidence: 0.88,
                    detectedFeatures: $features,
                    requiredCapability: 'reasoning'
                );
            }
        }

        // 7. General fallback
        return new TaskClassification(
            category: 'general',
            confidence: 0.50,
            detectedFeatures: ['default_general_heuristic'],
            requiredCapability: 'general'
        );
    }

    private function hasKeywordMatch(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (stripos($text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }
}
