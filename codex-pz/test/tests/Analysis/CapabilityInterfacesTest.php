<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Analysis;

use IndifferentKetchup\CodexPz\Analysis\AttributionConfidence;
use IndifferentKetchup\CodexPz\Analysis\CauseChainInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\EngineNoiseInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Insight;
use IndifferentKetchup\CodexPz\Analysis\InsightInterface;
use IndifferentKetchup\CodexPz\Analysis\ModAttribution;
use IndifferentKetchup\CodexPz\Analysis\ModAttributedInsightInterface;
use IndifferentKetchup\CodexPz\Analysis\Severity;
use IndifferentKetchup\CodexPz\Analysis\SeverityAwareInsightInterface;
use IndifferentKetchup\CodexPz\Test\Src\Analysis\TestInsight;
use PHPUnit\Framework\TestCase;

class CapabilityInterfacesTest extends TestCase
{
    public function testModAttributionJsonSerializeShape(): void
    {
        $attr = new ModAttribution(
            modName: 'MyMod',
            workshopId: '12345',
            deepestModFrame: 'MyMod/file.lua:42',
            confidence: AttributionConfidence::Direct
        );

        $data = $attr->jsonSerialize();

        $this->assertSame('MyMod', $data['modName']);
        $this->assertSame('12345', $data['workshopId']);
        $this->assertSame('MyMod/file.lua:42', $data['deepestModFrame']);
        $this->assertSame('direct', $data['confidence']);
    }

    public function testModAttributionNullableFields(): void
    {
        $attr = new ModAttribution(
            modName: 'UnknownMod',
            workshopId: null,
            deepestModFrame: null,
            confidence: AttributionConfidence::Unknown
        );

        $data = $attr->jsonSerialize();

        $this->assertNull($data['workshopId']);
        $this->assertNull($data['deepestModFrame']);
        $this->assertSame('unknown', $data['confidence']);
    }

    public function testSeveritySortOrder(): void
    {
        $this->assertLessThan(Severity::High->value, Severity::Noise->value);
        $this->assertLessThan(Severity::Low->value, Severity::Noise->value);
        $this->assertLessThan(Severity::Medium->value, Severity::Low->value);
        $this->assertLessThan(Severity::High->value, Severity::Medium->value);
        $this->assertLessThan(Severity::Critical->value, Severity::High->value);
    }

    public function testSeverityValues(): void
    {
        $this->assertSame(5, Severity::Noise->value);
        $this->assertSame(20, Severity::Low->value);
        $this->assertSame(50, Severity::Medium->value);
        $this->assertSame(80, Severity::High->value);
        $this->assertSame(100, Severity::Critical->value);
    }

    public function testCapableInsightJsonSerializeCarriesAllCapabilityKeys(): void
    {
        $insight = new class extends Insight implements
            SeverityAwareInsightInterface,
            ModAttributedInsightInterface,
            EngineNoiseInsightInterface,
            CauseChainInsightInterface
        {
            public function getMessage(): string { return 'test'; }
            public function isEqual(InsightInterface $insight): bool { return false; }
            public function getSeverity(): Severity { return Severity::High; }
            public function getModAttribution(): ?ModAttribution
            {
                return new ModAttribution('TestMod', '99999', 'foo.lua:1', AttributionConfidence::Inferred);
            }
            public function getCauseChain(): ?string { return 'caused by bar'; }
        };

        $data = $insight->jsonSerialize();

        // Original four keys must be present
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('counter', $data);
        $this->assertArrayHasKey('entry', $data);
        $this->assertArrayHasKey('fingerprint', $data);

        // Four new capability keys
        $this->assertArrayHasKey('severity', $data);
        $this->assertSame(Severity::High->value, $data['severity']);

        $this->assertArrayHasKey('mod', $data);
        $this->assertInstanceOf(ModAttribution::class, $data['mod']);

        $this->assertArrayHasKey('engineNoise', $data);
        $this->assertTrue($data['engineNoise']);

        $this->assertArrayHasKey('causeChain', $data);
        $this->assertSame('caused by bar', $data['causeChain']);
    }

    public function testPlainInsightJsonSerializeLacksCapabilityKeys(): void
    {
        $insight = new TestInsight();

        $data = $insight->jsonSerialize();

        // Original four keys must still be present
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('counter', $data);
        $this->assertArrayHasKey('entry', $data);
        $this->assertArrayHasKey('fingerprint', $data);

        // No capability keys
        $this->assertArrayNotHasKey('severity', $data);
        $this->assertArrayNotHasKey('mod', $data);
        $this->assertArrayNotHasKey('engineNoise', $data);
        $this->assertArrayNotHasKey('causeChain', $data);
    }
}
