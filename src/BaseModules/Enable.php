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

    public function getCommands() : array
    {
        $commands =
            [
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "General commands",
                    "function"      => ""
                ]
            ];

        if (isset($this->terminal->config['plugins']['auth'])) {
            array_push($commands,
                [
                    "availableAt"   => "enable",
                    "command"       => "show history",
                    "description"   => "Show history {number_of_last_commands}. show history 10, will show last 10 history commands. 20 is default value.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "clear history",
                    "description"   => "Clear terminal history",
                    "function"      => "clearHistory"
                ]
            );
        }

        array_push($commands,
            [
                "availableAt"   => "enable",
                "command"       => "config terminal",
                "description"   => "Configure terminal Settings",
                "function"      => "configTerminal"
            ],
            [
                "availableAt"   => "enable",
                "command"       => "",
                "description"   => "",
                "function"      => ""
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
                "command"       => "show installed",
                "description"   => "show installed {plugins/modules}. Show a list of all installed plugins/modules.",
                "function"      => "show"
            ],
            [
                "availableAt"   => "enable",
                "command"       => "",
                "description"   => "",
                "function"      => ""
            ],
            [
                "availableAt"   => "enable",
                "command"       => "",
                "description"   => "composer commands",
                "function"      => ""
            ],
            [
                "availableAt"   => "enable",
                "command"       => "composer search",
                "description"   => "composer search {plugins/modules}. Search for plugins/modules in packagist.org repository.",
                "function"      => "composer"
            ],
            [
                "availableAt"   => "enable",
                "command"       => "composer check",
                "description"   => "composer check {plugins/modules}. Check if installed plugins/modules have any updates.",
                "function"      => "composer"
            ]
        );

        if (isset($this->terminal->config['plugins']['auth'])) {
            array_push($commands,
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "Auth Plugin Commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show accounts",
                    "description"   => "Show all accounts.",
                    "function"      => "show"
                ]
            );
        }

        return $commands;
    }

    protected function showHistory($args = [])
    {
        if (!isset($args[0])) {
            $args[0] = 20;
        }

        if ($this->terminal->getAccount() && $this->terminal->getAccount()['id']) {
            $history = readline_list_history();
            if ($history && count($history) > 0) {
                if ($args[0] < count($history)) {
                    $history = array_slice($history, (count($history) - $args[0]), $args[0], true);
                }

                $this->terminal->addResponse('Ok', 0, ['history' => $history]);

                return true;
            }
        }

        $this->terminal->addResponse('No history!', 2);

        return true;
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
        $account = $this->terminal->getAccount();

        if ($account && $account['permissions']['config'] === false) {
            $this->terminal->addResponse('Permissions denied!', 1);

            return false;
        }

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

    protected function showAccounts()
    {
        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        $accounts = $auth->getAllAccounts();

        if ($accounts) {
            $this->terminal->addResponse(
                '',
                0,
                ['accounts' => $accounts],
                true,
                [
                    'id', 'username', 'full_name', 'email', 'permissions_enable', 'permissions_config'
                ],
                [
                    3,20,30,30,20,20
                ]
            );
        } else {
            $this->terminal->addResponse('Error retrieving list of accounts', 1);

            return false;
        }

        return true;
    }

    protected function showInstalled(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide needs to be checked, plugins or modules.', 1);

            return false;
        }

        if ($args[0] !== 'plugins' && $args[0] !== 'modules') {
            $this->terminal->addResponse('Either plugins or modules can be checked. Don\'t know what ' . $args[0] . ' is...', 1);

            return false;
        }

        if ($args[0] === 'modules') {
            $headers = ['name', 'package_name', 'version', 'location', 'description'];
            $columnsWidth = [10,50,10,40,40];
        } else if ($args[0] === 'plugins') {
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

            $headers = ['name', 'package_name', 'version', 'class', 'description'];
            $columnsWidth = [10,50,10,30,50];
        }

        $this->terminal->addResponse(
            '',
            0,
            ['Installed ' . ucfirst($args[0]) => $this->terminal->config[$args[0]] ?? []],
            true,
            $headers,
            $columnsWidth
        );

        return true;
    }

    protected function composerSearch(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide needs to be checked, plugins or modules.', 1);

            return false;
        }

        if ($args[0] !== 'plugins' && $args[0] !== 'modules') {
            $this->terminal->addResponse('Either plugins or modules can be checked. Don\'t know what ' . $args[0] . ' is...', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bSearching...%w");
        \cli\line("");

        if ($this->runComposerCommand('search -f json phpterminal-' . $args[0])) {
            $composerInfomation = file_get_contents(base_path('composer.install'));

            $composerInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $composerInfomation));

            $composerInfomation = @json_decode($composerInfomation, true);

            if ($composerInfomation && count($composerInfomation) > 0) {
                $uniqueArr = unique_multidim_array($composerInfomation, 'name');

                foreach ($uniqueArr as &$unique) {
                    $unique['installed'] = 'No';

                    foreach ($this->terminal->config[$args[0]] as $package) {
                        if ($unique['name'] === $package['package_name']) {
                            $unique['installed'] = 'Yes';
                        }
                    }
                }

                $this->terminal->addResponse(
                    '',
                    0,
                    [ucfirst($args[0]) => $uniqueArr],
                    true,
                    [
                        'name', 'installed', 'description'
                    ],
                    [
                        50,10,100
                    ]
                );
            } else {
                $this->readComposerInstallFile(true);

                return false;
            }
        } else {
            $this->readComposerInstallFile(true);

            return false;
        }

        return true;
    }

    protected function composerCheck($args)//Check for latest release
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide needs to be checked, plugins or modules.', 1);

            return false;
        }

        if ($args[0] !== 'plugins' && $args[0] !== 'modules') {
            $this->terminal->addResponse('Either plugins or modules can be checked. Don\'t know what ' . $args[0] . ' is...', 1);

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

        $packageToUpdate = false;

        if (isset($this->terminal->config[strtolower($args[0])]) && count($this->terminal->config[strtolower($args[0])]) > 0) {
            foreach ($this->terminal->config[strtolower($args[0])] as $package) {
                if ($this->runComposerCommand('show -a -l -f json ' . $package['package_name'])) {
                    $composerInfomation = file_get_contents(base_path('composer.install'));

                    $composerInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $composerInfomation));

                    $composerInfomation = @json_decode($composerInfomation, true);

                    if ($composerInfomation && count($composerInfomation) > 0) {
                        if (isset($composerInfomation['latest']) &&
                            $composerInfomation['latest'] !== $package['version']
                        ) {
                            $packageToUpdate = true;
                            \cli\line('%yUpdate available for package %w' . $package['package_name']);
                            \cli\line('%bInstalled version: %w' . $package['version']);
                            \cli\line('%bAvailable version: %w' . $composerInfomation['latest']);
                            \cli\line('%bUpgrade command: %wcomposer upgrade ' . substr($args[0], 0, -1) . ' ' . $package['package_name']);

                            if (strtolower($args[0]) === 'modules' &&
                                $package['package_name'] === 'phpterminal/phpterminal'
                            ) {
                                \cli\line('%yNOTE: Package phpterminal/phpterminal should be upgraded via composer and not this application.%w');
                                \cli\line('%yTrying to upgrade phpterminal/phpterminal package via this application will fail and cause errors.%w');
                                \cli\line('%yUpgrade package via composer and then run, composer resync via config mode to sync the updated package.%w');
                                \cli\line('%yIf you have installed phpterminal/phpterminal via git then run, git pull.%w');
                            }

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

        if (!$packageToUpdate) {
            \cli\line('%gAll installed packages are up to date!%w');
        }

        \cli\line("");

        return true;
    }
}