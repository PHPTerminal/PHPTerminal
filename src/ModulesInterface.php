<?php

namespace PHPTerminal;

interface ModulesInterface
{
    public function init(Terminal $terminal, string $command);

    public function getCommands() : array;
}