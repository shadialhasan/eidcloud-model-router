<?php

declare(strict_types=1);

namespace EidCloud\ModelRouter\Classifier;

/**
 * Task classification result.
 */
class TaskClassification
{
    /**
     * @param string $category Core category (code, vision, arabic, json, reasoning, general)
     * @param float $confidence Score between 0.0 and 1.0
     * @param array<string> $detectedFeatures Specific matched cues or features
     * @param string $requiredCapability Primary LLM capability required
     */
    public function __construct(
        public readonly string $category,
        public readonly float $confidence,
        public readonly array $detectedFeatures,
        public readonly string $requiredCapability
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'confidence' => $this->confidence,
            'detected_features' => $this->detectedFeatures,
            'required_capability' => $this->requiredCapability,
        ];
    }
}
