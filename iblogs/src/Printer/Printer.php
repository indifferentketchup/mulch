<?php

namespace IndifferentKetchup\Iblogs\Printer;

use IndifferentKetchup\CodexPz\Log\Entry;
use IndifferentKetchup\CodexPz\Log\EntryInterface;
use IndifferentKetchup\CodexPz\Log\Level;
use IndifferentKetchup\CodexPz\Log\LineInterface;
use IndifferentKetchup\CodexPz\Log\LogInterface;
use IndifferentKetchup\CodexPz\Log\Minecraft\MinecraftLog;
use IndifferentKetchup\CodexPz\Log\ProjectZomboid\ProjectZomboidLog;
use IndifferentKetchup\CodexPz\Printer\Minecraft\MinecraftInlineFormat;
use IndifferentKetchup\CodexPz\Printer\ModifiableDefaultPrinter;
use IndifferentKetchup\CodexPz\Util\ProjectZomboid\ProjectZomboidModAttributor;
use IndifferentKetchup\Iblogs\Id;

/**
 * Class Printer
 *
 * @package Printer
 */
class Printer extends ModifiableDefaultPrinter
{
    private FormatModification $formatModification;

    public function __construct()
    {
        $this->formatModification = new FormatModification();
        $this->addModification($this->formatModification);
    }

    /**
     * @var Id
     */
    protected Id $id;

    /**
     * @param Id $id
     * @return Printer
     */
    public function setId(Id $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Override to wire the per-game `Modification` delegate now that the log
     * type is known. Minecraft logs get the `§`-code translator; Project
     * Zomboid logs get the Lua stack-frame mod attributor. Other games fall
     * through to a no-op `FormatModification`.
     */
    public function setLog(LogInterface $log): static
    {
        $delegate = match (true) {
            $log instanceof MinecraftLog => new MinecraftInlineFormat(),
            $log instanceof ProjectZomboidLog => new ProjectZomboidModAttributor(),
            default => null,
        };
        $this->formatModification->setDelegate($delegate);
        return parent::setLog($log);
    }

    /**
     * @return string
     */
    protected function printLog(): string
    {
        return '<div class="log-inner">' . parent::printLog() . '</div>';
    }

    /**
     * @param EntryInterface|null $entry
     * @return string
     * @throws \Exception
     */
    protected function printEntry(?EntryInterface $entry = null): string
    {
        $entry = $entry ?? $this->entry;
        /** @var Entry $entry */
        $return = '';
        $first = true;
        foreach ($entry as $line) {
            $entryClass = "entry-no-error";
            if ($entry->getLevel()->asInt() <= Level::ERROR->asInt()) {
                $entryClass = "entry-error";
            }
            $return .= '<div class="entry ' . $entryClass . '">';
            $return .= '<div class="line-number-container"><a href="/' . $this->id->get() . '#L' . $line->getNumber() . '" id="L' . $line->getNumber() . '" class="line-number">' . $line->getNumber() . '</a></div>';
            $return .= '<div class="line-content"><span class="level level-' . $entry->getLevel()->asString() . ((!$first) ? " multiline" : "") . '">';
            $lineString = $this->printLine($line);
            if ($entry->getPrefix() !== null) {
                $prefix = htmlentities($entry->getPrefix());
                $lineString = str_replace($prefix, '<span class="level-prefix">' . $prefix . '</span>', $lineString);
            }
            $return .= $lineString;
            $return .= '</span></div>';
            $return .= '</div>';
            $first = false;
        }

        return $return;
    }

    /**
     * @param LineInterface $line
     * @return string
     */
    protected function printLine(LineInterface $line): string
    {
        return $this->runModifications(htmlentities($line->getText())) . PHP_EOL;
    }
}
