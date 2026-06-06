# Security Analysis: PZ error-pipeline epic plan + iblogs log.php template extension

## Scope

**Branch:** `pz-enrichment-bootstrap` (codex), `pz-enrichment-iblogs-bootstrap` (iblogs).

**Primary artefact under review:**
- `/home/samkintop/opt/ik-codex/docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md`

**First-party code paths the plan extends or touches:**
- `/home/samkintop/opt/ik-codex/src/Util/ProjectZomboid/ProjectZomboidRedactor.php` (architectural-analysis SEC-001 source — *not* modified by this plan)
- `/home/samkintop/opt/ik-codex/src/Util/ProjectZomboid/ProjectZomboidModAttributor.php` (existing `MOD_NAME_TO_WORKSHOP_ID` map + escaping behaviour)
- `/home/samkintop/opt/ik-codex/src/Analysis/Insight.php` (the `jsonSerialize()` extension in plan §1.6)
- `/home/samkintop/opt/ik-codex/src/Analyser/ProjectZomboid/ConnectionFailureAnalyser.php` (existing PII-bearing custom analyser; carries Steam ID + player name into `getMessage()`)
- `/home/samkintop/opt/ik-codex/src/Analysis/ProjectZomboid/ConnectionFailureProblem.php` (`getMessage()` embeds Steam ID + player name verbatim — line 53–61)
- `/home/samkintop/opt/ik-codex/src/Pattern/ProjectZomboid/DebugServerPattern.php` (plan §1.1 extends LINE constants)
- `/opt/iblogs/src/Filter/ProjectZomboidRedactorFilter.php` (save-time PII boundary)
- `/opt/iblogs/src/Log.php` (`save()` invokes `Filter::filterAll()` at persistence; line 354)
- `/opt/iblogs/src/Api/Response/LogResponse.php` (`/1/insights/<id>` JSON surface; `withInsights` flag at line 28, 53)
- `/opt/iblogs/src/Api/Response/CodexLogResponse.php` (delegates to codex `LogInterface::jsonSerialize()`)
- `/opt/iblogs/src/Api/Action/LogInsightsAction.php` (the route handler returning analysis JSON; line 18–29)
- `/opt/iblogs/web/frontend/log.php` (the template the plan extends in §2.2)

**Dependency manifests inspected:**
- `/home/samkintop/opt/ik-codex/composer.json` + `composer.lock` (runtime: php >=8.4 only; dev: phpunit ^12 → resolved 12.5.6)
- `/opt/iblogs/composer.json` + `composer.lock` (manifest: codex `^0.5.0`; **lock resolved: codex v0.3.0**; mongodb/mongodb 2.1.2, psr/log 3.0.2, symfony/polyfill-php85 v1.37.0)

**Antecedent context:** Architectural analysis at `docs/superpowers/research/2026-06-05-pz-analyser-architecture-analysis.md` previously catalogued SEC-001 through SEC-008 (numbering local to that document). The epic plan explicitly defers SEC-001 / SEC-002 / SEC-004 from the antecedent doc to a parallel hotfix track. The findings in *this* document re-number from SEC-001 within its own scope, and reference the antecedent IDs by full path where needed.

## Summary

The plan is, in absolute terms, conservative on the active attack surface it introduces: `workshopId` is sourced from a maintainer-controlled static map rather than log content, mod names are `htmlspecialchars`-escaped in both the codex enricher and the proposed iblogs template, and the `<pre>` rendering of cause chains goes through `htmlspecialchars()`. However, the plan's §1.6 `Insight::jsonSerialize()` extension and §1.4 `StackTraceClassificationAnalyser` open new JSON-API and HTML-template channels that re-surface the antecedent SEC-001 PII leak through *additional* fields, and the plan's deferral language for that fix is non-binding ("Should be hotfixed before…") rather than a release gate encoded in the acceptance criteria. The cross-repo deploy sequence in §"Phase orchestration" is a window in which iblogs would reference codex v0.6.0 interfaces while the iblogs `composer.lock` may still resolve to v0.3.0 (the drift exists *today*, not hypothetically).

