# IndifferentKetchup Codex

Generic PHP log parsing and analysis framework. Reads a log file, detects which log type it is, parses entries (including multi-line records like Java stack traces), runs the type-specific analysers, and returns structured `Information` and `Problem` insights with attached `Solution`s where applicable.

Originally a fork of [`aternos/codex`](https://github.com/aternosorg/codex); the framework is intentionally game-agnostic. The reference implementation in this tree is Project Zomboid server logs.

## Install

```
composer require indifferentketchup/codex-pz
```

Requires PHP `>=8.4`. No third-party runtime dependencies.

## Quick start

Given a Project Zomboid `DebugLog-server.txt`:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use IndifferentKetchup\CodexPz\Detective\ProjectZomboid\ProjectZomboidDetective;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;

$detective = new ProjectZomboidDetective();
$detective->setLogFile(new PathLogFile('2026-04-30_14-00_DebugLog-server.txt'));

$log = $detective->detect();
$log->parse();
$analysis = $log->analyse();

echo $log->getTitle(), "\n\n";

foreach ($analysis->getInformation() as $info) {
    echo "[INFO] ", $info->getMessage(), "\n";
}

foreach ($analysis->getProblems() as $problem) {
    echo "[PROBLEM] ", $problem->getMessage(), "\n";
    foreach ($problem->getSolutions() as $solution) {
        echo "          -> ", $solution->getMessage(), "\n";
    }
}
```

For a session with mod issues and a server-side exception, output looks roughly like:

```
Project Zomboid Debug Server Log

[INFO] Engine version: 42.16.3 (build <hash>, <build date>)
[INFO] Mod loaded: <mod_id>
[INFO] Mod loaded: <other_mod_id>
[PROBLEM] Required mod "<missing>" not found.
          -> Subscribe to mod "<missing>" or remove its ID from the Mods= line in serverconfig.ini.
[PROBLEM] Exception thrown: java.nio.file.NoSuchFileException
```

If the log content arrives without a filesystem path (clipboard paste, web upload, stream), use `StringLogFile` or `StreamLogFile` instead of `PathLogFile`. The detective falls back to content signatures when the filename hint is absent.

## Redaction

Before rendering or exporting log content, pass it through `ProjectZomboidRedactor` to strip PII:

```php
use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;

$redactor = new ProjectZomboidRedactor();
$safe = $redactor->redact($logContent);
```

This scrubs three categories in a fixed pass order: Steam IDs are replaced with a zeroed placeholder, player names with `<player>`, and world coordinates with `0,0,0`. All three passes are on by default; opt out per category with `redactSteamIds(bool)`, `redactPlayerNames(bool)`, or `redactCoordinates(bool)`.

Documented v1 limitations: in PvP combat lines, only the attacker's name and coords are redacted — the victim's name and coords (appearing after `hit`) are deferred to v2. In admin lines, `teleported X to <coords>` coordinates are not redacted in v1.

## Architecture

```
LogFile → Log → parse() → Entry[] of Line[] → analyse() → Analysis of Insight[]
                                                          └── Information | Problem(+Solutions)
```

- **`Detective`** ranks candidate `Log` subclasses by running each candidate's static `getDetectors()` and picking the highest-scoring result. Each game ships its own `<Game>Detective` that pre-registers its log classes.
- **`PatternParser`** is regex-driven; lines that don't match the entry-start regex append to the previous `Entry`, which is how multi-line records (Java stack traces, indented warnings) are kept intact.
- **Analysers** come in two flavours: configured `PatternAnalyser` instances for per-entry pattern matching, and custom subclasses of `Analyser` for cross-entry logic (pairing events, sliding-window thresholds, snapshot comparisons).
- **Insights** are either `Information` (label + value) or `Problem` (with attached `Solution`s). Equal insights coalesce via a counter, so repeated patterns don't produce duplicate output.

Patterns live as plain `string` constants under `src/Pattern/<Game>/` — there is no `PatternInterface`. Each game adds files under `src/<Component>/<Game>/` (components-outer, game-suffixed). Full extension guide and conventions in [`CLAUDE.md`](CLAUDE.md).

## Game support

| Game | State |
|---|---|
| Project Zomboid | Full: 11 log subclasses across all the file types a server emits; analysers covering engine version, mod loading, server exceptions, PvP combat, admin audit, connection failures, item duplication, skill progression anomalies |
| Minecraft | Stub only — `MinecraftDetective` skeleton, no log subclasses yet |
| Hytale | Stub only |
| Seven Days To Die | Stub only |

The framework itself is generic — adding a new game means writing the same shape of files Project Zomboid demonstrates, not modifying anything in `src/{Analyser,Analysis,Detective,Log,Parser,Printer,Pattern}/` outside the new game's subdirectory.

## Developing

`composer test` runs the suite. PHP and Composer are not required on the host — invocations wrap in the official `composer:latest` Docker image (PHP 8.5). See [`CLAUDE.md`](CLAUDE.md) for the wrapped command, file layout, and the workflow conventions used in this repo.

## Source

<https://git.indifferentketchup.com/indifferentketchup/ik-codex-pz>

## License

MIT — see [`LICENSE`](LICENSE).
