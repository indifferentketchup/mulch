<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Util\Redactor;

use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidRedactor;
use PHPUnit\Framework\TestCase;

class ProjectZomboidRedactorIpv6Test extends TestCase
{
    public function testRedactsFullIpv6(): void
    {
        $input = 'Bound 2001:0db8:85a3:0000:0000:8a2e:0370:7334 ok.';
        $expected = 'Bound [REDACTED_IP] ok.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsAbbreviatedIpv6(): void
    {
        $input = 'Server peer 2001:db8::1 connected.';
        $expected = 'Server peer [REDACTED_IP] connected.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsLoopbackIpv6(): void
    {
        $input = 'localhost ::1 reachable.';
        $expected = 'localhost [REDACTED_IP] reachable.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsBracketedIpv6WithPort(): void
    {
        $input = 'Bound to [2001:db8::1]:8080 ok.';
        $expected = 'Bound to [REDACTED_IP] ok.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsBracketedLoopbackWithPort(): void
    {
        $input = 'Listening on [::1]:27015.';
        $expected = 'Listening on [REDACTED_IP].';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testRedactsIpv4MappedIpv6(): void
    {
        // IPv4-mapped form must be handled by the IPv6 pass before the IPv4
        // pass so the leading "::ffff:" doesn't get orphaned. With the IPv6
        // pass first, the whole token collapses into a single placeholder.
        $input = 'Mapped ::ffff:192.168.1.1 ok.';
        $expected = 'Mapped [REDACTED_IP] ok.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testDoesNotRedactJavaScopeOperator(): void
    {
        // Java method references and PHP scope operators look superficially
        // like leading-:: IPv6 forms but fail filter_var validation; the
        // word-boundary lookbehind also rejects matches that follow letters.
        $input = 'Foo::bar called Object::toString.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output);
    }

    public function testDoesNotRedactTimestampShape(): void
    {
        // PZ log timestamps include hh:mm:ss.v segments which match the coarse
        // IPv6 candidate pattern but are rejected by filter_var.
        $input = '[16-04-26 12:00:00.000][LOG] startup complete';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($input, $output);
    }

    public function testDoesNotRedactSteamIdAsIpv6(): void
    {
        // 17-digit Steam IDs share no characters with IPv6 syntax, but assert
        // explicitly so a future change to the IPv6 regex doesn't accidentally
        // collide with the Steam ID pass.
        $input = 'Player 76561198111111111 joined.';
        $expected = 'Player 76561198000000000 joined.';

        $output = (new ProjectZomboidRedactor())->redact($input);

        $this->assertSame($expected, $output);
    }

    public function testToggleOffLeavesIpv6Intact(): void
    {
        $input = 'Bound to [2001:db8::1]:8080 ok.';

        $output = (new ProjectZomboidRedactor())
            ->redactIpAddresses(false)
            ->redact($input);

        $this->assertSame($input, $output);
    }

    public function testIdempotence(): void
    {
        $input = implode("\n", [
            'Server peer 2001:db8::1 connected.',
            'Listening on [::1]:27015.',
            'Mapped ::ffff:192.168.1.1 ok.',
            '[16-04-26 12:00:00.000][LOG] startup complete',
        ]);

        $redactor = new ProjectZomboidRedactor();
        $once = $redactor->redact($input);
        $twice = $redactor->redact($once);

        $this->assertSame($once, $twice);
    }
}
