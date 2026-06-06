<?php

namespace IndifferentKetchup\CodexPz\Util;

interface RedactorInterface
{
    /**
     * Redact PII from the given content string and return the result.
     *
     * The method is stateless from the caller's perspective: the same instance
     * may be called repeatedly and each call operates independently on its
     * input. Configuration (which passes are enabled, replacement tokens, etc.)
     * is applied once via implementation-specific setters before the first call
     * to redact().
     *
     * @param string $content Raw log content that may contain PII.
     * @return string Content with PII replaced by redaction tokens.
     */
    public function redact(string $content): string;
}
