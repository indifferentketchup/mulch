<?php

namespace IndifferentKetchup\CodexPz\Analysis;

/**
 * An OPTIONAL per-analysis verdict from an LLM classifier (the Qwen taxonomy in
 * tools/pz-analyzer). Carried on the Analysis and consulted by RankCalculator
 * as a bounded additive nudge. The deterministic rank never depends on it; when
 * absent, ranking and gating work unchanged.
 *
 * `category` vocabulary mirrors tools/pz-analyzer/pz_classify.py:
 *   corrupt_save, mod_conflict, missing_mod, lua_error, network_error,
 *   performance, warning, unknown.
 * `severity` is the LLM's own coarse label: problem | warning | info.
 */
final readonly class LlmVerdict implements \JsonSerializable
{
    public function __construct(
        public string $category,
        public string $severity,
        public float $confidence,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'category' => $this->category,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
        ];
    }
}
