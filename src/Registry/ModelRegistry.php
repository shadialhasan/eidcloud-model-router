<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter\Registry;

/**
 * Model registry managing registered LLM models and their capabilities.
 */
class ModelRegistry
{
    /** @var array<string, ModelSpec> */
    private array $models = [];

    public function __construct(bool $loadDefaults = true)
    {
        if ($loadDefaults) {
            $this->loadDefaultModels();
        }
    }

    public function register(ModelSpec $model): self
    {
        $this->models[$model->id] = $model;
        return $this;
    }

    public function get(string $id): ?ModelSpec
    {
        return $this->models[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->models[$id]);
    }

    /**
     * @return array<string, ModelSpec>
     */
    public function all(): array
    {
        return $this->models;
    }

    public function setAvailability(string $id, bool $available): bool
    {
        if (isset($this->models[$id])) {
            $this->models[$id]->isAvailable = $available;
            return true;
        }
        return false;
    }

    /**
     * Find models meeting required capability, latency budget, and VRAM budget.
     *
     * @return array<ModelSpec>
     */
    public function findMatching(
        string $requiredCapability,
        ?float $maxLatencyMs = null,
        ?float $maxVramGb = null,
        bool $requireAvailable = true
    ): array {
        $matches = [];

        foreach ($this->models as $model) {
            if ($requireAvailable && !$model->isAvailable) {
                continue;
            }

            if (!$model->hasCapability($requiredCapability)) {
                continue;
            }

            if ($maxLatencyMs !== null && $model->avgLatencyMs > $maxLatencyMs) {
                continue;
            }

            if ($maxVramGb !== null && $model->isLocal && $model->vramRequirementGb > $maxVramGb) {
                continue;
            }

            $matches[] = $model;
        }

        // Sort by priority (ascending: lower number = higher priority), then cost
        usort($matches, function (ModelSpec $a, ModelSpec $b) {
            if ($a->priority !== $b->priority) {
                return $a->priority <=> $b->priority;
            }
            $costA = $a->inputCostPerMillion + $a->outputCostPerMillion;
            $costB = $b->inputCostPerMillion + $b->outputCostPerMillion;
            return $costA <=> $costB;
        });

        return $matches;
    }

