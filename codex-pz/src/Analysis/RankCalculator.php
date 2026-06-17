<?php

namespace IndifferentKetchup\CodexPz\Analysis;

use IndifferentKetchup\CodexPz\Log\Level;

/**
 * Computes a single per-insight `rank` (a sort score) from the framework-level
 * signals an insight already carries. This is the "priority" axis that sits on
 * top of the `Severity` "floor" axis (Sentry / RFC 5424 two-axis model).
 *
 * The headline problem this fixes: the legacy consumers sorted by
 * `severity * counter`, which AMPLIFIES noise (a high-frequency cosmetic
 * warning outranks a rare real crash). Here frequency is a small CAPPED damp,
 * never a multiplier, and attribution (is a mod responsible?) is the dominant
 * lever, because the corpus shows ~98.4% of error volume is unattributed
 * engine chatter while the actionable signal lives in the mod-attributed core.
 *
 * Weights are lifted verbatim from the design doc
 * (.scratch/pz/severity-ranking-design.md section 3) and held as constants so
 * both the score and any future re-tuning live in exactly one place.
 */
final class RankCalculator
{
    public const int DEFAULT_FLOOR = Severity::Low->value;

    public const array ATTRIBUTION_BONUS = ['direct' => 40, 'inferred' => 15, 'unattributed' => -30];
    public const array KIND_BONUS = ['lua_runtime' => 30, 'require_failed' => 15, 'java_exception' => 20, 'runtime' => -10, 'engine_noise' => -100];
    public const array LEVEL_BONUS = ['ERROR' => 10, 'WARN' => 0];
    public const array ACTIONABILITY_BONUS = ['automatable' => 20, 'cause_chain' => 10];
    public const int RARITY_BONUS_DIRECT_LE5 = 15;
    public const array FREQUENCY_DAMP = [5 => 0, 25 => 2, 100 => 4, PHP_INT_MAX => 6];
    public const int UNATTRIBUTED_FLOOR = Severity::Low->value;
    public const int ENGINE_NOISE_FLOOR = Severity::Noise->value;

    /**
     * Resolve the attribution confidence from a ModAttributed insight, or null
     * when the insight names no mod (treated as unattributed).
     */
    private static function attribution(InsightInterface $insight): ?AttributionConfidence
    {
        if ($insight instanceof ModAttributedInsightInterface) {
            return $insight->getModAttribution()?->confidence;
        }
        return null;
    }

    /**
     * Compute the rank score for an insight. Higher = more important.
     */
    public static function compute(InsightInterface $insight): int
    {
        $rank = $insight instanceof SeverityAwareInsightInterface
            ? $insight->getSeverity()->value
            : self::DEFAULT_FLOOR;

        $confidence = self::attribution($insight);
        $rank += match ($confidence) {
            AttributionConfidence::Direct => self::ATTRIBUTION_BONUS['direct'],
            AttributionConfidence::Inferred => self::ATTRIBUTION_BONUS['inferred'],
            default => self::ATTRIBUTION_BONUS['unattributed'],
        };

        $kind = $insight->getKind()->value;
        $rank += self::KIND_BONUS[$kind] ?? 0;

        $level = $insight->getEntry()?->getLevel();
        if ($level instanceof Level) {
            $rank += self::LEVEL_BONUS[strtoupper($level->asString())] ?? 0;
        }

        if ($insight instanceof CauseChainInsightInterface
            && ($insight->getCauseChain() ?? '') !== '') {
            $rank += self::ACTIONABILITY_BONUS['cause_chain'];
        }

        if ($insight instanceof ProblemInterface) {
            foreach ($insight->getSolutions() as $solution) {
                if ($solution instanceof AutomatableSolutionInterface) {
                    $rank += self::ACTIONABILITY_BONUS['automatable'];
                    break;
                }
            }
        }

        if ($confidence === AttributionConfidence::Direct
            && $insight->getCounterValue() <= 5) {
            $rank += self::RARITY_BONUS_DIRECT_LE5;
        }

        $occ = $insight->getCounterValue();
        foreach (self::FREQUENCY_DAMP as $threshold => $damp) {
            if ($occ <= $threshold) {
                $rank -= $damp;
                break;
            }
        }

        $attributed = $confidence === AttributionConfidence::Direct
            || $confidence === AttributionConfidence::Inferred;
        if (!$attributed) {
            $rank = min($rank, self::UNATTRIBUTED_FLOOR);
        }

        if ($insight->getKind() === Kind::EngineNoise) {
            $rank = min($rank, self::ENGINE_NOISE_FLOOR);
        }

        return $rank;
    }

    /**
     * Apply the OPTIONAL LLM verdict as a bounded additive adjustment. The
     * deterministic rank stands alone; this only nudges when a verdict exists.
     * The LLM can PROMOTE an already-credible problem but never rescue noise.
     */
    public static function applyLlmAdjustment(int $rank, ?LlmVerdict $verdict): int
    {
        if ($verdict === null) {
            return $rank;
        }
        if ($verdict->confidence < 0.5) {
            return $rank;
        }

        $delta = match ($verdict->category) {
            'corrupt_save', 'lua_error', 'mod_conflict' => $rank >= Severity::Medium->value ? 15 : 0,
            'network_error', 'missing_mod' => 5,
            'performance', 'warning' => -10,
            default => 0,
        };
        if ($verdict->severity === 'info') {
            $delta -= 5;
        }

        return max(Severity::Noise->value, min(Severity::Critical->value, $rank + $delta));
    }
}
