<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter\Registry;

/**
 * Model specifications and capabilities.
 */
class ModelSpec
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $provider,
        public readonly array $capabilities,
        public readonly float $inputCostPerMillion,
        public readonly float $outputCostPerMillion,
        public readonly int $contextWindow,
        public readonly float $avgLatencyMs,
        public readonly float $vramRequirementGb = 0.0,
        public readonly bool $isLocal = false,
        public bool $isAvailable = true,
        public readonly ?string $fallbackModelId = null,
        public readonly int $priority = 100
    ) {}

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'capabilities' => $this->capabilities,
            'pricing' => [
                'input_cost_per_1m_usd' => $this->inputCostPerMillion,
                'output_cost_per_1m_usd' => $this->outputCostPerMillion,
            ],
            'context_window' => $this->contextWindow,
            'avg_latency_ms' => $this->avgLatencyMs,
            'vram_requirement_gb' => $this->vramRequirementGb,
            'is_local' => $this->isLocal,
            'is_available' => $this->isAvailable,
            'fallback_model_id' => $this->fallbackModelId,
            'priority' => $this->priority,
        ];
    }
}
