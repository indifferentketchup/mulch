<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Printer;

use IndifferentKetchup\CodexPz\Log\Entry;
use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Log\Line;
use IndifferentKetchup\CodexPz\Log\Log;
use IndifferentKetchup\CodexPz\Printer\ModifiableDefaultPrinter;
use IndifferentKetchup\CodexPz\Test\Src\Printer\TestModification;
use PHPUnit\Framework\TestCase;

class ModifiableDefaultPrinterTest extends TestCase
{

    public function testPrint(): void
    {
        $logFile = new StringLogFile("This is foo!");
        $log = new Log();
        $log->setLogFile($logFile);
        $log->parse();

        $printer = new ModifiableDefaultPrinter();
        $printer->addModification(new TestModification());
        $printer->setLog($log);
        $this->assertEquals("This is bar!", trim($printer->print()));
    }

    public function testPrintEntry(): void
    {
        $entry = (new Entry())->addLine(new Line(1, "This is foo!"));

        $printer = new ModifiableDefaultPrinter();
        $printer->setModifications([new TestModification()]);
        $printer->setEntry($entry);
        $this->assertEquals("This is bar!", trim($printer->print()));
    }
}
