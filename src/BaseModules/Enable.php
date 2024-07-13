<?php

namespace PHPTerminal\BaseModules;

use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class Enable extends Modules
{
    protected $terminal;

    protected $command;

    public function init(Terminal $terminal = null, $command)
    {
        $this->terminal = $terminal;

        $this->command = $command;

        return $this;
    }

    public function clearHistory()
    {
        if ($this->terminal->getAccount() && $this->terminal->getAccount()['id']) {
            if (file_exists(base_path('var/terminal/history/' . $this->terminal->getAccount()['id']))) {
                unlink(base_path('var/terminal/history/' . $this->terminal->getAccount()['id']));
            }

            readline_clear_history();

            $this->terminal->addResponse('Cleared history for ' . $this->terminal->getAccount()['profile']['full_name'] ?? $this->terminal->getAccount()['email']);

            return true;
        }
    }

    public function configTerminal()
    {
        $this->terminal->setWhereAt('config');
        $this->terminal->setPrompt('(config)# ');

        return true;
    }

    public function show()
    {
        $command = explode(' ', $this->command);

        if ($command[0] !== 'show') {
            return false;
        }


        var_dump($command, $this->command);

        return true;
    }

    public function switch($args = [])
    {
        var_dump($args);
        $this->terminal->setWhereAt('config');
        $this->terminal->setPrompt('(config)# ');

        return true;
    }

    public function getCommands() : array
    {
        return
            [
                [
                    "availableAt"   => "enable",
                    "command"       => "clear history",
                    "description"   => "Clear terminal history",
                    "function"      => "clearHistory"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "config terminal",
                    "description"   => "Configure terminal Settings",
                    "function"      => "configTerminal"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show module",
                    "description"   => "Show current running module.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show modules",
                    "description"   => "Show all available modules.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "switch module",
                    "description"   => "Switch terminal module to read commands from different location.",
                    "function"      => "switch"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "exit",
                    "description"   => "Exit enable mode",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "quit",
                    "description"   => "Quit Terminal",
                    "function"      => ""
                ]
            ];
    }
}