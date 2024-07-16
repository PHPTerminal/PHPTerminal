<?php

namespace PHPTerminal\BaseModules;

use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class ConfigTerminal extends Modules
{
    protected $terminal;

    protected $command;

    public function init(Terminal $terminal, $command) : object
    {
        $this->terminal = $terminal;

        $this->command = $command;

        return $this;
    }

    public function run($args = [])
    {
        //
    }

    protected function setHostname(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide valid hostname', 1);

            return false;
        }

        if (!checkCtype($args[0], 'alnum', [' '])) {
            $this->terminal->addResponse('Please provide valid hostname. Hostname cannot have special characters', 1);

            return false;
        }

        if (strlen($args[0]) > 20) {
            $this->terminal->addResponse('Please provide valid hostname. Hostname cannot be greater than 20 characters', 1);

            return false;
        }

        $this->terminal->updateConfig(['hostname' => $args[0]]);

        $this->terminal->setHostname();

        return true;
    }

    public function switchModule()
    {
        $commandArr = explode(' ', $this->command);

        if ($commandArr[0] !== 'switch' ||
            count($commandArr) > 3
        ) {
            return false;
        }

        $module = strtolower($commandArr[array_key_last($commandArr)]);

        if (isset($this->terminal->config['modules'][$module])) {
            $this->terminal->updateConfig(['active_module' => $module]);
            $this->terminal->setActiveModule($module);
            $this->terminal->setHostname();
            $this->terminal->getAllCommands();
        } else {
            $this->terminal->addResponse('Unknwon module: ' . $module . '. Run show available modules from enable mode to see all available modules', 1);

            return false;
        }

        return true;
    }

    public function passwd()
    {
        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        $account = $auth->getAccount($this->terminal->getAccount()['id']);

        if ($account) {
            if ($auth->changePassword($account)) {
                $this->terminal->addResponse('Password updated. Please login again with new password.');
                $this->terminal->setWhereAt('disable');
                $this->terminal->setPrompt('> ');
                $this->terminal->setAccount(null);

                return true;
            }
        }

        $this->terminal->addResponse('Password not updated!', 1);

        return false;
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

    protected function composerInstallModule($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide module name to install', 1);

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

    protected function composerUpgradeModule($args)
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

    protected function composerRemoveModule($args)
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

    protected function composerResync()
    {
        \cli\line("");
        \cli\line("%bRe-syncing...%w");
        \cli\line("");

        if ($this->runComposerCommand('show -i -f json')) {
            $allPackages = file_get_contents(base_path('composer.install'));

            $allPackages = trim(preg_replace('/<warning>.*<\/warning>/', '', $allPackages));

            $allPackages = @json_decode($allPackages, true);

            if (isset($allPackages['installed']) && count($allPackages['installed']) > 0) {
                if (count($this->terminal->config['plugins']) > 0) {
                    foreach ($this->terminal->config['plugins'] as $pluginKey => $plugin) {
                        $found = false;

                        foreach ($allPackages['installed'] as $key => $package) {
                            if ($plugin['name'] === $package['name']) {
                                $found = true;

                                $this->terminal->config['plugins'][$pluginKey]['version'] = $package['version'];

                                break;
                            }
                        }

                        if (!$found) {//If package was uninstalled
                            unset($this->terminal->config['plugins'][$pluginKey]);
                        }
                    }
                }

                if (count($this->terminal->config['modules']) > 0) {
                    foreach ($this->terminal->config['modules'] as $moduleKey => $module) {
                        if ($module['name'] === 'base' && $this->terminal->viaComposer === false) {//if phpterminal was installed via composer.
                            continue;
                        }

                        $found = false;

                        foreach ($allPackages['installed'] as $key => $package) {
                            if ($module['name'] === $package['name']) {
                                $found = true;

                                $this->terminal->config['modules'][$moduleKey]['version'] = $package['version'];

                                break;
                            }
                        }

                        if (!$found && $module['name'] !== 'base') {//If package was uninstalled. We never uninstall base.
                            unset($this->terminal->config['modules'][$moduleKey]);
                        }
                    }
                }

                $this->terminal->updateConfig($this->terminal->config);
            }

            $this->terminal->addResponse('Re-sync successful!');

            return true;
        }


        return false;
    }

    public function getCommands(): array
    {
        $commands =
            [
                [
                    "availableAt"   => "config",
                    "command"       => "do",
                    "description"   => "Run enable mode commands in config mode. Example: do show run, will show running configuration from config mode. do ? will show list of enable mode commands.",
                    "function"      => "",
                ],
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
                    "command"       => "switch module",
                    "description"   => "switch module {module_name}. Switch terminal module.",
                    "function"      => "switchModule"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "",
                    "description"   => "composer commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer install plugin",
                    "description"   => "composer install plugin {plugin_name}. To install plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer upgrade plugin",
                    "description"   => "composer upgrade plugin {plugin_name}. To upgrade plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer remove plugin",
                    "description"   => "composer remove plugin {plugin_name}. To remove plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "",
                    "description"   => "",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer install module",
                    "description"   => "composer install module {module_name}. To install module directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer upgrade module",
                    "description"   => "composer upgrade module {module_name}. To upgrade module directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer remove module",
                    "description"   => "composer remove module {module_name}. To remove module directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer resync",
                    "description"   => "If you installed a plugin or a module via composer and not via phpterminal, you can resync latest information from composer.",
                    "function"      => "composer"
                ]
            ];

        if (isset($this->terminal->config['plugins']['auth']['settings']['canResetPasswd']) &&
            $this->terminal->config['plugins']['auth']['settings']['canResetPasswd'] === true
        ) {
            array_push($commands,
                [
                    "availableAt"   => "config",
                    "command"       => "passwd",
                    "description"   => "Set new password for current logged in user.",
                    "function"      => "passwd"
                ]
            );
        }

        return $commands;
    }
}