<?php

namespace IndifferentKetchup\Iblogs\Api\Response;

use IndifferentKetchup\Iblogs\Filter\Filter;

class FiltersResponse extends ApiResponse
{
    public function jsonSerialize(): array
    {
        return Filter::getAll();
    }
}