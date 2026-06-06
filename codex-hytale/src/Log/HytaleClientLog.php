<?php

namespace Aternos\Codex\Hytale\Log;

use Aternos\Codex\Analyser\AnalyserInterface;
use Aternos\Codex\Detective\SinglePatternDetector;
use Aternos\Codex\Hytale\Analyser\HytaleAnalyser;
use Aternos\Codex\Hytale\Analyser\HytaleClientAnalyser;
use Aternos\Codex\Parser\ParserInterface;
use Aternos\Codex\Parser\PatternParser;

class HytaleClientLog extends HytaleLog
{
    public static string $prefixPattern = '(((?:[0-9]{2,4}\-?){3}\s(?:[0-9]{2}:?){3})\.[0-9]{4}\|(\w+)\|)';
    protected static string $detectionPattern = 'HytaleClient.Application.Program|HytaleClient';

    public static function getDefaultParser(): ParserInterface
    {
        return new PatternParser()
            ->setPattern(static::getPattern())
            ->setMatches([PatternParser::PREFIX, PatternParser::TIME, PatternParser::LEVEL])
            ->setTimeFormat("Y-m-d H:i:s");
    }

    public static function getDefaultAnalyser(): AnalyserInterface
    {
        return new HytaleClientAnalyser();
    }

    public static function getDetectors(): array
    {
        return [
            new SinglePatternDetector()->setPattern(static::getPattern(static::$detectionPattern)),
            ...parent::getDetectors(),
        ];
    }

    protected function getTypeName(): string
    {
        return "Client";
    }
}
