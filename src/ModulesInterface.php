<?php

namespace PHPTerminal;

interface ModulesInterface
{
    // return $this - Called when initializing the module
    public function init(Terminal $terminal, string $command) : object;

    //return $this - Called at the time of module install
    public function onInstall() : object;

    //return $this - Called at the time of module upgrade
    public function onUpgrade() : object;

    //return $this - Called at the time of module uninstall
    public function onUninstall() : object;

    //return $this - Called at the time of module activation using switch module {module_name} configuration mode command.
    public function onActive() : object;

    //return array - Called at the start of the application and whenever mode is changed
    public function getCommands() : array;
}