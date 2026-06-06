<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Printer;

use IndifferentKetchup\CodexPz\Log\Entry;
use IndifferentKetchup\CodexPz\Log\File\PathLogFile;
use IndifferentKetchup\CodexPz\Log\Line;
use IndifferentKetchup\CodexPz\Log\Log;
use IndifferentKetchup\CodexPz\Printer\DefaultPrinter;
use PHPUnit\Framework\TestCase;

class DefaultPrinterTest extends TestCase
{
    public function testPrint(): void
    {
        $logFile = new PathLogFile(__DIR__ . "/../../data/simple.log");
        $log = new Log();
        $log->setLogFile($logFile);
        $log->parse();

        $printer = new DefaultPrinter();
        $printer->setLog($log);
        $this->assertEquals($logFile->getContent(), trim($printer->print()));
    }

    public function testPrintEntry(): void
    {
        $text = uniqid();
        $entry = (new Entry())->addLine(new Line(1, $text));

        $printer = new DefaultPrinter();
        $printer->setEntry($entry);
        $this->assertEquals($text, trim($printer->print()));
    }
}
