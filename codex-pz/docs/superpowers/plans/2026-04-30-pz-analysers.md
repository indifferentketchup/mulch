# ProjectZomboid Phase B.1 ServerLog Analysers — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three top-priority ServerLog analysers (engine version, mod load + missing-mod problem, server exception coalesced by type) by introducing five Insight classes that plug into the framework's existing `PatternAnalyser`, then wire `ProjectZomboidServerLog::getDefaultAnalyser()` to return a configured analyser carrying them.

**Architecture:** All Phase B.1 analysis is done by a single vanilla `PatternAnalyser` — no custom Analyser subclass is needed because `Entry::__toString()` joins all of an entry's lines with `\n`, and `PatternAnalyser::analyseEntry` runs `preg_match_all` against the stringified entry. A single multi-line regex on `ServerExceptionProblem` therefore captures both the ERROR header and the trailing tab-indented stack body in one match. Each Insight class declares its own `getPatterns()`/`setMatches()` and the framework coalesces equal insights via the existing `Insight::isEqual()` mechanism.

**Tech Stack:** PHP 8.4+, PHPUnit 12, Composer (root package: `indifferentketchup/codex`). PHP/Composer not installed on host — all command invocations wrap in `docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest …`.

**Spec:** `docs/superpowers/specs/2026-04-30-pz-analysers-design.md`

---

## Pre-flight

The test runner across this whole plan is:

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest composer test
```

To run a single test file:

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit test/tests/Games/ProjectZomboid/Analysis/EngineVersionInformationTest.php
```

Project root is the repository root. All paths in this plan are relative to it.

---

## File Structure

| File | Purpose | Created/Modified |
|---|---|---|
| `src/Analysis/ProjectZomboid/EngineVersionInformation.php` | Information capturing the version banner (one per file) | Create |
| `src/Analysis/ProjectZomboid/ModLoadInformation.php` | Information per `loading <mod>` line, coalesced by mod name | Create |
| `src/Analysis/ProjectZomboid/ModMissingProblem.php` | Problem per missing mod, attaches a `ModMissingSolution` | Create |
| `src/Analysis/ProjectZomboid/ModMissingSolution.php` | Solution attached to `ModMissingProblem` | Create |
| `src/Analysis/ProjectZomboid/ServerExceptionProblem.php` | Problem capturing exception type + stack body, coalesced by type | Create |
| `src/Pattern/ProjectZomboid/DebugServerPattern.php` | Add new `EXCEPTION` constant for header+body capture | Modify |
| `src/Log/ProjectZomboid/ProjectZomboidServerLog.php` | Wire `getDefaultAnalyser()` to register all four insight classes | Modify |
| `test/tests/Games/ProjectZomboid/Analysis/EngineVersionInformationTest.php` | Unit test for the engine-version insight | Create |
| `test/tests/Games/ProjectZomboid/Analysis/ModLoadInformationTest.php` | Unit test for the mod-load insight | Create |
| `test/tests/Games/ProjectZomboid/Analysis/ModMissingProblemTest.php` | Unit test for the missing-mod problem and its solution | Create |
| `test/tests/Games/ProjectZomboid/Analysis/ServerExceptionProblemTest.php` | Unit test for exception type+body capture and coalescing | Create |
| `test/tests/Games/ProjectZomboid/Analyser/ServerLogAnalysisTest.php` | End-to-end test: parse fixture → analyse → assert insight set | Create |

No test fixture changes — the existing synthetic `test/src/Games/ProjectZomboid/fixtures/debug-server-minimal.txt` already contains everything the end-to-end test needs.

---

## Task 0: Pre-phase-B checkpoint

A revert anchor before adding new code.

- [ ] **Step 1: Create the empty checkpoint commit**

```bash
git commit --allow-empty -m "pre-phase-B checkpoint"
```

---

## Task 1: EngineVersionInformation

**Files:**
- Create: `src/Analysis/ProjectZomboid/EngineVersionInformation.php`
- Test: `test/tests/Games/ProjectZomboid/Analysis/EngineVersionInformationTest.php`

The pattern source `DebugServerPattern::VERSION` already exists (Phase A); this task only consumes it.

- [ ] **Step 1: Write the failing test**

