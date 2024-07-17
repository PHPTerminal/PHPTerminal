<?php

namespace PHPTerminal\BaseModules;

use League\Flysystem\UnableToListContents;
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

    protected function switchModule($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide module name.', 1);

            return false;
        }

        $module = strtolower($args[0]);

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

        return false;
    }

    protected function composerAddPlugin($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide plugin name to install', 1);

            return false;
        }

        return $this->composerAddDetails('plugin', $args);
    }

    protected function composerInstallPlugin($args)
    {
        return $this->composerInstall('plugin', $args);
    }

    protected function composerUpgradePlugin($args)
    {
        return $this->composerUpgrade('plugin', $args);
    }

    protected function composerRemovePlugin($args)
    {
        return $this->composerRemove('plugin', $args);
    }

    protected function composerInstallModule($args)
    {
        return $this->composerInstall('module', $args);
    }

    protected function composerUpgradeModule($args)
    {
        return $this->composerUpgrade('module', $args);
    }

    protected function composerRemoveModule($args)
    {
        return $this->composerRemove('module', $args);
    }

    protected function composerInstall($type, $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide '. $type .' name to install', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bInstalling $type...%w");
        \cli\line("");

        if ($this->runComposerCommand('require -n ' . $args[0])) {
            $this->readComposerInstallFile();

            if ($this->composerAddDetails($type, $args)) {
                return true;
            }
        } else {
            $this->readComposerInstallFile(true);
        }

        return true;
    }

    protected function composerUpgrade($type, $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide '. $type .' name to remove', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bUpgrading $type...%w");
        \cli\line("");

        if ($this->runComposerCommand('upgrade -n ' . $args[0])) {
            $this->readComposerInstallFile();

            if ($type === 'plugin') {
                //Extract Plugin Type
                $pluginType = explode('-', $args[0]);

                $pluginType = $pluginType[array_key_last($pluginType)];
            } else if ($type === 'module') {
                //
            }

            if ($this->runComposerCommand('show -i -f json')) {
                $allPackages = file_get_contents(base_path('composer.install'));

                $allPackages = trim(preg_replace('/<warning>.*<\/warning>/', '', $allPackages));

                $allPackages = @json_decode($allPackages, true);

                if ($allPackages && isset($allPackages['installed']) && count($allPackages['installed']) > 0) {
                    foreach ($allPackages['installed'] as $key => $package) {
                        if ($package['name'] === $args[0]) {
                            if ($type === 'plugin') {
                                $this->terminal->config['plugins'][$pluginType]['version'] = $package['version'];
                            } else if ($type === 'module') {
                                //
                            }
                            break;
                        }
                    }
                } else {
                    $this->readComposerInstallFile(true);

                    return true;
                }
            } else {
                $this->readComposerInstallFile(true);

                return true;
            }

            try {
                if ($type === 'plugin') {
                    $this->terminal->config['plugins'][$pluginType]['settings'] =
                        (new $this->terminal->config['plugins'][$pluginType]['class'])->init($this->terminal)->onUpgrade()->getSettings();
                } else if ($type === 'module') {
                    //
                }
            } catch (\throwable $e) {
                if ($type === 'plugin') {
                    $this->terminal->config['plugins'][$pluginType]['settings'] = [];
                } else if ($type === 'module') {
                    //
                }
            }

            $this->terminal->updateConfig($this->terminal->config);
        }

        return true;
    }

    protected function composerRemove($type, $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide '. $type .' name to remove', 1);

            return false;
        }

        \cli\line("");
        \cli\line("%bRemoving $type...%w");
        \cli\line("");

        if ($this->runComposerCommand('remove --dry-run -n ' . $args[0])) {
            if ($type === 'plugin') {
                foreach ($this->terminal->config['plugins'] as $pluginType => $plugin) {
                    if ($plugin['name'] === $args[0]) {
                        if ((new $plugin['class'])->init($this->terminal)->onUninstall()) {
                            if ($this->runComposerCommand('remove -n ' . $args[0])) {
                                $this->readComposerInstallFile();

                                unset($this->terminal->config['plugins'][$pluginType]);
                            } else {
                                $this->readComposerInstallFile(true);

                                return true;
                            }
                        }
                        break;
                    }
                }
            } else if ($type === 'module') {
                //
            }

            $this->terminal->updateConfig($this->terminal->config);
        } else {
            $this->readComposerInstallFile(true);

            return true;
        }

        return true;
    }

    protected function composerAddDetails($type, $args)
    {
        if ($this->runComposerCommand('show -f json ' . $args[0])) {
            $composerInfomation = file_get_contents(base_path('composer.install'));

            $composerInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $composerInfomation));

            $composerInfomation = @json_decode($composerInfomation, true);

            if ($composerInfomation && count($composerInfomation) > 0) {
                if ($type === 'plugin') {
                    //Extract Plugin Type
                    $pluginType = explode('-', $composerInfomation['name']);

                    $pluginType = strtolower($pluginType[array_key_last($pluginType)]);

                    $this->terminal->config['plugins'][$pluginType] = [];
                    $this->terminal->config['plugins'][$pluginType]['name'] = $pluginType;
                    $this->terminal->config['plugins'][$pluginType]['package_name'] = $composerInfomation['name'];
                    $this->terminal->config['plugins'][$pluginType]['description'] = $composerInfomation['description'];
                    $this->terminal->config['plugins'][$pluginType]['class'] = array_keys($composerInfomation['autoload']['psr-4'])[0] . ucfirst($pluginType);
                } else if ($type === 'module') {
                    //Extract Module Key
                    $moduleKey = explode('-', $composerInfomation['name']);

                    $moduleKey = strtolower($moduleKey[array_key_last($moduleKey)]);

                    $this->terminal->config['modules'][$moduleKey] = [];
                    $this->terminal->config['modules'][$moduleKey]['name'] = $moduleKey;
                    $this->terminal->config['modules'][$moduleKey]['package_name'] = $composerInfomation['name'];
                    $this->terminal->config['modules'][$moduleKey]['description'] = $composerInfomation['description'];
                    $this->terminal->config['modules'][$moduleKey]['location'] = $composerInfomation['path'] . '/' . $composerInfomation['autoload']['psr-4'][array_key_first($composerInfomation['autoload']['psr-4'])];
                }

                if ($this->runComposerCommand('show -i -f json')) {
                    $allPackages = file_get_contents(base_path('composer.install'));

                    $allPackages = trim(preg_replace('/<warning>.*<\/warning>/', '', $allPackages));

                    $allPackages = @json_decode($allPackages, true);

                    if ($allPackages && isset($allPackages['installed']) && count($allPackages['installed']) > 0) {
                        $found = false;

                        foreach ($allPackages['installed'] as $key => $package) {
                            if ($package['name'] === $composerInfomation['name']) {
                                if ($type === 'plugin') {
                                    $this->terminal->config['plugins'][$pluginType]['version'] = $package['version'];
                                } else if ($type === 'module') {
                                    $this->terminal->config['modules'][$moduleKey]['version'] = $package['version'];
                                }

                                $found = true;

                                break;
                            }
                        }

                        if (!$found) {
                            return false;
                        }
                    } else {
                        $this->readComposerInstallFile(true);

                        return false;
                    }
                } else {
                    $this->readComposerInstallFile(true);

                    return false;
                }

                if ($type === 'plugin') {
                    try {
                        if (!class_exists($this->terminal->config['plugins'][$pluginType]['class'])) {
                            include $composerInfomation['path'] . '/' . $composerInfomation['autoload']['psr-4'][array_keys($composerInfomation['autoload']['psr-4'])[0]] . ucfirst($pluginType) . '.php';
                        }

                        $this->terminal->config['plugins'][$pluginType]['settings'] =
                            (new $this->terminal->config['plugins'][$pluginType]['class'])->init($this->terminal)->onInstall()->getSettings();
                    } catch (\throwable $e) {
                        $this->terminal->config['plugins'][$pluginType]['settings'] = [];
                    }
                }

                $this->terminal->updateConfig($this->terminal->config);

                if ($type === 'plugin') {
                    if (strtolower($pluginType) === 'auth') {
                        $this->terminal->setWhereAt('disable');
                        $this->terminal->setPrompt('> ');
                    }
                } else if ($type === 'module') {
                    try {
                        $this->terminal->getAllCommands();
                    } catch (\throwable | UnableToListContents $e) {
                        \cli\line('%rError Loading commands from module ' . $composerInfomation['name'] . ', contact Developer!%w' . PHP_EOL);

                        \cli\line('%yUninstalling installed module%w' . PHP_EOL . PHP_EOL);

                        $this->runComposerCommand('remove -n ' . $args[0]);

                        $this->readComposerInstallFile();

                        unset($this->terminal->config['modules'][$moduleKey]);

                        $this->terminal->updateConfig($this->terminal->config);
                    }
                }
            }

            return true;
        }

        \cli\line("");
        \cli\line("%r$args[0] package is not installed locally. Please use command %wcomposer install $type $args[0]%r to install the package.%w");
        \cli\line("");

        return true;
    }

    public function composerResync($showOutput = true)
    {
        if ($showOutput) {
            \cli\line("");
            \cli\line("%bRe-syncing...%w");
            \cli\line("");
        }

        if ($this->runComposerCommand('show -i -f json')) {
            $allPackages = file_get_contents(base_path('composer.install'));

            $allPackages = trim(preg_replace('/<warning>.*<\/warning>/', '', $allPackages));

            $allPackages = @json_decode($allPackages, true);

            if ($allPackages && isset($allPackages['installed']) && count($allPackages['installed']) > 0) {//check for other packages version
                foreach ($allPackages['installed'] as $key => $installed) {
                    $allPackages['installed'][$installed['name']] = $installed;
                    unset($allPackages['installed'][$key]);
                }

                if (isset($this->terminal->config['plugins']) && count($this->terminal->config['plugins']) > 0) {
                    foreach ($this->terminal->config['plugins'] as $pluginKey => $plugin) {
                        $found = false;

                        if (isset($allPackages['installed'][$plugin['package_name']])) {
                            $found = true;

                            if ($plugin['version'] !== $allPackages['installed'][$plugin['package_name']]['version']) {
                                $this->terminal->config['plugins'][$pluginKey]['version'] = $allPackages['installed'][$plugin['package_name']]['version'];

                                if ($showOutput) {
                                    \cli\line('%bUpdating plugin ' . $plugin['package_name'] . ' version to ' . $allPackages['installed'][$plugin['package_name']]['version'] . '...%w');
                                }
                            }
                        }

                        if (!$found) {//If package was uninstalled
                            unset($this->terminal->config['plugins'][$pluginKey]);
                        }
                    }
                }

                if (isset($this->terminal->config['modules']) && count($this->terminal->config['modules']) > 0) {
                    foreach ($this->terminal->config['modules'] as $moduleKey => $module) {
                        if ($module['name'] === 'base' && $this->terminal->viaComposer === false) {//if phpterminal was installed via composer.
                            continue;
                        }

                        $found = false;

                        if (isset($allPackages['installed'][$module['package_name']])) {
                            $found = true;

                            if ($module['version'] !== $allPackages['installed'][$module['package_name']]['version']) {
                                $this->terminal->config['modules'][$moduleKey]['version'] = $allPackages['installed'][$module['package_name']]['version'];

                                if ($showOutput) {
                                    \cli\line('%bUpdating module ' . $module['package_name'] . ' version to ' . $allPackages['installed'][$module['package_name']]['version'] . '...%w');
                                }
                            }
                        }

                        if (!$found && $module['name'] !== 'base') {//If package was uninstalled. We never uninstall base.
                            unset($this->terminal->config['modules'][$moduleKey]);
                        }
                    }
                }

                $this->terminal->updateConfig($this->terminal->config);

                foreach ($allPackages['installed'] as $packageName => $package) {
                    if (str_contains($package['name'], 'phpterminal-plugins')) {
                        $nameArr = explode('-', $package['name']);

                        if (!isset($this->terminal->config['plugins'][$nameArr[array_key_last($nameArr)]])) {
                            \cli\line('%bAdding missing plugin ' . $package['name'] . '...%w');

                            $this->composerAddDetails('plugin', [$package['name']]);
                        }
                    } else if (str_contains($package['name'], 'phpterminal-modules')) {
                        $nameArr = explode('-', $package['name']);

                        if (!isset($this->terminal->config['modules'][$nameArr[array_key_last($nameArr)]])) {
                            \cli\line('%bAdding missing module ' . $package['name'] . '...%w');

                            $this->composerAddDetails('module', [$package['name']]);
                        }
                    }
                }

                $this->terminal->addResponse('Re-sync successful!');
            } else {
                $this->readComposerInstallFile(true);
            }
        } else {
            $this->readComposerInstallFile(true);
        }

        return true;
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
                    "function"      => "switch"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "",
                    "description"   => "composer commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer add plugin",
                    "description"   => "composer add plugin {plugin_package_name}. To add pre-installed plugin (via composer) into phpterminal.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer install plugin",
                    "description"   => "composer install plugin {plugin_package_name}. To install plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer upgrade plugin",
                    "description"   => "composer upgrade plugin {plugin_package_name}. To upgrade plugin directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer remove plugin",
                    "description"   => "composer remove plugin {plugin_package_name}. To remove plugin directly from composer.",
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
                    "command"       => "composer add module",
                    "description"   => "composer add module {module_package_name}. To add pre-installed module (via composer) into phpterminal.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer install module",
                    "description"   => "composer install module {module_package_name}. To install module directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer upgrade module",
                    "description"   => "composer upgrade module {module_package_name}. To upgrade module directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer remove module",
                    "description"   => "composer remove module {module_package_name}. To remove module directly from composer.",
                    "function"      => "composer"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "composer resync",
                    "description"   => "If you installed a plugin or a module via composer and not via phpterminal, you can resync latest information from composer.",
                    "function"      => "composerResync"
                ]
            ];

        if (isset($this->terminal->config['plugins']['auth']['settings']['canResetPasswd']) &&
            $this->terminal->config['plugins']['auth']['settings']['canResetPasswd'] === true
        ) {
            array_push($commands,
                [
                    "availableAt"   => "config",
                    "command"       => "",
                    "description"   => "Auth Plugin Commands",
                    "function"      => ""
                ],
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