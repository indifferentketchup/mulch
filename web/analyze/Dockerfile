FROM composer:latest AS base

COPY codex-pz /app/codex-pz
RUN cd /app/codex-pz && composer install --no-dev --no-interaction --prefer-dist --ignore-platform-req=ext-mongodb

COPY web/analyze/router.php /app/router.php
COPY web/analyze/analyze.php /app/analyze.php
COPY web/analyze/redact.php /app/redact.php

EXPOSE 8080
CMD ["php", "-d", "post_max_size=50M", "-d", "upload_max_filesize=50M", "-S", "0.0.0.0:8080", "/app/router.php"]
