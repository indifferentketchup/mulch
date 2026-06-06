<?php

namespace IndifferentKetchup\CodexPz\Test\Tests\Printer;

use IndifferentKetchup\CodexPz\Log\File\StringLogFile;
use IndifferentKetchup\CodexPz\Log\Log;
use IndifferentKetchup\CodexPz\Printer\ModifiableDefaultPrinter;
use IndifferentKetchup\CodexPz\Printer\PatternModification;
use PHPUnit\Framework\TestCase;

class PatternModificationTest extends TestCase
{
    public function testPrint(): void
    {
        $logFile = new StringLogFile("This is foo!");
        $log = new Log();
        $log->setLogFile($logFile);
        $log->parse();

        $printer = new ModifiableDefaultPrinter();
        $printer->addModification(new PatternModification('/foo/', 'bar'));
        $printer->setLog($log);
        $this->assertEquals("This is bar!", trim($printer->print()));
    }
}
