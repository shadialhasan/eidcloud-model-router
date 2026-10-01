<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter;

use EidCloud\ModelRouter\Classifier\TaskClassification;
use EidCloud\ModelRouter\Registry\ModelSpec;

/**
 * Encapsulates the final routing decision.
 */
class RoutingDecision
{
    /**
     * @param ModelSpec $selectedModel
     * @param TaskClassification $classification
     * @param array $costEstimation
     * @param string $reason
     * @param bool $isFallback
     * @param string|null $originalModelId
     * @param array<ModelSpec> $alternativeCandidates
     */
    public function __construct(
        public readonly ModelSpec $selectedModel,
        public readonly TaskClassification $classification,
        public readonly array $costEstimation,
        public readonly string $reason,
        public readonly bool $isFallback = false,
        public readonly ?string $originalModelId = null,
        public readonly array $alternativeCandidates = []
    ) {}

    public function toArray(): array
    {
        return [
            'status' => 'success',
            'selected_model' => [
                'id' => $this->selectedModel->id,
                'name' => $this->selectedModel->name,
                'provider' => $this->selectedModel->provider,
                'is_local' => $this->selectedModel->isLocal,
                'avg_latency_ms' => $this->selectedModel->avgLatencyMs,
                'vram_requirement_gb' => $this->selectedModel->vramRequirementGb,
            ],
            'classification' => $this->classification->toArray(),
            'cost_estimation' => $this->costEstimation,
            'reason' => $this->reason,
            'is_fallback' => $this->isFallback,
            'original_model_id' => $this->originalModelId,
            'alternatives' => array_map(fn(ModelSpec $m) => [
                'id' => $m->id,
                'provider' => $m->provider,
                'avg_latency_ms' => $m->avgLatencyMs,
            ], $this->alternativeCandidates),
        ];
    }
}
