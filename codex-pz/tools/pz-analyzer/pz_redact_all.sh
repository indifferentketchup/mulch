#!/usr/bin/env bash
# One-shot PII redaction over the PZ DebugLog-server files extracted from
# /opt/ik-codex/Logs.zip. Produces /opt/ik-codex/.scratch/pz/Logs.redacted/
# (gitignored alongside the source). Single Docker invocation; the codex
# library's vendor/autoload.php is mounted read-write only because composer's
# image refuses world-readable mounts under -u UID:GID.
#
# Re-runnable: rewrites every output file. Add --refresh-cache semantics by
# rm -rf'ing the OUT directory first if you want.
set -euo pipefail

IN=/opt/ik-codex/.scratch/pz/Logs
OUT=/opt/ik-codex/.scratch/pz/Logs.redacted

if [ ! -d "$IN" ]; then
    echo "error: input directory $IN missing — extract Logs.zip first" >&2
    exit 1
fi

mkdir -p "$OUT"

docker run --rm \
    --entrypoint php \
    -v /opt/ik-codex:/app -w /app \
    -v "$IN":/in:ro -v "$OUT":/out \
    -u "$(id -u):$(id -g)" \
    composer:latest \
    -r '
        require "vendor/autoload.php";
        $r = new IndifferentKetchup\Codex\Util\ProjectZomboid\ProjectZomboidRedactor();
        $files = glob("/in/*DebugLog-server*.txt");
        foreach ($files as $f) {
            file_put_contents("/out/" . basename($f), $r->redact(file_get_contents($f)));
        }
        fprintf(STDERR, "redacted %d file(s)\n", count($files));
    '
