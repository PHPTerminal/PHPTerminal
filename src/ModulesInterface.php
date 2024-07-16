<?php

namespace PHPTerminal;

interface ModulesInterface
{
    public function init(Terminal $terminal, string $command) : object;

    public function onInstall() : object;//return $this

    public function onUpgrade() : object;//return $this

    public function onUninstall() : object;//return $this

    public function getCommands() : array;
}