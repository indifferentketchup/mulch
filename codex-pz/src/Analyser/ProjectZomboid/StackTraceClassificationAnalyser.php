<?php

namespace IndifferentKetchup\CodexPz\Analyser\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analyser\Analyser;
use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\AnalysisInterface;
use IndifferentKetchup\CodexPz\Analysis\AttributionConfidence;
use IndifferentKetchup\CodexPz\Analysis\Insight;
use IndifferentKetchup\CodexPz\Analysis\ModAttribution;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineNoiseExceptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\LuaModRuntimeProblem;
use IndifferentKetchup\CodexPz\Log\EntryInterface;

/**
 * Owns ALL "Exception thrown"-shaped entries (the ONE-PRODUCER SEAM, T2): the
 * PatternAnalyser families never match "Exception thrown", so there is exactly
 * one classified row per underlying error and no merge-time dedup is needed.
 *
 * This is a cross-entry custom Analyser because the two real Project Zomboid
 * stack layouts demand it (CRIT-002):
 *
 *   (a) B41/B42 — single entry. The "Exception thrown" header and its
 *       tab-indented frames are folded into ONE Entry by the parser, so
 *       (string)$entry already holds the whole stack.
 *
 *   (b) B4x — multi entry. The exception type is INLINE in the header
 *       ("Exception thrown <Class> at <method>. Message: <msg>") and the frames
 *       arrive as SEPARATE adjacent entries (same timestamp, carrying
 *       "DebugLogStream.printException" / "Stack trace:"). Assembly forward-walks
 *       those adjacent entries — a header-only regex would miss every frame.
 *
 * Classification phases mirror the deterministic Python prototype
 * (tools/pz-analyzer/pz_parser.py): stack assembly, mod attribution (direct +
 * ~40-line lookback), cause-chain unwinding, file:line extraction, kind /
 * engine-noise tagging, and a deterministic fingerprint.
 *
 * SEC-002: every field that carries raw log bytes (causeChain, deepestModFrame,
 * file:line, exception class, mod name) is derived from a control-stripped copy
 * of the assembled stack, so no ANSI/control bytes reach an emitted field.
 */
class StackTraceClassificationAnalyser extends Analyser
{
    /**
     * Maximum number of exception entries classified before bailing out.
     * Mirrors ErrorContextAnalyser::HIT_CAP — caps work on logs with cascading
     * per-tick exceptions.
     */
    public const int HIT_CAP = 500;

    /** Lookback window (raw file lines) for inferred mod attribution. */
    public const int INFERRED_LOOKBACK_LINES = 40;

    /** Maximum cause-chain levels retained. */
    public const int MAX_CAUSE_CHAIN_LEVELS = 6;

    /** Fully-qualified Java/Lua exception-class token (allows the $ inner-class separator). */
    private const string EXCEPTION_CLASS = '[A-Za-z0-9_.$]+(?:Exception|Error)';

    /** Direct mod-attribution marker. */
    private const string MOD_MARKER = '/Lua\(\(MOD:([^)]+)\)\)/u';

    /** A line that looks like a stack frame (gates the B4x forward-walk and frame collection). */
    private const string STACK_HINT = '/\bat\s|\.lua|\[string|Lua\(\(MOD:/u';

    public function analyse(): AnalysisInterface
    {
        $analysis = new Analysis();
        $analysis->setLog($this->log);

        $entries = [];
        foreach ($this->log as $entry) {
            $entries[] = $entry;
        }
        $count = count($entries);

        $hits = 0;
        for ($i = 0; $i < $count; $i++) {
            $rawText = (string) $entries[$i];
            if (!str_contains($rawText, 'Exception thrown')) {
                continue;
            }
            if ($hits >= self::HIT_CAP) {
                break;
            }
            $hits++;

            // Strip control bytes ONCE at the analyser boundary; everything is
            // derived from $assembled, so no field can carry raw control bytes.
            $assembled = $this->stripControl($this->assembleStack($entries, $i));

            $exceptionClass = $this->extractExceptionClass($assembled);
            $attribution = $this->attribute($assembled, $entries, $i);
            $causeChain = $this->extractCauseChain($assembled);
            $fileLine = $this->extractFileLine($assembled);
            $frames = $this->extractFrames($assembled);
            $kind = $this->classifyKind($assembled, $attribution);

            $modId = $attribution !== null ? $this->normaliseModKey($attribution->modName) : '';
            $fingerprint = 'sha256:' . substr(
                hash('sha256', $exceptionClass . '|' . implode('|', array_slice($frames, 0, 3)) . '|' . $modId),
                0,
                16
            );

            $insight = $this->buildInsight(
                $kind,
                $exceptionClass,
                $attribution,
                $causeChain,
                $fileLine,
                $frames
            );
            $insight->setEntry($entries[$i])->setFingerprint($fingerprint);
            $analysis->addInsight($insight);
        }

        return $analysis;
    }