| Severity | Count |
|----------|-------|
| Critical | 0     |
| High     | 1     |
| Medium   | 4     |

Full analysis written to: /home/samkintop/opt/ik-codex/docs/security-analysis.md

## Findings

### OWASP Top 10 Sweep

> **A01 — Broken Access Control:** No proven vulnerability found. Checked: the plan adds no new endpoints, no new auth surface; `LogInsightsAction.php:18–29` reads only by `Id` (random short-string), the token-based delete authority is unchanged. The plan's new analyser data flows through pre-existing public read endpoints whose access model is "log id = capability".

**SEC-001: Plan §1.6 `Insight::jsonSerialize()` + §1.4 `StackTraceClassificationAnalyser` amplify pre-existing redactor-bypass PII leak through new JSON-API and template channels**

- **OWASP:** A02 — Cryptographic Failures (PII exposure / data confidentiality)
- **Location:** Plan `docs/superpowers/plans/2026-06-06-pz-error-pipeline-epic.md:258–281` (proposed `Insight::jsonSerialize()`); plan lines 213–229 (`StackTraceClassificationAnalyser` Phase 5 `extractCauseChain` + `setCauseChain` on `LuaModRuntimeProblem`); plan template lines 447–455 (`<pre><?= htmlspecialchars($stack); ?></pre>`); upstream PII channel pre-existing at `src/Analysis/ProjectZomboid/ConnectionFailureProblem.php:53–61` and `src/Analyser/ProjectZomboid/ConnectionFailureAnalyser.php:34–47`; iblogs surface at `/opt/iblogs/src/Api/Action/LogInsightsAction.php:18–29` and `/opt/iblogs/src/Api/Response/LogResponse.php:53–54`.
- **Evidence:**
  ```php
  // src/Analysis/ProjectZomboid/ConnectionFailureProblem.php:53-61 — already-existing channel
  public function getMessage(): string
  {
      return sprintf(
          'Player %s (%s) had %d "attempting to join" event(s) without a matching "allowed to join".',
          $this->player,    // raw player name from log
          $this->steamId,   // raw Steam ID — 76561197/9 universe bypasses ProjectZomboidRedactor::STEAM_ID_REGEX
          $this->unmatchedAttempts
      );
  }
  ```
  Plan §1.6 proposes:
  ```php
  public function jsonSerialize(): array
  {
      $base = [
          'message' => $this->getMessage(),  // PII channel today
          'counter' => $this->getCounterValue(),
          'fingerprint' => $this->getFingerprint(),
      ];
      if ($this->entry !== null) {
          $base['entry'] = $this->entry;      // NEW conditional — entry was always nulled to null for custom analysers per B5/R2
      }
      // ... severity, mod, engine_noise additions
  }
  ```
  And plan §1.4 introduces `LuaModRuntimeProblem` with `setCauseChain($this->extractCauseChain($body))` — a `Phase 5` port of `tools/pz-analyzer/pz_parser.py` that copies raw `Caused by:` body text verbatim.

