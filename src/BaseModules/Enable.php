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
            ['Available Modules' => $this->terminal->config['modules'] ?? []],
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

    protected function showAvailablePlugins()
    {
        if (!isset($this->terminal->config['plugins']) ||
            (isset($this->terminal->config['plugins']) && count($this->terminal->config['plugins']) === 0)
        ) {
            \cli\line("");
            \cli\line("%yNo plugins available. Search plugins via composer.%w");
            \cli\line("Run command : composer search plugins");
            \cli\line("");
            \cli\line("%yTo install new plugins via composer.%w");
            \cli\line("Run command : composer install plugin {plugin_name}");
            \cli\line("");

            return true;
        }

        $this->terminal->addResponse(
            '',
            0,
            ['Available Plugins' => $this->terminal->config['plugins']],
            true,
            true,
            [
                'name', 'version', 'class', 'description'
            ],
            [
                50,50,25,25
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

    protected function composerInstallPlugin($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide plugin name to install', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bInstalling...%w");
        \cli\line("");

        if ($this->runComposerCommand('require -n ' . $args[0])) {
            $this->readComposerInstallFile();

            if ($this->runComposerCommand('show -f json ' . $args[0])) {
                $pluginInfomation = file_get_contents(base_path('composer.install'));

                $pluginInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $pluginInfomation));

                $pluginInfomation = @json_decode($pluginInfomation, true);

                if (count($pluginInfomation) > 0) {
                    //Extract Plugin Type
                    $pluginType = explode('-', $pluginInfomation['name']);

                    $pluginType = $pluginType[array_key_last($pluginType)];

                    $this->terminal->config['plugins'][$pluginType] = [];
                    $this->terminal->config['plugins'][$pluginType]['name'] = $pluginInfomation['name'];
                    $this->terminal->config['plugins'][$pluginType]['description'] = $pluginInfomation['description'];
                    $this->terminal->config['plugins'][$pluginType]['class'] = array_keys($pluginInfomation['autoload']['psr-4'])[0] . ucfirst($pluginType);

                    if ($this->runComposerCommand('show -i -f json')) {
                        $allPackages = file_get_contents(base_path('composer.install'));

                        $allPackages = trim(preg_replace('/<warning>.*<\/warning>/', '', $allPackages));

                        $allPackages = @json_decode($allPackages, true);

                        if (isset($allPackages['installed']) && count($allPackages['installed']) > 0) {
                            foreach ($allPackages['installed'] as $key => $package) {
                                if ($package['name'] === $pluginInfomation['name']) {
                                    $this->terminal->config['plugins'][$pluginType]['version'] = $package['version'];
                                    break;
                                }
                            }
                        }
                    }

                    try {
                        include
                            $pluginInfomation['path'] . '/' . $pluginInfomation['autoload']['psr-4'][array_keys($pluginInfomation['autoload']['psr-4'])[0]] . ucfirst($pluginType) . '.php';

                        $this->terminal->config['plugins'][$pluginType]['settings'] =
                            (new $this->terminal->config['plugins'][$pluginType]['class'])->getSettings();
                    } catch (\throwable $e) {
                        $this->terminal->config['plugins'][$pluginType]['settings'] = [];
                    }

                    $this->terminal->updateConfig($this->terminal->config);

                    if (strtolower($pluginType) === 'auth') {
                        $this->terminal->setWhereAt('disable');
                        $this->terminal->setPrompt('> ');
                    }
                }
            }
        }

        return true;
    }

    protected function composerUpgradePlugin($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide plugin name to remove', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bUpgrading...%w");
        \cli\line("");

        if ($this->runComposerCommand('upgrade -n ' . $args[0])) {
            $this->readComposerInstallFile();

            //Extract Plugin Type
            $pluginType = explode('-', $args[0]);

            $pluginType = $pluginType[array_key_last($pluginType)];

            if ($this->runComposerCommand('show -i -f json')) {
                $allPackages = file_get_contents(base_path('composer.install'));

                $allPackages = trim(preg_replace('/<warning>.*<\/warning>/', '', $allPackages));

                $allPackages = @json_decode($allPackages, true);

                if (isset($allPackages['installed']) && count($allPackages['installed']) > 0) {
                    foreach ($allPackages['installed'] as $key => $package) {
                        if ($package['name'] === $args[0]) {
                            $this->terminal->config['plugins'][$pluginType]['version'] = $package['version'];
                            break;
                        }
                    }
                }
            }

            try {
                $this->terminal->config['plugins'][$pluginType]['settings'] =
                    (new $this->terminal->config['plugins'][$pluginType]['class'])->getSettings();
            } catch (\throwable $e) {
                $this->terminal->config['plugins'][$pluginType]['settings'] = [];
            }

            $this->terminal->updateConfig($this->terminal->config);
        }

        return true;
    }

    protected function composerRemovePlugin($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide plugin name to remove', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bRemoving...%w");
        \cli\line("");

        if ($this->runComposerCommand('remove -n ' . $args[0])) {
            $this->readComposerInstallFile();

            foreach ($this->terminal->config['plugins'] as $pluginType => $plugin) {
                if ($plugin['name'] === $args[0]) {
                    unset($this->terminal->config['plugins'][$pluginType]);

                    break;
                }
            }

            $this->terminal->updateConfig($this->terminal->config);
        }

        return true;
    }

    protected function runComposerCommand($command)
    {
        try {
            $stream = fopen(base_path('composer.install'), 'w');
            $input = new \Symfony\Component\Console\Input\StringInput($command);
            $output = new \Symfony\Component\Console\Output\StreamOutput($stream);

            $application = new \Composer\Console\Application();
            $application->setAutoExit(false); // prevent `$application->run` method from exiting the script

            $app = $application->run($input, $output);
        } catch (\throwable $e) {
            $this->terminal->addResponse($e->getMessage(), 1);

            return false;
        }

        if ($app !== 0) {
            if ($app === 100) {
                $this->terminal->addResponse('Error while installing plugin via composer. Check network connection.', 1);
            } else {
                $this->terminal->addResponse('Error while installing plugin via composer. Try again later.', 1);
            }

            return false;
        }

        return true;
    }

    protected function readComposerInstallFile()
    {
        $handle = fopen(base_path('composer.install'), "r");

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, '<warning>') === 0) {
                    \cli\line("%y$line%w");
                } else {
                    echo $line;
                }
            }

            fclose($handle);
        }
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
                    "command"       => "show run",
                    "description"   => "Show running configuration.",
                    "function"      => "show"
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
                    "command"       => "show available plugins",
                    "description"   => "Show all available plugins.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer search plugins",
                    "description"   => "Search plugins via composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer install plugin",
                    "description"   => "composer install plugin {plugin_name}. To install plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer upgrade plugin",
                    "description"   => "composer upgrade plugin {plugin_name}. To upgrade plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer remove plugin",
                    "description"   => "composer remove plugin {plugin_name}. To remove plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "composer resync",
                    "description"   => "If you installed a plugin or a module via composer and not via phpterminal, you can resync latest information from composer.",
                    "function"      => "composer"
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