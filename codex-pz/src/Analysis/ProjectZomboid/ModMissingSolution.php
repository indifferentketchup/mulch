<?php

namespace IndifferentKetchup\CodexPz\Analysis\ProjectZomboid;

use IndifferentKetchup\CodexPz\Analysis\Solution;

class ModMissingSolution extends Solution
{
    private string $modName = '';

    public function setModName(string $modName): static
    {
        $this->modName = $modName;
        return $this;
    }

    public function getMessage(): string
    {
        return sprintf(
            'Subscribe to mod "%s" or remove its ID from the Mods= line in serverconfig.ini.',
            $this->modName
        );
    }
}
