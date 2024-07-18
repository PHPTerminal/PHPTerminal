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
                [
                    "availableAt"   => "enable",
                    "command"       => "composer check",
                    "description"   => "composer check {plugins/modules}. Check if installed plugins/modules have any updates.",
                    "function"      => "composer"
                ]
            ];
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

    protected function composerCheck($args)//Check for latest release
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide needs to be checked, plugins or modules.', 1);

            return false;
        }

        \cli\line("");
        \cli\line('%bChecking ' . $args[0] . ' for any updates...%w');
        \cli\line("");

        if (strtolower($args[0]) === 'plugins') {
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
        }

        if (isset($this->terminal->config[strtolower($args[0])]) && count($this->terminal->config[strtolower($args[0])]) > 0) {
            foreach ($this->terminal->config[strtolower($args[0])] as $package) {
                if (strtolower($args[0]) === 'modules' &&
                    $package['package_name'] === 'phpterminal/phpterminal' &&
                    !$this->terminal->viaComposer
                ) {
                    continue;
                }

                if ($this->runComposerCommand('show -a -l -f json ' . $package['package_name'])) {
                    $composerInfomation = file_get_contents(base_path('composer.install'));

                    $composerInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $composerInfomation));

                    $composerInfomation = @json_decode($composerInfomation, true);

                    if ($composerInfomation && count($composerInfomation) > 0) {
                        if (isset($composerInfomation['latest']) &&
                            $composerInfomation['latest'] !== $package['version']
                        ) {
                            \cli\line('%yUpdate available for package %w' . $package['package_name']);
                            \cli\line('%bInstalled version: %w' . $package['version']);
                            \cli\line('%bAvailable version: %w' . $composerInfomation['latest']);
                            \cli\line("");
                        }
                    } else {
                        $this->readComposerInstallFile(true);

                        return false;
                    }
                } else {
                    $this->readComposerInstallFile(true);

                    return false;
                }
            }
        }

        $this->terminal->addResponse('All installed packages are up to date!');

        return true;
    }
}