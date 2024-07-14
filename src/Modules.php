<?php

namespace PHPTerminal;

use PHPTerminal\ModulesInterface;
use PHPTerminal\Terminal;

class Modules implements ModulesInterface
{
    public function init(Terminal $terminal, $command) : object
    {
        //
    }

    public function getCommands() : array
    {
        //
    }
}