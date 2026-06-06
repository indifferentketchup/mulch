<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidRedactorIpv4Test extends TestCase
{
    public function testRedactsBareIpv4(): void
    {
        $input = 'Connection from 192.168.1.1 closed.';
        $expected = 'Connection from [REDACTED_IP] closed.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsIpv4WithPortSuffix(): void
    {
        $input = 'Connected to 10.0.0.42:27015.';
        $expected = 'Connected to [REDACTED_IP].';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsMultipleIpv4OnOneLine(): void
    {
        $input = 'Peer 192.168.1.10 -> 192.168.1.20 via 10.0.0.1:8080.';
        $expected = 'Peer [REDACTED_IP] -> [REDACTED_IP] via [REDACTED_IP].';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsLoopbackAndBoundaryAddresses(): void
    {
        $input = implode("\n", [
            '127.0.0.1',
            '0.0.0.0',
            '255.255.255.255',
        ]);
        $expected = implode("\n", [
            '[REDACTED_IP]',
            '[REDACTED_IP]',
            '[REDACTED_IP]',
        ]);

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testDoesNotRedactOutOfRangeOctets(): void
    {
        // 999 is not a valid octet under the 0-255 alternation; the address
        // must therefore be left untouched.
        $input = 'Bogus: 999.999.999.999';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output);
    }

    public function testDoesNotRedactInsideLongerDottedSequence(): void
    {
        // Five dotted segments are not an IPv4 address; the lookarounds must
        // reject any partial match inside the longer sequence.
        $input = 'Path frag 1.2.3.4.5 should not match.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output);
    }

    public function testDoesNotRedactThreeSegmentBuildNumbers(): void
    {
        // PZ build numbers are 3-segment (e.g. 41.78.16) and must not match.
        $input = 'Build 41.78.16 starting up.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output);
    }

    public function testToggleOffLeavesIpv4Intact(): void
    {
        $input = 'Connection from 192.168.1.1:27015 closed.';

        $output = (new ProjectZomboidRedactor())
            ->redactIpAddresses(false)
            ->redact($input);

        $this->assertSame($input, $output);
    }

    public function testIdempotence(): void
    {
        $input = implode("\n", [
            'Connection from 192.168.1.1:27015 closed.',
            'Peer 10.0.0.42 -> 10.0.0.43 via 172.16.0.1:8080.',
        ]);

        $redactor = new ProjectZomboidRedactor();
        $once = $redactor->redact($input);
        $twice = $redactor->redact($once);

        $this->assertSame($once, $twice);
    }
}
