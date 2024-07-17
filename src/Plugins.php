<?php

namespace PHPTerminal;

use PHPTerminal\PluginsInterface;
use PHPTerminal\Terminal;

class Plugins implements PluginsInterface
{
    public function init(Terminal $terminal) : object
    {
        return $this;
    }

    public function onInstall() : object
    {
        return $this;
    }

    public function onUpgrade() : object
    {
        return $this;
    }

    public function onUninstall() : object
    {
        return $this;
    }

    public function getSettings() : array
    {
        return [];
    }
}