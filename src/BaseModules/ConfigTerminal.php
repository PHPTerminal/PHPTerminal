<?php

namespace PHPTerminal\BaseModules;

use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class ConfigTerminal extends Modules
{
    protected $terminal;

    protected $command;

    public function init(Terminal $terminal, $command)
    {
        $this->terminal = $terminal;

        $this->command = $command;

        return $this;
    }

    public function run($args = [])
    {
        //
    }

    public function set()
    {
        $command = explode(' ', $this->command);

        if ($command[0] !== 'set') {
            return false;
        }

        if (method_exists($this, $method = "set" . ucfirst("{$command[1]}"))) {
            return $this->{$method}($command[2] ?? null);
        }

        var_dump($command, $this->command);

        return true;
    }

    protected function setHostname($hostname)
    {
        $this->terminal->updateConfig(['hostname' => $hostname]);

        $this->terminal->setHostname();

        return true;
    }

    public function getCommands(): array
    {
        return
            [
                [
                    "availableAt"   => "config",
                    "command"       => "set hostname",
                    "description"   => "Set hostname {hostname}",
                    "function"      => "set",
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "set banner",
                    "description"   => "Set banner {banner}",
                    "function"      => "set",
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "set timeout",
                    "description"   => "Set timeout {seconds}",
                    "function"      => "set",
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "quit",
                    "description"   => "Quit Terminal",
                    "function"      => ""
                ]
            ];
    }
}