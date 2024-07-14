<?php

namespace PHPTerminal\BaseModules;

use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class Enable extends Modules
{
    protected $terminal;

    protected $command;

    public function init(Terminal $terminal = null, $command) : object
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

            $this->terminal->addResponse(
                'Cleared history for ' . $this->terminal->getAccount()['profile']['full_name'] ?? $this->terminal->getAccount()['email']
            );

            return true;
        }
    }

    public function configTerminal()
    {
        $this->terminal->setWhereAt('config');
        $this->terminal->setPrompt('(config)# ');

        return true;
    }

    protected function showActiveModule()
    {
        $this->terminal->addResponse('', 0, ['Active Module' => $this->terminal->module]);

        return true;
    }

    protected function showAvailableModules()
    {
        $this->terminal->addResponse(
            '',
            0,
            ['Available Modules' => $this->terminal->config['modules']],
            true,
            true,
            [
                'name', 'description', 'location'
            ],
            [
                20,75,75
            ]
        );

        return true;
    }

    public function switchModule($module)
    {
        $this->terminal->module = $module;

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
                    "command"       => "show active module",
                    "description"   => "Show current running module.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show available modules",
                    "description"   => "Show all available modules.",
                    "function"      => "show"
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