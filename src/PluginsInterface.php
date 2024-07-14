<?php

namespace PHPTerminal;

interface PluginsInterface
{
    public function init(Terminal $terminal) : object;

    public function getSettings() : array;
}