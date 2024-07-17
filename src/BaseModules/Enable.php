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

    protected function showRun()
    {
        $runningConfiguration = $this->terminal->config;

        unset($runningConfiguration['_id']);

        $this->terminal->addResponse('', 0, ['Running Configuration' => $runningConfiguration]);

        return true;
    }

    protected function showAvailableModules()
    {
        $this->terminal->addResponse(
            '',
            0,
            ['Available Modules' => $this->terminal->config['modules'] ?? []],
            true,
            [
                'name', 'package_name', 'version', 'location', 'description'
            ],
            [
                10,50,50,40,15
            ]
        );

        return true;
    }

    protected function showAvailablePlugins()
    {
        if (!isset($this->terminal->config['plugins']) ||
            (isset($this->terminal->config['plugins']) && count($this->terminal->config['plugins']) === 0)
        ) {
            \cli\line("");
            \cli\line("%yNo plugins available. Search plugins via composer in enable mode.%w");
            \cli\line("Run command : composer search plugins");
            \cli\line("");
            \cli\line("%yTo install new plugins via composer in config mode.%w");
            \cli\line("Run command : composer install plugin {plugin_name}");
            \cli\line("");

            return true;
        }

        $this->terminal->addResponse(
            '',
            0,
            ['Available Plugins' => $this->terminal->config['plugins']],
            true,
            [
                'name', 'package_name', 'version', 'class', 'description'
            ],
            [
                10,50,50,40,15
            ]
        );

        return true;
    }

    protected function composerSearchPlugins()
    {
        \cli\line("");
        \cli\line("%bSearching...%w");
        \cli\line("");

        if ($this->runComposerCommand('search -N phpterminal-plugins')) {
            $this->readComposerInstallFile();
        }

        return true;
    }

    protected function composerSearchModules()
    {
        \cli\line("");
        \cli\line("%bSearching...%w");
        \cli\line("");

        if ($this->runComposerCommand('search -N phpterminal-modules')) {
            $this->readComposerInstallFile();
        }

        return true;
    }

    public function getCommands() : array
    {
        return
            [
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "General commands",
                    "function"      => ""
                ],
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
                    "command"       => "",
                    "description"   => "show commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show run",
                    "description"   => "Show running configuration.",
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
                    "command"       => "show available plugins",
                    "description"   => "Show all available plugins.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "composer commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer search plugins",
                    "description"   => "Search plugins via composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer search modules",
                    "description"   => "Search modules via composer.",
                    "function"      => "composer"
                ],
            ];
    }
}