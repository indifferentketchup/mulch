<?php

namespace IndifferentKetchup\Iblogs\Router;

abstract class Action
{
    abstract public function run(): bool;
}