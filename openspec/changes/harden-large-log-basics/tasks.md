## 1. Dependency Seam

- [x] 1.1 Change `iblogs/composer.json` to use the sibling codex-pz path repository `../codex-pz`. Verify: `rg -n '\"url\": \"../codex-pz\"' iblogs/composer.json && ! rg -n '/opt/ik-codex' iblogs/composer.json`
- [ ] 1.2 Regenerate `iblogs/composer.lock` for `indifferentketchup/codex-pz` with Docker Composer. Verify: `cd iblogs && docker run --rm -v "$(pwd):/app" -w /app -u "$(id -u):$(id -g)" composer:latest update indifferentketchup/codex-pz --ignore-platform-req=ext-frankenphp --ignore-platform-req=ext-mongodb --ignore-platform-req=ext-uri`
- [ ] 1.3 Confirm stale codex path references are gone from Composer metadata. Verify: `! rg -n '/opt/ik-codex' iblogs/composer.json iblogs/composer.lock`

## 2. Log View Stability

- [ ] 2.1 Null-guard problem lookup in `Log::getPageDescription()` so null analysis behaves as an empty problem list. Verify: `rg -n 'getAnalysis\\(\\)\\?->getProblems' iblogs/src/Log.php && ! rg -n 'getAnalysis\\(\\)->getProblems' iblogs/src/Log.php`
- [ ] 2.2 Null-guard information lookup in `web/frontend/log.php` so null analysis behaves as an empty information list. Verify: `rg -n 'getAnalysis\\(\\)\\?->getInformation' iblogs/web/frontend/log.php && ! rg -n 'getAnalysis\\(\\)->getInformation' iblogs/web/frontend/log.php`

## 3. API And Worker Hardening

- [ ] 3.1 Apply `Filter::filterAll()` in `AnalyseLogAction` before `Log::setContent()`. Verify: `rg -n 'Filter::filterAll' iblogs/src/Api/Action/AnalyseLogAction.php && rg -n 'new Log\\(\\)->setContent\\(\\$content\\)' iblogs/src/Api/Action/AnalyseLogAction.php`
- [ ] 3.2 Add a `Throwable` boundary around route dispatch in `worker.php`, log the exception, emit a 500 response, and keep per-request reset in `finally`. Verify: `rg -n 'catch \\(Throwable|finally|error_log|http_response_code\\(500\\)' iblogs/worker.php`

## 4. ID Generation

- [ ] 4.1 Replace `rand()` with `random_int()` in `Id::generate()`. Verify: `rg -n 'random_int\\(' iblogs/src/Id.php && ! rg -n 'rand\\(' iblogs/src/Id.php`

## 5. Log Page Controls

- [ ] 5.1 Convert `#error-toggle`, `#down-button`, and `#up-button` from clickable `div`s to native `button type="button"` elements while preserving existing classes and content. Verify: `rg -n '<button type="button" class="btn[^"]*" id="(error-toggle|down-button|up-button)"' iblogs/web/frontend/log.php && ! rg -n '<div class="btn[^"]*" id="(error-toggle|down-button|up-button)"' iblogs/web/frontend/log.php`
- [ ] 5.2 Wire `#error-toggle` in `log.js` to scroll to the first `.entry-error` when present. Verify: `rg -n 'error-toggle|entry-error' iblogs/web/public/js/log.js`
- [ ] 5.3 Remove `maximum-scale=1` from the viewport meta tag. Verify: `! rg -n 'maximum-scale=1' iblogs/web/frontend/parts/head.php`

## 6. Plan Verification

- [ ] 6.1 Validate this OpenSpec change. Verify: `openspec change validate harden-large-log-basics --strict --no-interactive`
- [ ] 6.2 Show the final diff summary. Verify: `git diff --stat`
