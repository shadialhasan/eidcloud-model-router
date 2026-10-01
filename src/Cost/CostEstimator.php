<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter\Cost;

use EidCloud\ModelRouter\Registry\ModelSpec;

/**
 * Calculates estimated prompt and completion cost in USD across providers.
 */
class CostEstimator
{
    /**
     * Approximate token estimation rule: ~4 chars per token for English/Code, ~1.5 - 2 chars for Arabic/Unicode.
     */
    public function estimateTokenCount(string $text): int
    {
        $len = mb_strlen($text, 'UTF-8');
        // Count non-ascii (e.g. Arabic, CJK)
        $nonAscii = preg_match_all('/[^\x00-\x7F]/u', $text);
        $ascii = $len - $nonAscii;

        $tokens = (int) ceil(($ascii / 4.0) + ($nonAscii / 1.8));
        return max(1, $tokens);
    }

    /**
     * Calculate cost in USD for given model and token counts.
     */
    public function calculateCost(ModelSpec $model, int $inputTokens, int $outputTokens): array
    {
        $inputCost = ($inputTokens / 1_000_000.0) * $model->inputCostPerMillion;
        $outputCost = ($outputTokens / 1_000_000.0) * $model->outputCostPerMillion;
        $totalCost = $inputCost + $outputCost;

        return [
            'model_id' => $model->id,
            'provider' => $model->provider,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'input_cost_usd' => round($inputCost, 7),
            'output_cost_usd' => round($outputCost, 7),
            'total_cost_usd' => round($totalCost, 7),
            'currency' => 'USD',
        ];
    }

    /**
     * Compare cost across multiple models for the same token workload.
     *
     * @param array<ModelSpec> $models
     * @return array<array> Sorted by total_cost_usd ascending
     */
    public function compare(array $models, int $inputTokens, int $outputTokens): array
    {
        $comparisons = [];
        foreach ($models as $model) {
            $comparisons[] = $this->calculateCost($model, $inputTokens, $outputTokens);
        }

        usort($comparisons, fn($a, $b) => $a['total_cost_usd'] <=> $b['total_cost_usd']);

        return $comparisons;
    }
}