    /**
     * Assemble the full stack text for the exception entry at $i.
     *
     * Layout (a) is handled implicitly: the entry's own text already holds the
     * folded frames. Layout (b) forward-walks contiguous, same-timestamp
     * "DebugLogStream.printException" / "Stack trace:" / stack-shaped entries
     * (stopping at the next "Exception thrown" header or a timestamp jump) and
     * appends their text.
     */
    private function assembleStack(array $entries, int $i): string
    {
        $parts = [(string) $entries[$i]];
        $baseTime = $entries[$i]->getTime();
        $count = count($entries);

        for ($j = $i + 1; $j < $count; $j++) {
            $text = (string) $entries[$j];
            if (str_contains($text, 'Exception thrown')) {
                break;
            }
            $isContinuation = str_contains($text, 'DebugLogStream.printException')
                || str_contains($text, 'Stack trace:')
                || preg_match(self::STACK_HINT, $text) === 1;
            if (!$isContinuation) {
                break;
            }
            $time = $entries[$j]->getTime();
            if ($baseTime !== null && $time !== null && abs($time - $baseTime) > 1) {
                break;
            }
            $parts[] = $text;
        }

        return implode("\n", $parts);
    }

    /**
     * Extract the leading exception class. Prefers the token following
     * "Exception thrown" (matches both the inline B4x form and, via \s+ across
     * the newline, the B41 tab-continuation form); falls back to the first
     * exception-shaped token anywhere in the assembled stack.
     */
    private function extractExceptionClass(string $assembled): string
    {
        if (preg_match('/Exception thrown\s+(' . self::EXCEPTION_CLASS . ')/u', $assembled, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/(' . self::EXCEPTION_CLASS . ')/u', $assembled, $m) === 1) {
            return $m[1];
        }
        return '';
    }

    /**
     * Determine mod attribution. Direct: a Lua((MOD:X)) marker in the assembled
     * stack. Inferred: no direct marker but the nearest Lua((MOD:X)) or
     * "Loading: .../mods/<name>/" within the raw-line lookback window.
     * Otherwise null (unknown).
     */
    private function attribute(string $assembled, array $entries, int $i): ?ModAttribution
    {
        if (preg_match(self::MOD_MARKER, $assembled, $m) === 1) {
            return new ModAttribution(
                trim($m[1]),
                null,
                $this->extractDeepestModFrame($assembled),
                AttributionConfidence::Direct
            );
        }

        $inferred = $this->inferModFromLookback($entries, $i);
        if ($inferred !== null) {
            return $inferred;
        }

        return null;
    }

    /**
     * Scan prior entries within INFERRED_LOOKBACK_LINES raw file lines,
     * nearest-first, for a Lua((MOD:X)) marker or a "Loading: .../mods/<name>/"
     * path. Returns an Inferred ModAttribution or null.
     */
    private function inferModFromLookback(array $entries, int $i): ?ModAttribution
    {
        $baseLine = $this->firstLineNumber($entries[$i]);
        if ($baseLine === null) {
            return null;
        }
        $threshold = $baseLine - self::INFERRED_LOOKBACK_LINES;

        for ($j = $i - 1; $j >= 0; $j--) {
            $priorLine = $this->firstLineNumber($entries[$j]);
            if ($priorLine !== null && $priorLine < $threshold) {
                break;
            }
            $lines = preg_split('/\r\n|\r|\n/', (string) $entries[$j]) ?: [];
            for ($k = count($lines) - 1; $k >= 0; $k--) {
                $line = $lines[$k];
                if (preg_match(self::MOD_MARKER, $line, $m) === 1) {
                    return new ModAttribution(trim($m[1]), null, null, AttributionConfidence::Inferred);
                }
                if (preg_match('#Loading:\s+\S*?/mods/([^/]+)/#u', $line, $m) === 1) {
                    return new ModAttribution(trim($m[1]), null, null, AttributionConfidence::Inferred);
                }
            }
        }

        return null;
    }

    private function firstLineNumber(EntryInterface $entry): ?int
    {
        $lines = $entry->getLines();
        if ($lines === []) {
            return null;
        }
        return $lines[0]->getNumber();
    }

    /** The nearest Lua((MOD:X)).method(...) frame, or the bare marker if no method follows. */
    private function extractDeepestModFrame(string $assembled): ?string
    {
        if (preg_match('/Lua\(\(MOD:[^)]+\)\)\.\S+/u', $assembled, $m) === 1) {
            return trim($m[0]);
        }
        if (preg_match('/Lua\(\(MOD:[^)]+\)\)/u', $assembled, $m) === 1) {
            return trim($m[0]);
        }
        return null;
    }

    /**
     * Build the " -> "-joined cause chain: the leading exception, then each
     * "Caused by:" target (on the same line or the next non-blank line).
     * Deduped, capped at MAX_CAUSE_CHAIN_LEVELS.
     */
    private function extractCauseChain(string $assembled): ?string
    {
        $tokens = [];
        $seen = [];

        $lead = '/Exception thrown\s+(' . self::EXCEPTION_CLASS . ')(?::\s*(.+?))?(?=\s+at\s|\.\s+Message:|\s*$)/u';
        if (preg_match($lead, $assembled, $m) === 1) {
            $this->pushCauseToken($tokens, $seen, $m[1], $m[2] ?? '');
        }

        $lines = preg_split('/\r\n|\r|\n/', $assembled) ?: [];
        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            if (count($tokens) >= self::MAX_CAUSE_CHAIN_LEVELS) {
                break;
            }
            if (preg_match('/Caused\s+by:\s*(.*)$/iu', $lines[$i], $cm) !== 1) {
                continue;
            }
            $target = trim($cm[1]);
            if ($target === '' && $i + 1 < $lineCount) {
                $target = trim($lines[$i + 1]);
            }
            $token = '/(' . self::EXCEPTION_CLASS . ')(?::\s*(.+?))?(?=\s+at\s|\.\s+Message:|\s*$)/u';
            if (preg_match($token, $target, $em) === 1) {
                $this->pushCauseToken($tokens, $seen, $em[1], $em[2] ?? '');
            }
        }

        if ($tokens === []) {
            return null;
        }
        return implode(' -> ', array_slice($tokens, 0, self::MAX_CAUSE_CHAIN_LEVELS));
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $seen
     */
    private function pushCauseToken(array &$tokens, array &$seen, string $class, string $message): void
    {
        $message = trim($message);
        $token = $message !== '' ? $class . ': ' . $message : $class;
        $token = trim($token);
        if ($token !== '' && !in_array($token, $seen, true)) {
            $seen[] = $token;
            $tokens[] = $token;
        }
    }

    /** First file:line found among the frame shapes; '' if none. */
    private function extractFileLine(string $assembled): string
    {
        if (preg_match('/\(([^()\s]+\.(?:lua|java)):(\d+)\)/u', $assembled, $m) === 1) {
            return $m[1] . ':' . $m[2];
        }
        if (preg_match('/\bat\s+([^\s:]+\.lua):(\d+)/u', $assembled, $m) === 1) {
            return $m[1] . ':' . $m[2];
        }
        if (preg_match('/\[string\s+["\']([^"\']+\.lua)["\']\]:(\d+)/u', $assembled, $m) === 1) {
            return $m[1] . ':' . $m[2];
        }
        return '';
    }

    /**
     * Stack-shaped, trimmed, non-empty lines of the assembled stack.
     *
     * @return list<string>
     */
    private function extractFrames(string $assembled): array
    {
        $frames = [];
        foreach (preg_split('/\r\n|\r|\n/', $assembled) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match(self::STACK_HINT, $trimmed) === 1) {
                $frames[] = $trimmed;
            }
        }
        return $frames;
    }