- **EXPLOIT:** (1) A doxxing-motivated player uploads any production PZ DebugLog containing a 76561199xxx Steam ID adjacent to player text — e.g. a stack trace caused by `IsoGameCharacter$XP.AddXP` on a named player. The user simply pastes their own log; no protocol-level crafting needed. (2) iblogs `Log::save()` at `/opt/iblogs/src/Log.php:354` calls `Filter::filterAll()`, which invokes `ProjectZomboidRedactor`. The regex at `src/Util/ProjectZomboid/ProjectZomboidRedactor.php:60` is `/76561198\d{9}/`, missing universes 76561197 and 76561199. The architectural-analysis measurement was 199 of 433 production Steam IDs missed (~46%). (3) `PLAYER_AFTER_STEAMID_REGEX` at line 69 of the same file is anchored on the redacted-placeholder text — when the Steam ID isn't redacted, the player name attached to it isn't either. (4) The plan's new `StackTraceClassificationAnalyser::extractCauseChain()` (plan §1.4, lines 213) captures the still-PII-bearing body text. (5) Persisted to MongoDB by `Log::save()`. (6) `LogInsightsAction` at `/opt/iblogs/src/Api/Action/LogInsightsAction.php:18-29` returns JSON at `https://bosslogs.indifferentketchup.com/1/insights/<id>` containing the new `problems[].mod`, `problems[].entry`, `problems[].causeChain` fields — each potentially carrying the leaked Steam ID and player name. (7) The HTML page at plan §2.2 line 453 (`<pre><?= htmlspecialchars($stack); ?></pre>`) renders the leak; `htmlspecialchars` HTML-encodes but does not scrub PII — Steam ID and player name appear in plaintext on every public log view. **The plan's §1.6 multiplies the JSON-field count by which this leak is reachable, and the new `LuaModRuntimeProblem` stack-trace rendering is a new HTML surface for it that did not exist before.** The "What we deferred" text at lines 717–719 ("Should be hotfixed before this epic ships to production… because the existing leak is critical") is aspirational prose — not a release gate, not a CI assertion, not encoded in the acceptance criteria (lines 691–704). The plan ships these new surfaces without depending on the antecedent SEC-001 fix landing first. By the acceptance criteria as written, v0.6.0 can be tagged and iblogs deployed with the surfaces live and the upstream bug still present.
- **Severity:** High

> **A03 — Injection:** No proven vulnerability found. Checked: (a) plan §2.2 template at lines 430 and 436 renders `<?= htmlspecialchars($mod->modName); ?>` — codex's `ProjectZomboidModAttributor::decorate()` at `src/Util/ProjectZomboid/ProjectZomboidModAttributor.php:91–104` already applies `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`; the template applies it again, double-safe. (b) `workshopId` rendered into `href` at line 426 is sourced from `MOD_NAME_TO_WORKSHOP_ID` — a static map with four numeric string values (`'3616176188'`, `'2857548524'`, `'2849467715'`, `'3118159023'`, all maintainer-controlled). The plan does not introduce a code path that lets log content populate `workshopId`; the Python prototype's `attribute_entry()` at `tools/pz-analyzer/pz_parser.py:410-481` returns `(mod_id, mod_name, attribution, confidence, reason)` with no log-derived Workshop ID. (c) `<pre><?= htmlspecialchars($stack); ?></pre>` at line 453 encodes the raw cause chain before insertion — `htmlspecialchars` defaults to `ENT_QUOTES | ENT_SUBSTITUTE` in PHP 8.4, sufficient for body content not in attribute context. (d) The existing `preg_replace("/'([^']+)'/", "'<strong>$1</strong>'", htmlspecialchars($solution->getMessage()))` at `/opt/iblogs/web/frontend/log.php:123` is preserved by the plan; the regex's `$1` capture runs over already-`htmlspecialchars`-escaped text, so `<` / `>` / `&` cannot reach the `<strong>` injection point. No SQL surface — codex is parsing only; MongoDB writes in `Log.php:356-364` use bound BSON. **The architectural-analysis already verified `MOD_TOKEN_REGEX` is ReDoS-safe (<5ms on 100k chars).**

**SEC-002: `causeChain` field surfaces raw log bytes through the JSON API without ANSI / control-character normalisation**