Create `test/tests/Games/ProjectZomboid/Analysis/EngineVersionInformationTest.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\Codex\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class EngineVersionInformationTest extends TestCase
{
    public function testGetPatternsReturnsTheVersionRegex(): void
    {
        $this->assertSame([DebugServerPattern::VERSION], EngineVersionInformation::getPatterns());
    }

    public function testSetMatchesPopulatesLabelAndValue(): void
    {
        $line = '[16-04-26 00:00:42.407] LOG  : General      f:0, t:1776297642406, st:48,648,157,584> version=42.16.3 0000000000000000000000000000000000000000 2026-04-08 11:54:01 (ZB) demo=false.';
        $this->assertSame(1, preg_match(DebugServerPattern::VERSION, $line, $matches));

        $insight = new EngineVersionInformation();
        $insight->setMatches($matches, 0);

        $this->assertSame('Engine version', $insight->getLabel());
        $this->assertSame('42.16.3 (build 0000000000000000000000000000000000000000, 2026-04-08 11:54:01)', $insight->getValue());
        $this->assertSame('Engine version: 42.16.3 (build 0000000000000000000000000000000000000000, 2026-04-08 11:54:01)', $insight->getMessage());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit test/tests/Games/ProjectZomboid/Analysis/EngineVersionInformationTest.php
```

Expected: FAIL with "Class \"IndifferentKetchup\\Codex\\Analysis\\ProjectZomboid\\EngineVersionInformation\" not found".

- [ ] **Step 3: Write the implementation**

Create `src/Analysis/ProjectZomboid/EngineVersionInformation.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Analysis\ProjectZomboid;

use IndifferentKetchup\Codex\Analysis\Information;
use IndifferentKetchup\Codex\Analysis\PatternInsightInterface;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;

class EngineVersionInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [DebugServerPattern::VERSION];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Engine version');
        $this->setValue(sprintf(
            '%s (build %s, %s %s)',
            $matches['version'],
            $matches['hash'],
            $matches['date'],
            $matches['time']
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest composer test
```

Expected: all tests PASS, count increased by 2.

- [ ] **Step 5: Commit**

```bash
git add src/Analysis/ProjectZomboid/EngineVersionInformation.php test/tests/Games/ProjectZomboid/Analysis/EngineVersionInformationTest.php
git commit -m "Add EngineVersionInformation insight"
```

---

## Task 2: ModLoadInformation

**Files:**
- Create: `src/Analysis/ProjectZomboid/ModLoadInformation.php`
- Test: `test/tests/Games/ProjectZomboid/Analysis/ModLoadInformationTest.php`

- [ ] **Step 1: Write the failing test**

Create `test/tests/Games/ProjectZomboid/Analysis/ModLoadInformationTest.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class ModLoadInformationTest extends TestCase
{
    public function testGetPatternsReturnsTheModLoadRegex(): void
    {
        $this->assertSame([DebugServerPattern::MOD_LOAD], ModLoadInformation::getPatterns());
    }

    public function testSetMatchesExtractsModName(): void
    {
        $line = '[16-04-26 00:01:19.131] LOG  : Mod          f:0, t:1776297679131, st:48,648,194,309> loading example_mod_alpha.';
        $this->assertSame(1, preg_match(DebugServerPattern::MOD_LOAD, $line, $matches));

        $insight = new ModLoadInformation();
        $insight->setMatches($matches, 0);

        $this->assertSame('Mod loaded', $insight->getLabel());
        $this->assertSame('example_mod_alpha', $insight->getValue());
    }

    public function testIsEqualCoalescesSameMod(): void
    {
        $a = $this->insightFor('example_mod_alpha');
        $b = $this->insightFor('example_mod_alpha');
        $c = $this->insightFor('example_mod_beta');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    private function insightFor(string $modName): ModLoadInformation
    {
        $insight = new ModLoadInformation();
        $insight->setMatches(['mod' => $modName], 0);
        return $insight;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit test/tests/Games/ProjectZomboid/Analysis/ModLoadInformationTest.php
```

Expected: FAIL with class-not-found.

- [ ] **Step 3: Write the implementation**

