<?php

namespace PHPTerminal;

interface PluginsInterface
{
    public function init(Terminal $terminal) : object;

    public function onInstall() : object;//return $this

    public function onUninstall() : object;//return $this

    public function getSettings() : array;
}