- **OWASP:** A03 — Injection (terminal control-character pass-through to CLI consumers)
- **Location:** Plan §1.4 line 213 (`setCauseChain($this->extractCauseChain($body))`); plan §1.6 lines 258–281 (`jsonSerialize()` will surface this via `mod` and/or a new `causeChain` JSON key when `LuaModRuntimeProblem` is serialised); plan §2.2 line 453 (`<pre><?= htmlspecialchars($stack); ?></pre>`).
- **Evidence:** The plan does not specify normalisation in `StackTraceClassificationAnalyser::extractCauseChain()`. The Python prototype it ports extracts raw `Caused by:` body text. Project Zomboid Java exceptions are normally ASCII, but the upload endpoint (`Log::create()` at `/opt/iblogs/src/Log.php:97–104` → `setContent()` at line 204) does not validate MIME / character set. The plan's `<pre><?= htmlspecialchars($stack); ?></pre>` strips HTML metacharacters only; ANSI escapes (`\x1b[...`) and other control bytes pass through unchanged. The same `htmlspecialchars` does not normalise the JSON output either — `json_encode()` will escape `\x1b` to `` in default config, but if `JSON_UNESCAPED_UNICODE` is enabled (which iblogs uses for log-content fields elsewhere), raw escapes survive.
- **EXPLOIT:** A hostile uploader synthesises a PZ-shaped log whose stack-trace body contains ANSI sequences such as `\x1b[2J\x1b[H` (clear screen + home cursor) or `\x1b]0;Hijacked\x07` (set terminal title). Uploads via the public paste endpoint. (2) The bytes survive the redactor (not in any pattern) and reach `StackTraceClassificationAnalyser::extractCauseChain()`. (3) Persisted to MongoDB. (4) A CLI consumer of the API runs `curl https://bosslogs.indifferentketchup.com/1/insights/<id> | jq -r '.problems[].causeChain'` — the raw escape sequences reach the terminal and execute. At minimum: terminal title manipulation, scrollback clearance, color-state corruption. The HTML page surface is correctly defended by `<pre>` + `htmlspecialchars`. **Severity Medium because the HTML rendering path is safe and the exploit's impact is limited to CLI consumers (cosmetic terminal manipulation, no RCE).** This is a regression from "no field carries raw log bytes through `/1/insights/`" to "one field does".
- **Severity:** Medium

> **A04 — Insecure Design:** No proven vulnerability found. Checked: plan §1.4 line 194 establishes `HIT_CAP = 500` on `StackTraceClassificationAnalyser::analyse()`, preventing unbounded insight growth from a 100k-line log; the 100k bench test in §1.7 (lines 286–296) asserts the parse+analyse pipeline completes in <2 seconds. No new rate-limit / resource-bound gaps. The `getProblems()` array is fully materialised in PHP memory but with a 500-entry cap × few-KB per insight the upper bound is bounded.

> **A05 — Security Misconfiguration:** No proven vulnerability found. Checked: plan introduces no debug switches, no environment-conditional code paths, no new exception-to-response shapes. The exception posture of the analyser is `iterator_to_array($this->log)` followed by `foreach` — no try/catch added that would suppress security-relevant errors. Plan §2.1 `Setting::HIDE_ENGINE_NOISE` default is `true` (line 333), a CSS-only filter — not a security boundary.

**SEC-003: Plan phase orchestration creates a tag-vs-lock window during cross-repo deploy**

- **OWASP:** A06 — Vulnerable and Outdated Components (deployment coordination)
- **Location:** Plan §"Phase orchestration via paseo" lines 670–676; cross-repo rule documented at `/home/samkintop/opt/ik-codex/CLAUDE.md` (Cross-repo sync rule section).
- **Evidence:** The plan's phase table:
  - Phase 2: "Codex release — Update CHANGELOG.md, cut v0.6.0 tag (no push)"
  - Phase 3: "iblogs frontend — Bump composer constraint to ^0.6.0"
  - Phase 4: "Cross-repo push — User reviews both branches; pushes both `--no-ff` merges in one operation"

  Between phase 2 and phase 4 the v0.6.0 tag exists locally only. The phase 3 instruction says "Bump composer constraint to ^0.6.0" but does not call out the corresponding `composer update indifferentketchup/codex` step that regenerates the iblogs `composer.lock` against the now-required tag. The existing `composer.lock` in iblogs (verified by grep) carries `"indifferentketchup/codex" "version": "v0.3.0"` while the existing `composer.json` requires `^0.5.0` — this drift is a *current* operational mode, not a hypothetical.
