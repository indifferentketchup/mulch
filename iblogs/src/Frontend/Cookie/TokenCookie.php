<?php

namespace IndifferentKetchup\Iblogs\Frontend\Cookie;

use IndifferentKetchup\Iblogs\Config\Config;
use IndifferentKetchup\Iblogs\Config\ConfigKey;
use IndifferentKetchup\Iblogs\Log;

class TokenCookie extends Cookie
{
    /**
     * @param Log $log
     * @return $this
     */
    public function setLog(Log $log): static
    {
        $this->log = $log;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function getKey(): string
    {
        return "IBLOGS_LOG_TOKEN";
    }

    /**
     * @param Log|null $log
     */
    public function __construct(protected ?Log $log = null)
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    protected function getPath(): string
    {
        if (!$this->log) {
            return "/";
        }
        return "/" . $this->log->getId()->get();
    }

    protected function getMaxAge(): ?int
    {
        return Config::getInstance()->get(ConfigKey::STORAGE_TTL);
    }
}