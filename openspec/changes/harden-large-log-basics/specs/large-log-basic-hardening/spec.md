## ADDED Requirements

### Requirement: Clean install resolves local codex-pz
The iblogs Composer manifest and lock metadata SHALL resolve `indifferentketchup/codex-pz` from the sibling `../codex-pz` package path, not from the stale `/opt/ik-codex` path.

#### Scenario: Composer path points to sibling package
- **WHEN** a developer inspects iblogs dependency metadata
- **THEN** `iblogs/composer.json` and the codex-pz lock entry reference `../codex-pz`
- **AND** neither file references `/opt/ik-codex`

### Requirement: Undetectable logs render without analysis crashes
The log view SHALL render logs whose codex detection falls back to a non-analysable log type without dereferencing a null analysis object.

#### Scenario: Log analysis is unavailable
- **WHEN** `IndifferentKetchup\Iblogs\Log::getAnalysis()` returns null
- **THEN** page description generation returns a description without detected-problem text
- **AND** the frontend log template treats analysis information as an empty list

### Requirement: Analyse endpoint uses bounded filtered content
The `/1/analyse` API action SHALL apply the same upload filter pipeline to submitted log content before constructing the codex log response.

#### Scenario: Analyse request contains over-limit or sensitive content
- **WHEN** `/1/analyse` receives content accepted by `LogContentParser`
- **THEN** `Filter::filterAll()` is applied before `Log::setContent()`
- **AND** the response is based on the filtered content rather than the raw request body

### Requirement: Worker request failures are isolated
The FrankenPHP worker SHALL isolate each handled request with a request-level exception boundary and SHALL reset per-request singletons in a deterministic place.

#### Scenario: Route dispatch throws
- **WHEN** API or frontend routing throws a `Throwable`
- **THEN** the worker emits an HTTP 500 response appropriate to the route type
- **AND** the request callback does not skip the reset logic for the next request
- **AND** the error is written to the PHP error log

### Requirement: Generated log IDs use cryptographic randomness
New log IDs SHALL be generated with PHP cryptographic integer randomness instead of `rand()`.

#### Scenario: New ID is generated
- **WHEN** `IndifferentKetchup\Iblogs\Id` creates a fresh ID
- **THEN** each character index is selected with `random_int()`
- **AND** production source no longer calls `rand()` for ID generation

### Requirement: Log page controls are semantic and usable
The log page SHALL expose scroll and error-jump controls as native buttons, SHALL keep the existing scroll behavior, SHALL let the error control jump to an error entry, and SHALL not block mobile pinch zoom.

#### Scenario: User activates log controls
- **WHEN** a user activates the line-count scroll control
- **THEN** the page scrolls to the bottom
- **WHEN** a user activates the footer scroll control
- **THEN** the page scrolls to the top
- **WHEN** a user activates the error-count control
- **THEN** the page scrolls to the first error entry if one exists
- **AND** all three controls are focusable native `button` elements

#### Scenario: User zooms on mobile
- **WHEN** the browser reads the viewport meta tag
- **THEN** the tag does not include `maximum-scale=1`