- **EXPLOIT:** Not a runtime exploit — an operational risk that becomes data-integrity adjacent. During phase 3, the iblogs agent in its worktree runs `composer require indifferentketchup/codex ^0.6.0`. Composer fails to resolve because the v0.6.0 tag is not yet on the remote (CLAUDE.md "never tag codex with breaking changes unless the matching iblogs adjustment is ready to push"). The agent may then proceed by editing `composer.json` without running `composer update`, leaving the lock at v0.3.0 — and the iblogs frontend code references v0.6.0 interface names (`SeverityAwareInsightInterface`, `ModAttributedInsightInterface`, `EngineNoiseInsightInterface`) that do not exist in the resolved v0.3.0 codex. At deploy, FrankenPHP's autoloader returns class-not-found errors on every page render through the new `instanceof` checks. Worst case is HTTP 500 on every log view — a data-availability incident, not a data-confidentiality breach. The narrower failure mode: if the iblogs agent runs `composer update --no-scripts`, succeeds against the missing tag (silently falling back to v0.5.0), and the lock pins v0.5.0 — then plan §1.6's conditional `entry: null` guard (which is part of the v0.6.0 work) is absent, and the JSON API surfaces `entry: null` for custom-analyser Insights (the B5/R2 defect the architectural analysis identified).
- **Severity:** Medium

> **A07 — Identification and Authentication Failures:** No proven vulnerability found. Checked: codex has no auth surface (parsing library only); iblogs token + delete flow at `Log.php:476–484` (`hasValidTokenCookie`) is unchanged by this plan. The plan does not introduce any new comparison of secrets / hashes; the new `getFingerprint()` is a 64-bit-truncated sha256 over `exceptionClass + first3StackFrames + modId`, not a security-bearing comparison. No timing-side-channel surface introduced.

> **A08 — Software and Data Integrity Failures:** No proven vulnerability found. Checked: plan introduces no deserialisation. MongoDB BSON round-trips are existing surface. The sha256 fingerprint truncation in §1.5 (64-bit → 16 hex chars) is collision-resistant against the documented corpus size — the v2 scan has 4,734 unique signatures, well below the ~4.3B birthday-collision boundary for 64 bits; no exploit demonstrable against a maintainer adding 50–100 new Insight classes per epic.

**SEC-004: Plan acceptance criteria do not include a "no PII in new fields" regression assertion**

- **OWASP:** A09 — Security Logging and Monitoring Failures (missing detection control)
- **Location:** Plan §"Acceptance criteria" lines 691–704.
- **Evidence:** The acceptance criteria assert parse performance (`<2 seconds`), insight presence (`getProblems()` returns typed instances for the 15 new classes), template-rendering shape, version-bump constraints. **None of the criteria assert that the new `mod`, `severity`, `engine_noise`, `fingerprint`, `causeChain` JSON-output fields contain no Steam IDs, no player names, no IP addresses for a fixture containing a redactor-missed identifier.** The plan's §1.7 bench test at lines 287–296 asserts entry counts and parse time only, with no PII-fixture roundtrip. The Redactor is not invoked in the bench fixture, and the bench output is not checked for residual PII.
- **EXPLOIT:** Not a runtime exploit; this is a missing detection control. Without a regression assertion like "for fixture `pii-leak-roundtrip-minimal.txt` containing `76561199000000099 "Player1"`, the JSON response of `/1/insights/<id>` MUST NOT contain `/76561199\d{9}/`", the SEC-001 amplification in this analysis is invisible to CI. A subsequent PR that adds another field surfacing log content will repeat the bug class undetected. The plan's CI loop runs `composer test` after each batch (per CLAUDE.md workflow), but the test surface does not cover the cross-boundary case.
- **Severity:** Medium

