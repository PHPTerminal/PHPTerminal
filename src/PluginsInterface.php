<?php

namespace PHPTerminal;

interface PluginsInterface
{
    // return $this - Called when initializing the plugin
    public function init(Terminal $terminal) : object;

    //return $this - Called at the time of plugin install
    public function onInstall() : object;

    //return $this - Called at the time of plugin upgrade
    public function onUpgrade() : object;

    //return $this - Called at the time of plugin uninstall
    public function onUninstall() : object;

    //return array - Called at the time of plugin install to grab predefined settings and are then stored in the database.
    public function getSettings() : array;
}