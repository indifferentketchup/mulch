<?php

namespace IndifferentKetchup\Iblogs\Frontend\Settings;

enum Setting: string
{
    case FULL_WIDTH = "fullWidth";
    case NO_WRAP = "noWrap";
    case FLOATING_SCROLLBAR = "floatingScrollbar";
    case OVERFLOW = "overflow";
    case HIDE_ENGINE_NOISE = "hideEngineNoise";
    case SHOW_ALL_ENTRIES = "showAllEntries";


    /**
     * @return string
     */
    function getLabel(): string
    {
        return match ($this) {
            Setting::FULL_WIDTH => "Full Width",
            Setting::NO_WRAP => "No Wrap",
            Setting::FLOATING_SCROLLBAR => "Floating Scrollbar",
            Setting::OVERFLOW => "Overflow",
            Setting::HIDE_ENGINE_NOISE => "Hide Engine Noise",
            Setting::SHOW_ALL_ENTRIES => "Show All Entries"
        };
    }

    /**
     * @return string|null
     */
    function getBodyClass(): ?string
    {
        return match ($this) {
            Setting::FULL_WIDTH => "setting-full-width",
            Setting::NO_WRAP => "setting-no-wrap",
            Setting::FLOATING_SCROLLBAR => "setting-floating-scrollbar",
            Setting::OVERFLOW => "setting-overflow",
            Setting::HIDE_ENGINE_NOISE => "setting-hide-engine-noise",
            default => null
        };
    }

    /**
     * @return bool
     */
    function getDefault(): bool
    {
        return match ($this) {
            Setting::HIDE_ENGINE_NOISE => true,
            default => false
        };
    }
}
