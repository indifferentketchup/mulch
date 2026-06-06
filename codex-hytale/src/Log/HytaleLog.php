<?php

namespace Aternos\Codex\Hytale\Log;

use Aternos\Codex\Detective\LinePatternDetector;
use Aternos\Codex\Hytale\Analysis\Information\HytaleVersionInformation;
use Aternos\Codex\Log\AnalysableLog;
use Aternos\Codex\Log\DetectableLogInterface;

abstract class HytaleLog extends AnalysableLog implements DetectableLogInterface
{

    public static function getPattern(string $contentPattern = ''): string
    {
        if ($contentPattern) {
            $contentPattern = '\s*' . $contentPattern;
        }
        return '/^' . static::$prefixPattern . $contentPattern . '.*$/';
    }

    public static function getDetectors(): array
    {
        return [
            new LinePatternDetector()->setPattern(static::getPattern()),
        ];
    }

    /**
     * @return string|null
     */
    public function getVersion(): ?string
    {
        /** @var HytaleVersionInformation[] $insights */
        $insights = $this->analyse()->getFilteredInsights(HytaleVersionInformation::class);
        if (count($insights) === 0) {
            return null;
        }
        return $insights[0]->getValue();
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return "Hytale " . $this->getTypeName() . " Log";
    }

    public function jsonSerialize(): array
    {
        return array_merge([
            'id' => "hytale/" . strtolower($this->getTypeName()),
            'name' => "Hytale",
            'type' => $this->getTypeName() . " Log",
            'version' => $this->getVersion(),
            'title' => $this->getTitle()
        ], parent::jsonSerialize());
    }

    protected abstract function getTypeName(): string;
}
