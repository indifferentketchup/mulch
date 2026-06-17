<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Analysis;

use IndifferentKetchup\CodexPz\Analysis\Analysis;
use IndifferentKetchup\CodexPz\Analysis\Attribution;
use IndifferentKetchup\CodexPz\Analysis\Kind;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\EngineNoiseExceptionInformation;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\JavaExceptionProblem;
use IndifferentKetchup\CodexPz\Analysis\ProjectZomboid\LuaModRuntimeProblem;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use PHPUnit\Framework\TestCase;

class JsonShapeTest extends TestCase
{
    public function testEngineNoiseInsightJsonHasKindAttributionRankGated(): void
    {
        $insight = (new EngineNoiseExceptionInformation())
            ->setExceptionClass('java.lang.RuntimeException')
            ->setSignature('runtime|test')
            ->setCauseChain('A -> B');
        $insight->setKind(Kind::EngineNoise);
        $insight->setAttribution(Attribution::Unattributed);
        $insight->setGated(true);

        $json = $insight->jsonSerialize();

        $this->assertArrayHasKey('kind', $json);
        $this->assertSame('engine_noise', $json['kind']);
        $this->assertArrayHasKey('attribution', $json);
        $this->assertSame('unattributed', $json['attribution']);
        $this->assertArrayHasKey('rank', $json);
        $this->assertIsInt($json['rank']);
        $this->assertArrayHasKey('gated', $json);
        $this->assertTrue($json['gated']);
    }

    public function testLuaModInsightJsonHasKindAttributionRankGated(): void
    {
        $insight = (new LuaModRuntimeProblem())
            ->setExceptionClass('java.lang.RuntimeException')
            ->setModAttribution(new \IndifferentKetchup\CodexPz\Analysis\ModAttribution(
                'TestMod', null, null, \IndifferentKetchup\CodexPz\Analysis\AttributionConfidence::Direct
            ));
        $insight->setKind(Kind::LuaRuntime);
        $insight->setAttribution(Attribution::Attributed);

        $json = $insight->jsonSerialize();

        $this->assertArrayHasKey('kind', $json);
        $this->assertSame('lua_runtime', $json['kind']);
        $this->assertArrayHasKey('attribution', $json);
        $this->assertSame('attributed', $json['attribution']);
        $this->assertArrayHasKey('rank', $json);
        $this->assertIsInt($json['rank']);
        $this->assertArrayHasKey('gated', $json);
    }

    public function testJavaExceptionInsightJsonHasKindAttributionRankGated(): void
    {
        $insight = (new JavaExceptionProblem())
            ->setExceptionClass('java.lang.Exception')
            ->setFileLine('test.java:42');
        $insight->setKind(Kind::JavaException);
        $insight->setAttribution(Attribution::Unattributed);

        $json = $insight->jsonSerialize();

        $this->assertArrayHasKey('kind', $json);
        $this->assertSame('java_exception', $json['kind']);
        $this->assertArrayHasKey('attribution', $json);
        $this->assertSame('unattributed', $json['attribution']);
        $this->assertArrayHasKey('rank', $json);
        $this->assertIsInt($json['rank']);
        $this->assertArrayHasKey('gated', $json);
    }

    public function testAnalysisJsonHasGatedTopLevel(): void
    {
        $analysis = new Analysis();
        $gate = new \IndifferentKetchup\CodexPz\Analyser\NoiseGate(
            fingerprint: 'test-fp',
            occurrences: 5,
            reason: 'test reason',
            kind: 'engine_noise',
            sampleMessage: 'test message',
        );
        $analysis->setGatedInsights([$gate]);

        $json = $analysis->jsonSerialize();

        $this->assertArrayHasKey('gated', $json);
        $this->assertCount(1, $json['gated']);
        $this->assertSame('test-fp', $json['gated'][0]['fingerprint']);
        $this->assertSame(5, $json['gated'][0]['occurrences']);
        $this->assertSame('test reason', $json['gated'][0]['reason']);
        $this->assertSame('engine_noise', $json['gated'][0]['kind']);
        $this->assertSame('test message', $json['gated'][0]['sampleMessage']);
    }
}
