<?php

namespace PHPTerminal;

use PHPTerminal\PluginsInterface;
use PHPTerminal\Terminal;

class Plugins implements PluginsInterface
{
    public function init(Terminal $terminal) : object
    {
        //
    }

    public function getSettings() : array
    {
        //
    }
}