<?php

namespace PHPTerminal;

interface ModulesInterface
{
    public function init(Terminal $terminal, string $command) : object;

    public function getCommands() : array;
}