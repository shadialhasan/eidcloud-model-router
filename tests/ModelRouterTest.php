<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter\Tests;

use EidCloud\ModelRouter\Classifier\TaskClassifier;
use EidCloud\ModelRouter\Cost\CostEstimator;
use EidCloud\ModelRouter\Registry\ModelRegistry;
use EidCloud\ModelRouter\Registry\ModelSpec;
use EidCloud\ModelRouter\Router;

/**
 * Zero-dependency unit and integration test suite for EidCloud Model Router.
 */
class ModelRouterTest
{
    private int $passes = 0;
    private int $fails = 0;
    private array $errors = [];

    public function run(): bool
    {
        echo "\033[1;34mRunning EidCloud Model Router Test Suite...\033[0m\n\n";

        $this->testTaskClassificationCategories();
        $this->testCapabilityMatching();
        $this->testLatencyAndVramBudgetConstraints();
        $this->testCostEstimationAccuracy();
        $this->testDynamicFallbackRouting();
        $this->testCliRouterOutput();

        echo "\n" . str_repeat("=", 50) . "\n";
        echo sprintf("Total Tests Run: %d\n", $this->passes + $this->fails);
        echo sprintf("\033[1;32mPassed: %d\033[0m\n", $this->passes);
        if ($this->fails > 0) {
            echo sprintf("\033[1;31mFailed: %d\033[0m\n", $this->fails);
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
            return false;
        }

        echo "\033[1;32mAll Tests Passed 100%! Ready for production.\033[0m\n";
        return true;
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passes++;
            echo "  \033[32m✔\033[0m {$message}\n";
        } else {
            $this->fails++;
            $this->errors[] = $message;
            echo "  \033[31m✘\033[0m {$message}\n";
        }
    }

    private function testTaskClassificationCategories(): void
    {
        echo "\033[1m[1] Testing Heuristic Task Classification:\033[0m\n";
        $classifier = new TaskClassifier();

        // 1. Code
        $codeRes1 = $classifier->classify("Write PHP code for user authentication");
        $this->assert($codeRes1->category === 'code', "Detects English code prompt ('Write PHP code')");

        $codeRes2 = $classifier->classify("Fix SQL query: SELECT * FROM users WHERE active = 1");
        $this->assert($codeRes2->category === 'code', "Detects SQL code snippet prompt");

        // 2. Vision
        $visionRes1 = $classifier->classify("Analyze image and extract diagram flow");
        $this->assert($visionRes1->category === 'vision', "Detects vision/diagram query");

        $visionRes2 = $classifier->classify("What is in this receipt?", ['has_images' => true]);
        $this->assert($visionRes2->category === 'vision', "Detects vision via image attachment context");

        // 3. Arabic
        $arabicRes1 = $classifier->classify("ترجم هذا المقال إلى اللغة العربية الفصحى");
        $this->assert($arabicRes1->category === 'arabic', "Detects Arabic translation task");

        $arabicRes2 = $classifier->classify("لخص النص التالي واشرح أهم النقاط");
        $this->assert($arabicRes2->category === 'arabic', "Detects Arabic summarization task");

        // 4. JSON / Structured data
        $jsonRes1 = $classifier->classify("Extract name and email into valid JSON format strictly");
        $this->assert($jsonRes1->category === 'json', "Detects JSON extraction requirement");

        $jsonRes2 = $classifier->classify("Parse this data and output only as json schema");
        $this->assert($jsonRes2->category === 'json', "Detects JSON schema output");
    }

    private function testCapabilityMatching(): void
    {
        echo "\n\033[1m[2] Testing Capability Matcher & Router:\033[0m\n";
        $router = new Router();

        $codeDecision = $router->route("Write a PHP 8.4 script implementing an LRU cache");
        $this->assert(
            $codeDecision->selectedModel->hasCapability('code'),
            "Routed code prompt to code-capable model: {$codeDecision->selectedModel->id}"
        );
        $this->assert(
            in_array($codeDecision->selectedModel->id, ['qwen2.5-coder-32b', 'deepseek-coder-v2', 'claude-3-5-sonnet'], true),
            "Selected optimal coder model: {$codeDecision->selectedModel->id}"
        );

        $arabicDecision = $router->route("لخص المقال واكتب خاتمة باللغة العربية");
        $this->assert(
            $arabicDecision->selectedModel->hasCapability('arabic'),
            "Routed Arabic prompt to Arabic-capable model: {$arabicDecision->selectedModel->id}"
        );

        $visionDecision = $router->route("Extract text and labels from this architecture diagram");
        $this->assert(
            $visionDecision->selectedModel->hasCapability('vision'),
            "Routed vision prompt to vision model: {$visionDecision->selectedModel->id}"
        );

        $jsonDecision = $router->route("Convert table to JSON format strictly");
        $this->assert(
            $jsonDecision->selectedModel->hasCapability('json'),
            "Routed JSON prompt to fast JSON model: {$jsonDecision->selectedModel->id}"
        );
    }

    private function testLatencyAndVramBudgetConstraints(): void
    {
        echo "\n\033[1m[3] Testing Latency and VRAM Budget Constraints:\033[0m\n";
        $router = new Router();

        // Enforce ultra-low latency (< 150ms) for JSON task
        $fastDecision = $router->route("Extract user records strictly as json", [
            'max_latency_ms' => 120.0
        ]);
        $this->assert(
            $fastDecision->selectedModel->avgLatencyMs <= 120.0,
            "Enforced latency budget <= 120ms (Selected: {$fastDecision->selectedModel->id} with {$fastDecision->selectedModel->avgLatencyMs}ms)"
        );

        // Enforce VRAM constraint (< 4GB) on local edge models
        $vramDecision = $router->route("Convert to json", [
            'max_vram_gb' => 3.0
        ]);
        if ($vramDecision->selectedModel->isLocal) {
            $this->assert(
                $vramDecision->selectedModel->vramRequirementGb <= 3.0,
                "Enforced VRAM constraint <= 3GB (Selected: {$vramDecision->selectedModel->id} with {$vramDecision->selectedModel->vramRequirementGb}GB)"
            );
        } else {
            $this->assert(true, "Selected cloud model with 0GB local VRAM consumption");
        }
    }

    private function testCostEstimationAccuracy(): void
    {
        echo "\n\033[1m[4] Testing Cost Estimator:\033[0m\n";
        $estimator = new CostEstimator();
        $testModel = new ModelSpec(
            id: 'test-model',
            name: 'Test Model',
            provider: 'EidCloud Test',
            capabilities: ['general'],
            inputCostPerMillion: 1.00,
            outputCostPerMillion: 2.00,
            contextWindow: 32000,
            avgLatencyMs: 200.0
        );

        $cost = $estimator->calculateCost($testModel, 100_000, 50_000);
        // 100k input @ $1/1M = $0.10
        // 50k output @ $2/1M = $0.10
        // Total = $0.20
        $this->assert(abs($cost['input_cost_usd'] - 0.10) < 0.0001, "Input cost calculated accurately ($0.10)");
        $this->assert(abs($cost['output_cost_usd'] - 0.10) < 0.0001, "Output cost calculated accurately ($0.10)");
        $this->assert(abs($cost['total_cost_usd'] - 0.20) < 0.0001, "Total cost calculated accurately ($0.20)");

        $tokens = $estimator->estimateTokenCount("Hello world, this is a test prompt for token estimation.");
        $this->assert($tokens > 10 && $tokens < 20, "Token count estimation reasonably calculated (~{$tokens} tokens)");
    }

    private function testDynamicFallbackRouting(): void
    {
        echo "\n\033[1m[5] Testing Dynamic Fallback Routing on Outage:\033[0m\n";
        $registry = new ModelRegistry();
        $router = new Router($registry);

        // Mark primary coder model offline
        $registry->setAvailability('qwen2.5-coder-32b', false);

        $decision = $router->route("Write a PHP script for file upload");
        $this->assert($decision->selectedModel->id !== 'qwen2.5-coder-32b', "Bypassed offline model 'qwen2.5-coder-32b'");
        $this->assert($decision->selectedModel->isAvailable, "Routed to available alternative '{$decision->selectedModel->id}'");

        // Mark all coder models offline to trigger secondary fallback
        $registry->setAvailability('deepseek-coder-v2', false);
        $registry->setAvailability('claude-3-5-sonnet', false);

        $fallbackDecision = $router->route("Write a PHP function");
        $this->assert($fallbackDecision->isFallback === true, "Marked decision as fallback when primary tier is unavailable");
        $this->assert($fallbackDecision->selectedModel->isAvailable, "Fallback successfully engaged to '{$fallbackDecision->selectedModel->id}'");
    }

    private function testCliRouterOutput(): void
    {
        echo "\n\033[1m[6] Testing CLI Executable via exec:\033[0m\n";
        $cliPath = dirname(__DIR__) . '/bin/eidcloud-router';

        $output = [];
        $returnCode = 0;
        exec("php \"{$cliPath}\" route \"Fix SQL query\" --json", $output, $returnCode);
        $jsonStr = implode("\n", $output);
        $parsed = json_decode($jsonStr, true);

        $this->assert($returnCode === 0, "CLI exit code is 0");
        $this->assert(is_array($parsed) && isset($parsed['selected_model']), "CLI outputs valid JSON structure");
        $this->assert($parsed['classification']['category'] === 'code', "CLI classification accurately tagged 'code'");
    }
}