    /**
     * Engine noise (Kahlua dump / DebugFileWatcher NoSuchFile) outranks all
     * other kinds. Then a mod attribution makes it a mod runtime crash.
     * Otherwise it is a generic / property-not-found Java exception.
     */
    private function classifyKind(string $assembled, ?ModAttribution $attribution): string
    {
        if (
            preg_match('/KahluaThread\.flushErrorMessage/u', $assembled) === 1
            || preg_match('/dumping\s+Lua\s+stack\s+trace/iu', $assembled) === 1
            || (preg_match('/DebugFileWatcher/u', $assembled) === 1
                && preg_match('/NoSuchFileException/u', $assembled) === 1)
        ) {
            return 'engine_noise';
        }
        if ($attribution !== null) {
            return 'mod_runtime';
        }
        return 'java_exception';
    }

    /**
     * @param list<string> $frames
     */
    private function buildInsight(
        string $kind,
        string $exceptionClass,
        ?ModAttribution $attribution,
        ?string $causeChain,
        string $fileLine,
        array $frames
    ): Insight {
        return match ($kind) {
            'engine_noise' => (new EngineNoiseExceptionInformation())
                ->setExceptionClass($exceptionClass)
                ->setSignature($this->normaliseSignature($exceptionClass . '|' . ($frames[0] ?? '')))
                ->setCauseChain($causeChain),
            'mod_runtime' => (new LuaModRuntimeProblem())
                ->setExceptionClass($exceptionClass)
                ->setModAttribution($attribution)
                ->setCauseChain($causeChain),
            default => (new JavaExceptionProblem())
                ->setExceptionClass($exceptionClass)
                ->setFileLine($fileLine)
                ->setCauseChain($causeChain),
        };
    }

    /** Normalise a mod name to a stable key: lowercased, spaces/apostrophes/hyphens removed. */
    private function normaliseModKey(string $name): string
    {
        return (string) preg_replace('/[\s\'\-]/u', '', mb_strtolower($name));
    }

    /** Lowercase, flatten >=2-digit runs to <N>, collapse whitespace — for engine-noise coalescing. */
    private function normaliseSignature(string $signature): string
    {
        $signature = mb_strtolower($signature);
        $signature = (string) preg_replace('/\d{2,}/u', '<N>', $signature);
        $signature = (string) preg_replace('/\s+/u', ' ', $signature);
        return trim($signature);
    }

    /** SEC-002: strip control / ANSI bytes, keep \t \n \r. */
    private function stripControl(string $value): string
    {
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return $stripped ?? $value;
    }
}
