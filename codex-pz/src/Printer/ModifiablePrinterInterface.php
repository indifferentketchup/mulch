<?php

namespace IndifferentKetchup\CodexPz\Printer;

/**
 * Interface ModifiablePrinterInterface
 *
 * @package IndifferentKetchup\CodexPz\Printer
 */
interface ModifiablePrinterInterface extends PrinterInterface
{
    /**
     * Set all modifications replacing the current modifications
     *
     * @param ModificationInterface[] $modifications
     * @return $this
     */
    public function setModifications(array $modifications): static;

    /**
     * Add a modification
     *
     * @param ModificationInterface $modification
     * @return $this
     */
    public function addModification(ModificationInterface $modification): static;
}