    private function loadDefaultModels(): void
    {
        $defaults = [
            // Code specialized models
            new ModelSpec(
                id: 'qwen2.5-coder-32b',
                name: 'Qwen 2.5 Coder 32B Instruct',
                provider: 'Alibaba Cloud / EidCloud Self-Host',
                capabilities: ['code', 'chat', 'general'],
                inputCostPerMillion: 0.80,
                outputCostPerMillion: 1.60,
                contextWindow: 131072,
                avgLatencyMs: 380.0,
                vramRequirementGb: 24.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'deepseek-coder-v2',
                priority: 10
            ),
            new ModelSpec(
                id: 'deepseek-coder-v2',
                name: 'DeepSeek Coder V2 16B',
                provider: 'DeepSeek',
                capabilities: ['code', 'chat'],
                inputCostPerMillion: 0.14,
                outputCostPerMillion: 0.28,
                contextWindow: 128000,
                avgLatencyMs: 420.0,
                vramRequirementGb: 16.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'claude-3-5-sonnet',
                priority: 20
            ),
            new ModelSpec(
                id: 'claude-3-5-sonnet',
                name: 'Claude 3.5 Sonnet',
                provider: 'Anthropic',
                capabilities: ['code', 'vision', 'reasoning', 'general'],
                inputCostPerMillion: 3.00,
                outputCostPerMillion: 15.00,
                contextWindow: 200000,
                avgLatencyMs: 750.0,
                vramRequirementGb: 0.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'gpt-4o',
                priority: 30
            ),

            // Vision specialized models
            new ModelSpec(
                id: 'qwen2.5-vl-72b',
                name: 'Qwen 2.5 VL 72B Instruct',
                provider: 'Alibaba Cloud / EidCloud Vision',
                capabilities: ['vision', 'ocr', 'multimodal'],
                inputCostPerMillion: 1.20,
                outputCostPerMillion: 2.40,
                contextWindow: 128000,
                avgLatencyMs: 650.0,
                vramRequirementGb: 48.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'gpt-4o',
                priority: 10
            ),
            new ModelSpec(
                id: 'gpt-4o',
                name: 'GPT-4o Omnimodal',
                provider: 'OpenAI',
                capabilities: ['vision', 'arabic', 'reasoning', 'general'],
                inputCostPerMillion: 2.50,
                outputCostPerMillion: 10.00,
                contextWindow: 128000,
                avgLatencyMs: 520.0,
                vramRequirementGb: 0.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'gpt-4o-mini',
                priority: 20
            ),
            new ModelSpec(
                id: 'gpt-4o-mini',
                name: 'GPT-4o Mini',
                provider: 'OpenAI',
                capabilities: ['vision', 'json', 'chat', 'general'],
                inputCostPerMillion: 0.15,
                outputCostPerMillion: 0.60,
                contextWindow: 128000,
                avgLatencyMs: 290.0,
                vramRequirementGb: 0.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: null,
                priority: 30
            ),

            // Arabic specialized models
            new ModelSpec(
                id: 'jais-30b-chat',
                name: 'Jais 30B Chat',
                provider: 'G42 / Inception',
                capabilities: ['arabic', 'chat'],
                inputCostPerMillion: 0.90,
                outputCostPerMillion: 1.80,
                contextWindow: 32768,
                avgLatencyMs: 360.0,
                vramRequirementGb: 20.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'allam-7b',
                priority: 10
            ),
            new ModelSpec(
                id: 'allam-7b',
                name: 'ALLaM 7B Instruct',
                provider: 'SDAIA / EidCloud Local',
                capabilities: ['arabic', 'chat', 'summarization'],
                inputCostPerMillion: 0.35,
                outputCostPerMillion: 0.70,
                contextWindow: 16384,
                avgLatencyMs: 220.0,
                vramRequirementGb: 8.0,
                isLocal: true,
                isAvailable: true,
                fallbackModelId: 'gpt-4o',
                priority: 15
            ),

            // JSON / Small fast structured models
            new ModelSpec(
                id: 'smollm2-1.7b',
                name: 'SmolLM2 1.7B Instruct',
                provider: 'HuggingFace / Local Edge',
                capabilities: ['json', 'fast', 'classification'],
                inputCostPerMillion: 0.05,
                outputCostPerMillion: 0.10,
                contextWindow: 8192,
                avgLatencyMs: 95.0,
                vramRequirementGb: 3.5,
                isLocal: true,
                isAvailable: true,
                fallbackModelId: 'llama-3.2-1b',
                priority: 5
            ),
            new ModelSpec(
                id: 'llama-3.2-1b',
                name: 'Llama 3.2 1B Instruct',
                provider: 'Meta / Local Edge',
                capabilities: ['json', 'fast', 'chat'],
                inputCostPerMillion: 0.04,
                outputCostPerMillion: 0.08,
                contextWindow: 131072,
                avgLatencyMs: 80.0,
                vramRequirementGb: 2.5,
                isLocal: true,
                isAvailable: true,
                fallbackModelId: 'gpt-4o-mini',
                priority: 10
            ),
            new ModelSpec(
                id: 'gemini-1.5-flash',
                name: 'Gemini 1.5 Flash',
                provider: 'Google DeepMind',
                capabilities: ['json', 'general', 'vision', 'reasoning'],
                inputCostPerMillion: 0.075,
                outputCostPerMillion: 0.30,
                contextWindow: 1000000,
                avgLatencyMs: 250.0,
                vramRequirementGb: 0.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'gpt-4o-mini',
                priority: 25
            ),

            // General & Reasoning models
            new ModelSpec(
                id: 'deepseek-v3',
                name: 'DeepSeek V3 (671B MoE)',
                provider: 'DeepSeek',
                capabilities: ['general', 'reasoning', 'chat'],
                inputCostPerMillion: 0.27,
                outputCostPerMillion: 1.10,
                contextWindow: 128000,
                avgLatencyMs: 450.0,
                vramRequirementGb: 0.0,
                isLocal: false,
                isAvailable: true,
                fallbackModelId: 'gpt-4o',
                priority: 12
            )
        ];

        foreach ($defaults as $model) {
            $this->register($model);
        }
    }
}