> **A09 — Security Logging and Monitoring Failures:** Covered by SEC-004 above.

> **A10 — Server-Side Request Forgery:** No proven vulnerability found. Checked: the new `<a href="https://steamcommunity.com/sharedfiles/filedetails/?id=...">` link in plan §2.2 lines 425–432 has `target="_blank" rel="noopener"`. The `id` parameter is sourced from the static `MOD_NAME_TO_WORKSHOP_ID` map in `src/Util/ProjectZomboid/ProjectZomboidModAttributor.php:44–49` — maintainer-controlled numeric strings, not log content. The plan does not introduce any server-side HTTP request based on log content. iblogs's `Log::find()` reads MongoDB by random short Id — no user-controllable URL on the server side. The architectural analysis already noted the existing CodexLogResponse path has no outbound HTTP surface.

### Attack-Angle Protocols

> **Protocol 1 (Input-to-Sink Tracing):** Traced. User input = uploaded log content. Sinks reached by the plan's additions:
> - DB query sink: parameterized BSON insert at `Log.php:356-364` — safe.
> - Shell command sink: none introduced.
> - Template rendering sinks: `<?= htmlspecialchars($mod->modName); ?>` (plan lines 430, 436), `<?= htmlspecialchars($mod->workshopId); ?>` (line 426), `<?= htmlspecialchars($stack); ?>` (line 453). All run through `htmlspecialchars`; `workshopId` additionally sourced from static map. The XSS surface is defended. **The semantic leak (Steam IDs / player names in plaintext) is captured under SEC-001 — it is a PII / confidentiality leak, not an HTML-injection leak.** The CLI-terminal-control surface is captured under SEC-002.
> - HTTP redirect sink: none.
> - Filesystem sink: none in the plan's additions.

> **Protocol 2 (Auth/Authz Decision Audit):** Reviewed. No new auth decisions in the plan. The pre-existing `Log` token check at `Log.php:476-484` is unchanged. The plan does not gate any new endpoint behind authorization (the new template additions are public per the existing model — every log is public by ID, treated as a capability URL).

> **Protocol 3 (Secret / PII Pattern Search):** Searched. No hardcoded secrets / API keys / tokens introduced by the plan. PII pattern findings:
> - `MOD_NAME_TO_WORKSHOP_ID` map values are public Steam Workshop IDs — not secrets.
> - The plan introduces no new credential storage. Cross-repo deploy uses pre-existing git transport.
> - PII channel widening through new analyser → JSON / template fields is captured under SEC-001.

**SEC-005: iblogs `composer.lock` carries codex v0.3.0 — two minor versions behind the manifest constraint `^0.5.0`**

