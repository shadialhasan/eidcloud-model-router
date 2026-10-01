<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter;

use EidCloud\ModelRouter\Classifier\TaskClassifier;
use EidCloud\ModelRouter\Cost\CostEstimator;
use EidCloud\ModelRouter\Registry\ModelRegistry;
use EidCloud\ModelRouter\Registry\ModelSpec;
use RuntimeException;

/**
 * Main AI Model Router orchestrator.
 */
class Router
{
    private TaskClassifier $classifier;
    private ModelRegistry $registry;
    private CostEstimator $costEstimator;

    public function __construct(
        ?ModelRegistry $registry = null,
        ?TaskClassifier $classifier = null,
        ?CostEstimator $costEstimator = null
    ) {
        $this->registry = $registry ?? new ModelRegistry();
        $this->classifier = $classifier ?? new TaskClassifier();
        $this->costEstimator = $costEstimator ?? new CostEstimator();
    }

    public function getRegistry(): ModelRegistry
    {
        return $this->registry;
    }

    public function getClassifier(): TaskClassifier
    {
        return $this->classifier;
    }

    public function getCostEstimator(): CostEstimator
    {
        return $this->costEstimator;
    }

    /**
     * Route a prompt to the optimal model based on classification, constraints, and cost.
     *
     * @param string $prompt Prompt text to analyze and route
     * @param array{
     *   max_latency_ms?: float,
     *   max_vram_gb?: float,
     *   estimated_completion_tokens?: int,
     *   preferred_provider?: string,
     *   context?: array
     * } $options Routing constraints and hints
     * @return RoutingDecision
     */
    public function route(string $prompt, array $options = []): RoutingDecision
    {
        $context = $options['context'] ?? [];
        $classification = $this->classifier->classify($prompt, $context);

        $maxLatencyMs = isset($options['max_latency_ms']) ? (float)$options['max_latency_ms'] : null;
        $maxVramGb = isset($options['max_vram_gb']) ? (float)$options['max_vram_gb'] : null;

        // Find available candidates matching capability
        $candidates = $this->registry->findMatching(
            requiredCapability: $classification->requiredCapability,
            maxLatencyMs: $maxLatencyMs,
            maxVramGb: $maxVramGb,
            requireAvailable: true
        );

        $isFallback = false;
        $originalModelId = null;

        if (empty($candidates)) {
            // Check if there was an intended primary model that was offline
            $offlineCandidates = $this->registry->findMatching(
                requiredCapability: $classification->requiredCapability,
                maxLatencyMs: $maxLatencyMs,
                maxVramGb: $maxVramGb,
                requireAvailable: false
            );

            if (!empty($offlineCandidates)) {
                $primaryOffline = $offlineCandidates[0];
                $originalModelId = $primaryOffline->id;

                // Look for its explicit fallback
                if ($primaryOffline->fallbackModelId !== null) {
                    $fbModel = $this->registry->get($primaryOffline->fallbackModelId);
                    if ($fbModel && $fbModel->isAvailable) {
                        $selectedModel = $fbModel;
                        $isFallback = true;
                    }
                }
            }

            // If still no selected model, relax constraints to find any general/fallback available model
            if (!isset($selectedModel)) {
                $generalCandidates = $this->registry->findMatching(
                    requiredCapability: 'general',
                    maxLatencyMs: null,
                    maxVramGb: null,
                    requireAvailable: true
                );
                if (empty($generalCandidates)) {
                    throw new RuntimeException("No available AI model found matching requirements or fallback.");
                }
                $selectedModel = $generalCandidates[0];
                $isFallback = true;
            }
        } else {
            $selectedModel = $candidates[0];
        }

        // Calculate token and cost estimates
        $inputTokens = $this->costEstimator->estimateTokenCount($prompt);
        $outputTokens = $options['estimated_completion_tokens'] ?? (int) ceil($inputTokens * 1.2);
        if ($outputTokens < 100) {
            $outputTokens = 250;
        }

        $cost = $this->costEstimator->calculateCost($selectedModel, $inputTokens, $outputTokens);

        $reason = sprintf(
            "Task classified as '%s' (confidence: %.2f) requiring '%s' capability. Selected '%s' (%s).%s",
            $classification->category,
            $classification->confidence,
            $classification->requiredCapability,
            $selectedModel->id,
            $selectedModel->provider,
            $isFallback ? " [Fallback active from original '{$originalModelId}']" : ""
        );

        $alternatives = array_values(array_filter(
            $candidates,
            fn(ModelSpec $m) => $m->id !== $selectedModel->id
        ));

        return new RoutingDecision(
            selectedModel: $selectedModel,
            classification: $classification,
            costEstimation: $cost,
            reason: $reason,
            isFallback: $isFallback,
            originalModelId: $originalModelId,
            alternativeCandidates: $alternatives
        );
    }
}