Create `src/Analysis/ProjectZomboid/ModLoadInformation.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Analysis\ProjectZomboid;

use IndifferentKetchup\Codex\Analysis\Information;
use IndifferentKetchup\Codex\Analysis\PatternInsightInterface;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;

class ModLoadInformation extends Information implements PatternInsightInterface
{
    public static function getPatterns(): array
    {
        return [DebugServerPattern::MOD_LOAD];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->setLabel('Mod loaded');
        $this->setValue($matches['mod']);
    }
}
```

The default `Information::isEqual` (label + value match) covers the coalescing requirement — no override needed.

- [ ] **Step 4: Run test to verify it passes**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest composer test
```

Expected: all tests PASS, count increased by 3.

- [ ] **Step 5: Commit**

```bash
git add src/Analysis/ProjectZomboid/ModLoadInformation.php test/tests/Games/ProjectZomboid/Analysis/ModLoadInformationTest.php
git commit -m "Add ModLoadInformation insight"
```

---

## Task 3: ModMissingProblem and ModMissingSolution

**Files:**
- Create: `src/Analysis/ProjectZomboid/ModMissingSolution.php`
- Create: `src/Analysis/ProjectZomboid/ModMissingProblem.php`
- Test: `test/tests/Games/ProjectZomboid/Analysis/ModMissingProblemTest.php`

These two ship together because `ModMissingSolution` is meaningful only as a child of `ModMissingProblem`.

- [ ] **Step 1: Write the failing test**

Create `test/tests/Games/ProjectZomboid/Analysis/ModMissingProblemTest.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModMissingSolution;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class ModMissingProblemTest extends TestCase
{
    public function testGetPatternsReturnsTheModMissingRegex(): void
    {
        $this->assertSame([DebugServerPattern::MOD_MISSING], ModMissingProblem::getPatterns());
    }

    public function testSetMatchesExtractsModNameAndAttachesSolution(): void
    {
        $line = '[16-04-26 00:01:19.200] WARN : Mod          f:0, t:1776297679200, st:48,648,194,378> ZomboidFileSystem.loadModAndRequired> required mod "absent_mod" not found.';
        $this->assertSame(1, preg_match(DebugServerPattern::MOD_MISSING, $line, $matches));

        $problem = new ModMissingProblem();
        $problem->setMatches($matches, 0);

        $this->assertSame('absent_mod', $problem->getModName());
        $this->assertStringContainsString('absent_mod', $problem->getMessage());
        $this->assertCount(1, $problem->getSolutions());

        $solution = $problem->getSolutions()[0];
        $this->assertInstanceOf(ModMissingSolution::class, $solution);
        $this->assertStringContainsString('absent_mod', $solution->getMessage());
        $this->assertStringContainsString('serverconfig.ini', $solution->getMessage());
    }

    public function testIsEqualCoalescesSameMissingMod(): void
    {
        $a = $this->problemFor('mod_x');
        $b = $this->problemFor('mod_x');
        $c = $this->problemFor('mod_y');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    private function problemFor(string $modName): ModMissingProblem
    {
        $problem = new ModMissingProblem();
        $problem->setMatches(['mod' => $modName], 0);
        return $problem;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit test/tests/Games/ProjectZomboid/Analysis/ModMissingProblemTest.php
```

Expected: FAIL with class-not-found.

- [ ] **Step 3: Write `ModMissingSolution`**

Create `src/Analysis/ProjectZomboid/ModMissingSolution.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Analysis\ProjectZomboid;

use IndifferentKetchup\Codex\Analysis\Solution;

class ModMissingSolution extends Solution
{
    private string $modName = '';

    public function setModName(string $modName): static
    {
        $this->modName = $modName;
        return $this;
    }

    public function getMessage(): string
    {
        return sprintf(
            'Subscribe to mod "%s" or remove its ID from the Mods= line in serverconfig.ini.',
            $this->modName
        );
    }
}
```

- [ ] **Step 4: Write `ModMissingProblem`**

Create `src/Analysis/ProjectZomboid/ModMissingProblem.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Analysis\ProjectZomboid;

use IndifferentKetchup\Codex\Analysis\InsightInterface;
use IndifferentKetchup\Codex\Analysis\PatternInsightInterface;
use IndifferentKetchup\Codex\Analysis\Problem;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;

class ModMissingProblem extends Problem implements PatternInsightInterface
{
    private string $modName = '';

    public static function getPatterns(): array
    {
        return [DebugServerPattern::MOD_MISSING];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->modName = $matches['mod'];
        $this->addSolution((new ModMissingSolution())->setModName($this->modName));
    }

    public function getModName(): string
    {
        return $this->modName;
    }

    public function getMessage(): string
    {
        return sprintf('Required mod "%s" not found.', $this->modName);
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self && $insight->getModName() === $this->modName;
    }
}
```

- [ ] **Step 5: Run all tests to verify pass**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest composer test
```

Expected: all tests PASS, count increased by 3.

- [ ] **Step 6: Commit**

```bash
git add src/Analysis/ProjectZomboid/ModMissingProblem.php src/Analysis/ProjectZomboid/ModMissingSolution.php test/tests/Games/ProjectZomboid/Analysis/ModMissingProblemTest.php
git commit -m "Add ModMissingProblem and ModMissingSolution"
```

---

## Task 4: ServerExceptionProblem (with new EXCEPTION pattern constant)

**Files:**
- Modify: `src/Pattern/ProjectZomboid/DebugServerPattern.php` (add `EXCEPTION` constant)
- Create: `src/Analysis/ProjectZomboid/ServerExceptionProblem.php`
- Test: `test/tests/Games/ProjectZomboid/Analysis/ServerExceptionProblemTest.php`

- [ ] **Step 1: Write the failing test**

Create `test/tests/Games/ProjectZomboid/Analysis/ServerExceptionProblemTest.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Test\Tests\Games\ProjectZomboid\Analysis;

use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ServerExceptionProblem;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;
use PHPUnit\Framework\TestCase;

class ServerExceptionProblemTest extends TestCase
{
    public function testGetPatternsReturnsTheExceptionRegex(): void
    {
        $this->assertSame([DebugServerPattern::EXCEPTION], ServerExceptionProblem::getPatterns());
    }

    public function testSetMatchesCapturesTypeAndBodyAcrossLines(): void
    {
        $entryText = "[16-04-26 00:01:19.080] ERROR: General      f:0, t:1776297679080, st:48,648,194,258> DebugFileWatcher.registerDir> Exception thrown\n"
            . "\tjava.nio.file.NoSuchFileException: /placeholder/config/mods at UnixException.translateToIOException(null:-1).\n"
            . "\tStack trace:\n"
            . "\t\tjava.base/sun.nio.fs.UnixException.translateToIOException(Unknown Source)";

        $this->assertSame(1, preg_match(DebugServerPattern::EXCEPTION, $entryText, $matches));

        $problem = new ServerExceptionProblem();
        $problem->setMatches($matches, 0);

        $this->assertSame('java.nio.file.NoSuchFileException', $problem->getExceptionType());
        $this->assertStringContainsString('Stack trace', $problem->getBody());
        $this->assertStringContainsString('java.base/sun.nio.fs.UnixException', $problem->getBody());
    }

    public function testIsEqualCoalescesSameTypeRegardlessOfBody(): void
    {
        $a = $this->problemFor('java.io.IOException', 'body one');
        $b = $this->problemFor('java.io.IOException', 'body two completely different');
        $c = $this->problemFor('java.lang.RuntimeException', 'body one');

        $this->assertTrue($a->isEqual($b));
        $this->assertFalse($a->isEqual($c));
    }

    public function testNestedExceptionTypeNamesAreSupported(): void
    {
        $entryText = "[16-04-26 00:01:45.937] ERROR: WorldGen     f:0, t:1776297705937, st:48,648,221,115> IsoPropertyType.lookupOrDefaultStr> Exception thrown\n"
            . "\tzombie.core.properties.IsoPropertyType\$IsoPropertyTypeNotFoundException: Property Name not found: ladderW";

        $this->assertSame(1, preg_match(DebugServerPattern::EXCEPTION, $entryText, $matches));

        $problem = new ServerExceptionProblem();
        $problem->setMatches($matches, 0);

        $this->assertSame('zombie.core.properties.IsoPropertyType$IsoPropertyTypeNotFoundException', $problem->getExceptionType());
    }

    private function problemFor(string $type, string $body): ServerExceptionProblem
    {
        $problem = new ServerExceptionProblem();
        $problem->setMatches(['type' => $type, 'body' => $body], 0);
        return $problem;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit test/tests/Games/ProjectZomboid/Analysis/ServerExceptionProblemTest.php
```

Expected: FAIL with class-not-found AND constant-not-defined errors.

- [ ] **Step 3: Add the `EXCEPTION` constant to `DebugServerPattern`**

Modify `src/Pattern/ProjectZomboid/DebugServerPattern.php`. After the existing `EXCEPTION_HEADER` constant, add:

```php
    public const string EXCEPTION = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\][^\n]+Exception thrown\n\t(?<type>[A-Za-z0-9_.$]+(?:Exception|Error))[^\n]*(?<body>(?:\n\t.+)*)/';
```

The full file becomes:

```php
<?php

namespace IndifferentKetchup\Codex\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid DebugLog-server.txt format.
 *
 * LINE captures, in order:
 *   1. time   (DD-MM-YY HH:MM:SS.mmm)
 *   2. level  (LOG | WARN | ERROR | INFO | DEBUG)
 *   3. prefix (subsystem name, e.g. General, Mod, WorldGen)
 *
 * The f:/t:/st: metadata and trailing message body are intentionally not
 * captured by the parser; analyzers reach into the Line raw text directly.
 */
class DebugServerPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\]\s+(\w+)\s*:\s+(\S+)\s+f:\d+,\s+t:\d+,\s+st:[\d,]+>\s+.*$/';

    public const string VERSION = '/version=(?<version>\S+) (?<hash>[a-f0-9]{40}) (?<date>\d{4}-\d{2}-\d{2}) (?<time>\d{2}:\d{2}:\d{2})/';

    public const string MOD_LOAD = '/loading (?<mod>[A-Za-z0-9_]+)\.?$/';

    public const string MOD_MISSING = '/required mod "(?<mod>[^"]+)" not found/';

    public const string EXCEPTION_HEADER = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\]\s+ERROR:.*Exception thrown/';

    public const string EXCEPTION = '/^\[\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\][^\n]+Exception thrown\n\t(?<type>[A-Za-z0-9_.$]+(?:Exception|Error))[^\n]*(?<body>(?:\n\t.+)*)/';
}
```

- [ ] **Step 4: Write the implementation**

Create `src/Analysis/ProjectZomboid/ServerExceptionProblem.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Analysis\ProjectZomboid;

use IndifferentKetchup\Codex\Analysis\InsightInterface;
use IndifferentKetchup\Codex\Analysis\PatternInsightInterface;
use IndifferentKetchup\Codex\Analysis\Problem;
use IndifferentKetchup\Codex\Pattern\ProjectZomboid\DebugServerPattern;

class ServerExceptionProblem extends Problem implements PatternInsightInterface
{
    private string $exceptionType = '';
    private string $body = '';

    public static function getPatterns(): array
    {
        return [DebugServerPattern::EXCEPTION];
    }

    public function setMatches(array $matches, mixed $patternKey): void
    {
        $this->exceptionType = $matches['type'];
        $this->body = trim($matches['body'] ?? '');
    }

    public function getExceptionType(): string
    {
        return $this->exceptionType;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMessage(): string
    {
        return sprintf('Exception thrown: %s', $this->exceptionType);
    }

    public function isEqual(InsightInterface $insight): bool
    {
        return $insight instanceof self
            && $insight->getExceptionType() === $this->exceptionType;
    }
}
```

- [ ] **Step 5: Run all tests to verify pass**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest composer test
```

Expected: all tests PASS, count increased by 4.

- [ ] **Step 6: Commit**

```bash
git add src/Pattern/ProjectZomboid/DebugServerPattern.php src/Analysis/ProjectZomboid/ServerExceptionProblem.php test/tests/Games/ProjectZomboid/Analysis/ServerExceptionProblemTest.php
git commit -m "Add ServerExceptionProblem insight"
```

---

## Task 5: Wire ProjectZomboidServerLog default analyser + end-to-end test

**Files:**
- Modify: `src/Log/ProjectZomboid/ProjectZomboidServerLog.php`
- Test: `test/tests/Games/ProjectZomboid/Analyser/ServerLogAnalysisTest.php`

- [ ] **Step 1: Write the failing end-to-end test**

Create `test/tests/Games/ProjectZomboid/Analyser/ServerLogAnalysisTest.php`:

```php
<?php

namespace IndifferentKetchup\Codex\Test\Tests\Games\ProjectZomboid\Analyser;

use IndifferentKetchup\Codex\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ServerExceptionProblem;
use IndifferentKetchup\Codex\Log\File\PathLogFile;
use IndifferentKetchup\Codex\Log\ProjectZomboid\ProjectZomboidServerLog;
use PHPUnit\Framework\TestCase;

class ServerLogAnalysisTest extends TestCase
{
    private function fixturePath(): string
    {
        return __DIR__ . '/../../../../src/Games/ProjectZomboid/fixtures/debug-server-minimal.txt';
    }

    public function testAnalyseProducesExpectedInsightSet(): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $this->assertCount(1, $analysis->getFilteredInsights(EngineVersionInformation::class));
        $this->assertCount(3, $analysis->getFilteredInsights(ModLoadInformation::class));
        $this->assertCount(1, $analysis->getFilteredInsights(ModMissingProblem::class));
        $this->assertCount(2, $analysis->getFilteredInsights(ServerExceptionProblem::class));
    }

    public function testAnalysisCarriesAttachedSolutionForMissingMod(): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $missing = $analysis->getFilteredInsights(ModMissingProblem::class);
        $this->assertCount(1, $missing);
        $this->assertCount(1, $missing[0]->getSolutions());
    }

    public function testTwoDistinctExceptionsAreNotCoalesced(): void
    {
        $log = (new ProjectZomboidServerLog())->setLogFile(new PathLogFile($this->fixturePath()));
        $log->parse();
        $analysis = $log->analyse();

        $exceptions = $analysis->getFilteredInsights(ServerExceptionProblem::class);
        $types = array_map(fn($e) => $e->getExceptionType(), $exceptions);
        sort($types);

        $this->assertSame(
            [
                'java.nio.file.NoSuchFileException',
                'zombie.core.properties.IsoPropertyType$IsoPropertyTypeNotFoundException',
            ],
            $types
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest vendor/bin/phpunit test/tests/Games/ProjectZomboid/Analyser/ServerLogAnalysisTest.php
```

Expected: FAIL — `ProjectZomboidServerLog::getDefaultAnalyser()` currently returns an empty `PatternAnalyser` with no insight classes registered, so all four `getFilteredInsights` calls return zero items and the count assertions fail.

- [ ] **Step 3: Wire `ProjectZomboidServerLog::getDefaultAnalyser()`**

Modify `src/Log/ProjectZomboid/ProjectZomboidServerLog.php`. Replace the body of `getDefaultAnalyser()`:

```php
    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return (new PatternAnalyser())
            ->addPossibleInsightClass(EngineVersionInformation::class)
            ->addPossibleInsightClass(ModLoadInformation::class)
            ->addPossibleInsightClass(ModMissingProblem::class)
            ->addPossibleInsightClass(ServerExceptionProblem::class);
    }
```

Add the four corresponding `use` statements at the top of the file (after the existing `use` lines):

```php
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\EngineVersionInformation;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModLoadInformation;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ModMissingProblem;
use IndifferentKetchup\Codex\Analysis\ProjectZomboid\ServerExceptionProblem;
```

- [ ] **Step 4: Run all tests to verify pass**

```bash
docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest composer test
```

Expected: all tests PASS, count increased by 3.

- [ ] **Step 5: Commit**

```bash
git add src/Log/ProjectZomboid/ProjectZomboidServerLog.php test/tests/Games/ProjectZomboid/Analyser/ServerLogAnalysisTest.php
git commit -m "Wire ProjectZomboidServerLog default analyser"
```

---

## Done condition

After Task 5, `composer test` should report 158 tests, 309 assertions, all green:

- 146 baseline (from end of Phase A)
- +2 (Task 1)
- +3 (Task 2)
- +3 (Task 3)
- +4 (Task 4)
- +3 (Task 5 e2e)

If counts diverge from this projection, stop and investigate before claiming completion.

---

## Phase B.2 deferred

PvpDamageAnalyser and AdminAuditAnalyser ride into a separate spec + plan in a follow-up session. The empty `src/Analyser/ProjectZomboid/.gitkeep` placeholder stays untouched until those analysers land.