- **OWASP:** A06 — Vulnerable and Outdated Components
- **Location:** `/opt/iblogs/composer.lock` entry for `indifferentketchup/codex` (confirmed by grep: `"name": "indifferentketchup/codex"`, `"version": "v0.3.0"`); `/opt/iblogs/composer.json:24` (`"indifferentketchup/codex": "^0.5.0"`).
- **Evidence:** The two files disagree by two minor versions. `composer.json` declares the floor as `^0.5.0` but the lock pins v0.3.0. The antecedent architectural analysis identified its SEC-001 (production Steam ID universe gap) in the codex Redactor — if v0.4.0 or v0.5.0 carried *any* partial fix or hardening to the Redactor, the production lock has frozen it out. Production `composer install --no-dev` (FrankenPHP container build) installs whatever the lock pins, not the manifest floor.
- **EXPLOIT:** Not a public-CVE referent (the codex package is private to this maintainer; no published advisory). The exploit is the existence of the drift itself: production runs against a version older than the manifest floor, and any security work landed in v0.4.0 / v0.5.0 is silently absent from the deployed binary. Concretely: the architectural-analysis SEC-002 (PvP victim name + coords leak) and SEC-004 (toggle-interaction silent player-name leak) were both identified against the current codex state; if either was partially addressed in v0.4.0 or v0.5.0 (the maintainer's prior releases), the deployed iblogs at `bosslogs.indifferentketchup.com` still leaks because the lock pins v0.3.0. The plan's phase 3 instruction "Bump composer constraint to ^0.6.0" (line 674) does not include the corresponding `composer update indifferentketchup/codex --with-all-dependencies` that would regenerate the lock against the new tag. The CLAUDE.md cross-repo rule says branches must be pushed together; the plan does not extend that to "and the lock must be regenerated against the pushed tag in the same operation".
- **Severity:** Medium

> **Protocol 4 (Dependency Vulnerability Check):** Performed against both composer.lock files. Codex dev-only deps in `composer.lock`: `phpunit/phpunit 12.5.6`, `nikic/php-parser v5.7.0`, `myclabs/deep-copy 1.13.4`, `sebastian/comparator 7.1.3`, `phar-io/manifest 2.0.4`, `phar-io/version 3.2.1` — all current as of the knowledge cutoff; no known unpatched CVEs at these versions. Codex runtime: PHP `>=8.4` only — no third-party runtime deps to audit. Iblogs runtime deps: `mongodb/mongodb 2.1.2` (current major; no known CVE at this version), `psr/log 3.0.2` (interface-only, no executable surface), `symfony/polyfill-php85 v1.37.0` (current). The cross-repo pin divergence is captured under SEC-005.

## Security Improvement Summary

### What Was Found

Five proven findings raised. Severity distribution: **1 High, 4 Medium**.

- **SEC-001 (High):** The plan's §1.6 `Insight::jsonSerialize()` extension and §1.4 `StackTraceClassificationAnalyser` together introduce new JSON-API fields (`mod`, `causeChain`, `entry` re-enabled for custom-analyser problems) and a new HTML rendering surface (`<pre>` cause-chain block) that propagate the underlying antecedent SEC-001 PII leak (Steam ID universe gap + chained player-name bypass) into channels where it was not present before. The plan ships these surfaces without depending — in the acceptance criteria — on the Redactor fix landing first; the deferral language is aspirational rather than gating.
- **SEC-002 (Medium):** The new `causeChain` field passes raw log bytes through to the JSON API at `/1/insights/<id>` without ANSI / control-character normalisation; the HTML rendering path is correctly defended by `<pre>` + `htmlspecialchars`, but CLI consumers of the JSON output are exposed to terminal-control manipulation. New surface introduced by the plan.
- **SEC-003 (Medium):** The plan's phase orchestration creates a window between codex v0.6.0 tag creation (phase 2, local) and the cross-repo push (phase 4) during which the iblogs frontend agent in phase 3 is asked to bump the constraint without an explicit `composer update` step encoded; combined with the existing lock drift documented in SEC-005, the deploy can land iblogs code that references v0.6.0 interface names while the resolved codex version is older.
- **SEC-004 (Medium):** Plan acceptance criteria (lines 691–704) do not include a regression assertion that the new JSON-API fields are PII-free for a fixture containing a redactor-missed identifier; the SEC-001 amplification is therefore invisible to the CI loop.
- **SEC-005 (Medium):** iblogs `composer.lock` currently pins codex `v0.3.0` despite the manifest requiring `^0.5.0`, demonstrating that lock drift is a real operational mode for this repo pair — any security work in v0.4.0 or v0.5.0 is silently absent from the deployed FrankenPHP runtime.

### How to Improve

1. **Address SEC-001 by re-shaping the deferral language into a release gate.** Add to plan §"Acceptance criteria" a top-row constraint: "v0.6.0 MUST NOT be tagged until `STEAM_ID_REGEX` covers universes 76561197, 76561198, and 76561199, and `PLAYER_AFTER_STEAMID_REGEX` is verified by a fixture in `test/src/Games/ProjectZomboid/fixtures/pii-roundtrip-multi-universe-minimal.txt`. The pipeline `parse() → analyse() → jsonSerialize() → json_encode()` MUST NOT emit any digit sequence matching `/76561197\d{9}|76561198\d{9}|76561199\d{9}/` for that fixture." Sequence the PR for the Redactor fix before any of the new Insight / Analyser PRs.
2. **Address SEC-002 by normalising control bytes at the analyser boundary, not the renderer.** In `StackTraceClassificationAnalyser::extractCauseChain()` apply `preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body)` (preserves `\x09` tab, `\x0A` LF, `\x0D` CR for stack-trace formatting). Document the choice in the analyser docblock. Apply the same to any other field carrying raw log content (`extractFileLine`, `deepestModFrame`).
3. **Address SEC-003 by adding an explicit phase row.** Add to the orchestration table between phase 2 and phase 3: "Phase 2.5 — Push v0.6.0 annotated tag to codex remote; verify tag is reachable from iblogs git transport before allowing phase 3 to start." And expand phase 3: "Run `composer update indifferentketchup/codex --with-dependencies` inside the iblogs worktree; commit the regenerated `composer.lock` in the same commit as the `composer.json` constraint bump." Reference CLAUDE.md's cross-repo rule inline.
4. **Address SEC-004 by adding a Phase 1 acceptance test.** Build a synthetic fixture covering all three Steam ID universes, named players, and a `Lua((MOD:...))` stack trace. Run the full pipeline and `assertNotMatchesRegularExpression('/76561(?:197|198|199)\d{9}/', json_encode($insight->jsonSerialize()))`; same for known player names. Make this test gate Phase 1.6 (the `jsonSerialize` extension).
5. **Address SEC-005 by adding a one-line CI guard in iblogs.** Parse `composer.json` and `composer.lock`, assert that the resolved version of `indifferentketchup/codex` is `>=` the constraint's lower bound: `php -r '$lock = json_decode(file_get_contents("composer.lock"), true); $man = json_decode(file_get_contents("composer.json"), true); /* lookup + version_compare */'`. Fail the build on drift. This generalises beyond the codex package to any future first-party dependency.

### How to Prevent This Going Forward

1. **Add a `test/tests/Security/PIIRoundTripTest.php` in codex** that ingests a synthetic fixture covering all three Steam ID universes (76561197/8/9) plus quoted player names in each PZ log shape, runs the canonical Redactor + each Insight class's `jsonSerialize()`, and asserts no recognizable PII pattern survives in the JSON output. Run on every PR. This catches future Insight-class additions that inadvertently surface raw entry content (the same class of finding as SEC-001 in this report).
2. **Add a `bin/composer-lock-drift-check.php` CI step in iblogs** that fails the build when the lock's resolved version of any first-party dependency is older than the constraint's lower bound. Generalises SEC-005's specific instance.
3. **Document a "PII boundary" annotation convention on `Insight` subclasses.** Any `Insight` subclass whose `getMessage()` or other accessor returns raw log content should declare a `#[PIIBearing]` PHP attribute. `Insight::jsonSerialize()` would then refuse to serialise attributed classes unless the consumer opts in (parallel to the existing `setIncludeEntries(false)` flag at `/opt/iblogs/src/Api/Action/LogInsightsAction.php:27`). Extending that pattern preserves least-privilege at the JSON surface.
4. **Treat raw log bytes as untrusted at the analyser boundary, not at the renderer.** Apply control-character stripping (SEC-002 fix) in the `Analyser` subclass so all downstream consumers — HTML template, JSON API, CLI export, future search index — get sanitised content uniformly. Don't rely on each consumer reinventing the defence.
5. **Encode the cross-repo deploy sequence as a Makefile target or a paseo orchestration script** rather than prose in a plan. The CLAUDE.md rule "must be pushed together" is honoured per-incident today; mechanising it removes the gap between intent and execution, and makes the operational invariant audit-friendly.
