<?php

namespace IndifferentKetchup\CodexPz\Analyser;

final readonly class NoiseGate implements \JsonSerializable
{
    public function __construct(
        public string $fingerprint,
        public int $occurrences,
        public string $reason,
        public string $kind,
        public string $sampleMessage,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'occurrences' => $this->occurrences,
            'reason' => $this->reason,
            'kind' => $this->kind,
            'sampleMessage' => $this->sampleMessage,
        ];
    }
}
