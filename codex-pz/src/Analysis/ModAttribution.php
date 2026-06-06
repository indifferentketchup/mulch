<?php

namespace IndifferentKetchup\CodexPz\Analysis;

final readonly class ModAttribution implements \JsonSerializable
{
    public function __construct(
        public string $modName,
        public ?string $workshopId,
        public ?string $deepestModFrame,
        public AttributionConfidence $confidence,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'modName' => $this->modName,
            'workshopId' => $this->workshopId,
            'deepestModFrame' => $this->deepestModFrame,
            'confidence' => $this->confidence->value,
        ];
    }
}
