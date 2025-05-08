<?php

namespace PHPTerminal\BaseModules\Commands;

class Composer
{
    protected $module;

    protected $terminal;

    public function __construct($module)
    {
        $this->module = $module;

        $this->terminal = $module->terminal;

        return $this;
    }

    public function getCommands($at = 'enable')
    {
        if ($at === 'enable') {
            return
                [
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
                ];
        } else if ($at === 'configt') {
            return
                [
                    [
                        "availableAt"   => "config",
                        "command"       => "",
                        "description"   => "",
                        "function"      => ""
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
                        "command"       => "",
                        "description"   => "",
                        "function"      => ""
                    ],
                    [
                        "availableAt"   => "config",
                        "command"       => "composer resync",
                        "description"   => "If you installed a plugin or a module via composer and not via phpterminal, you can resync latest information from composer.",
                        "function"      => "composerResync"
                    ]
                ];
        }
    }

    public function composerSearch(array $args)
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

        if ($this->module->runComposerCommand('search -n -f json phpterminal-' . $args[0])) {
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
                $this->module->readComposerInstallFile(true);

                return false;
            }
        } else {
            $this->module->readComposerInstallFile(true);

            return false;
        }

        return true;
    }

    public function composerCheck($args)//Check for latest release
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
                if ($this->module->runComposerCommand('show -n -a -l -f json ' . $package['package_name'])) {
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
                        $this->module->readComposerInstallFile(true);

                        return false;
                    }
                } else {
                    $this->module->readComposerInstallFile(true);

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

    public function composerInstallPlugin($args)
    {
        return $this->composerInstall('plugin', $args);
    }

    public function composerUpgradePlugin($args)
    {
        return $this->composerUpgrade('plugin', $args);
    }

    public function composerRemovePlugin($args)
    {
        return $this->composerRemove('plugin', $args);
    }

    public function composerInstallModule($args)
    {
        return $this->composerInstall('module', $args);
    }

    public function composerUpgradeModule($args)
    {
        return $this->composerUpgrade('module', $args);
    }

    public function composerRemoveModule($args)
    {
        return $this->composerRemove('module', $args);
    }

    public function composerInstall($type, $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide '. $type .' name to install', 1);

            return false;
        }

        if (strtolower($args[0]) === 'phpterminal/phpterminal') {
            \cli\line("");
            \cli\line('%yNOTE: Package phpterminal/phpterminal should be upgraded via composer and not this application.%w');
            \cli\line('%yTrying to upgrade phpterminal/phpterminal package via this application will fail and cause errors.%w');
            \cli\line('%yUpgrade package via composer and then run, composer resync via config mode to sync the updated package.%w');
            \cli\line('%yIf you have installed phpterminal/phpterminal via git then run, git pull.%w');
            \cli\line("");

            return false;
        }

        if (!str_contains(strtolower($args[0]), 'phpterminal-' . $type . 's-')) {
            \cli\line("");
            \cli\line('%rPackage ' . $args[0] . ' is not a valid phpterminal package. Package needs to follow naming convention. See documentation.%w');
            \cli\line("");

            return false;
        }

        \cli\line("");
        \cli\line("%bInstalling $type...%w");
        \cli\line("");

        if ($this->module->runComposerCommand('require -n ' . $args[0])) {
            $this->module->readComposerInstallFile();

            if ($this->composerAddUpdateDetails($type, $args)) {
                return true;
            }
        } else {
            $this->module->readComposerInstallFile(true);
        }

        return true;
    }

    public function composerUpgrade($type, $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide '. $type .' name to upgrade', 1);

            return false;
        }

        if (strtolower($args[0]) === 'phpterminal/phpterminal') {
            \cli\line("");
            \cli\line('%yNOTE: Package phpterminal/phpterminal should be upgraded via composer and not this application.%w');
            \cli\line('%yTrying to upgrade phpterminal/phpterminal package via this application will fail and cause errors.%w');
            \cli\line('%yUpgrade package via composer and then run, composer resync via config mode to sync the updated package.%w');
            \cli\line('%yIf you have installed phpterminal/phpterminal via git then run, git pull.%w');
            \cli\line("");

            return false;
        }

        if (!str_contains(strtolower($args[0]), 'phpterminal-' . $type . 's-')) {
            \cli\line("");
            \cli\line('%rPackage ' . $args[0] . ' is not a valid phpterminal package. Package needs to follow naming convention. See documentation.%w');
            \cli\line("");

            return false;
        }

        \cli\line("");
        \cli\line("%bUpgrading $type...%w");
        \cli\line("");

        if ($this->module->runComposerCommand('require -n ' . $args[0])) {
            $this->module->readComposerInstallFile();

            if ($this->composerAddUpdateDetails($type, $args, 'upgrade')) {
                return true;
            }
        } else {
            $this->module->readComposerInstallFile(true);
        }

        return true;
    }

    public function composerRemove($type, $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide '. $type .' name to remove', 1);

            return false;
        }

        if (strtolower($args[0]) === 'phpterminal/phpterminal') {
            $this->terminal->addResponse('Can not remove base module!', 1);

            return false;
        }

        if (!str_contains(strtolower($args[0]), 'phpterminal-' . $type . 's-')) {
            \cli\line("");
            \cli\line('%rPackage ' . $args[0] . ' is not a valid phpterminal package. Package needs to follow naming convention. See documentation.%w');
            \cli\line("");

            return false;
        }

        \cli\line("");
        \cli\line("%bRemoving $type...%w");
        \cli\line("");

        if ($this->module->runComposerCommand('remove --dry-run -n ' . $args[0])) {
            $found = false;
            if ($type === 'plugin') {
                foreach ($this->terminal->config['plugins'] as $pluginType => $plugin) {
                    if ($plugin['package_name'] === $args[0]) {
                        if ((new $plugin['class'])->init($this->terminal)->onUninstall()) {
                            if ($this->module->runComposerCommand('remove -n ' . $args[0])) {
                                $this->module->readComposerInstallFile();

                                unset($this->terminal->config['plugins'][$pluginType]);
                            } else {
                                $this->module->readComposerInstallFile(true);

                                return true;
                            }
                        }
                        $found = true;
                        break;
                    }
                }
            } else if ($type === 'module') {
                foreach ($this->terminal->config['modules'] as $moduleKey => $module) {
                    if ($module['package_name'] === $args[0]) {
                        if ($this->terminal->config['active_module'] === $moduleKey) {
                            $this->terminal->addResponse('Can not remove active module!', 1);

                            return false;
                        }

                        if ($this->module->runComposerCommand('show -f json ' . $args[0])) {
                            $composerInfomation = file_get_contents(base_path('composer.install'));

                            $composerInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $composerInfomation));

                            $composerInfomation = @json_decode($composerInfomation, true);

                            if ($composerInfomation && count($composerInfomation) > 0) {
                                $namespace = array_keys($composerInfomation['autoload']['psr-4'])[0];
                                $class = $namespace . ucfirst($moduleKey);

                                try {
                                    if (!class_exists($class)) {
                                        include $composerInfomation['path'] . '/' . $composerInfomation['autoload']['psr-4'][array_keys($composerInfomation['autoload']['psr-4'])[0]] . ucfirst($moduleKey) . '.php';
                                    }

                                    (new $class)->init($this->terminal, null)->onUninstall();
                                } catch (\throwable $e) {
                                    \cli\line("");
                                    \cli\line('%yCould not run onUninstall for module ' . $composerInfomation['name'] . ', contact developer!%w');
                                    \cli\line('%y' . $e->getMessage() . '%w');
                                    \cli\line("");

                                    return false;
                                }
                            }
                        } else {
                            $this->module->readComposerInstallFile(true);

                            return false;
                        }

                        if ($this->module->runComposerCommand('remove -n ' . $args[0])) {
                            $this->module->readComposerInstallFile();

                            unset($this->terminal->config['modules'][$moduleKey]);
                        } else {
                            $this->module->readComposerInstallFile(true);

                            return true;
                        }
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) {
                $this->terminal->updateConfig($this->terminal->config);
            } else {
                $this->terminal->addResponse(ucfirst($type) . ' package ' . $args[0] . ' not found!', 1);

                $this->module->readComposerInstallFile(true);

                return false;
            }
        } else {
            $this->module->readComposerInstallFile(true);

            return true;
        }

        return true;
    }

    public function composerResync($showOutput = true)
    {
        if ($showOutput) {
            \cli\line("");
            \cli\line("%bRe-syncing...%w");
            \cli\line("");
        }

        if ($this->module->runComposerCommand('show -n -f json')) {
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
                            \cli\line('%yRemoving plugin ' . $plugin['package_name'] . '...%w');

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
                            \cli\line('%yRemoving module ' . $module['package_name'] . '...%w');

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

                            $this->composerAddUpdateDetails('plugin', [$package['name']]);
                        }
                    } else if (str_contains($package['name'], 'phpterminal-modules')) {
                        $nameArr = explode('-', $package['name']);

                        if (!isset($this->terminal->config['modules'][$nameArr[array_key_last($nameArr)]])) {
                            \cli\line('%bAdding missing module ' . $package['name'] . '...%w');

                            $this->composerAddUpdateDetails('module', [$package['name']]);
                        }
                    }
                }

                $this->terminal->addResponse('Re-sync successful!');
            } else {
                $this->module->readComposerInstallFile(true);
            }
        } else {
            $this->module->readComposerInstallFile(true);
        }

        return true;
    }

    public function composerAddUpdateDetails($type, $args, $process = 'install')
    {
        if ($process === 'install') {
            $call = 'onInstall';
        } else if ($process === 'upgrade') {
            $call = 'onUpgrade';
        }

        if ($this->module->runComposerCommand('show -n -f json ' . $args[0])) {
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

                if ($this->module->runComposerCommand('show -n -f json')) {
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
                        $this->module->readComposerInstallFile(true);

                        return false;
                    }
                } else {
                    $this->module->readComposerInstallFile(true);

                    return false;
                }

                if ($type === 'plugin') {
                    try {
                        if (!class_exists($this->terminal->config['plugins'][$pluginType]['class'])) {
                            include $composerInfomation['path'] . '/' . $composerInfomation['autoload']['psr-4'][array_keys($composerInfomation['autoload']['psr-4'])[0]] . ucfirst($pluginType) . '.php';
                        }

                        $this->terminal->config['plugins'][$pluginType]['settings'] =
                            (new $this->terminal->config['plugins'][$pluginType]['class'])->init($this->terminal)->$call()->getSettings();
                    } catch (\throwable $e) {
                        \cli\line("");
                        \cli\line('%yCould not run on' . $process . ' for plugin ' . $composerInfomation['name'] . ', contact developer!%w');
                        \cli\line('%y' . $e->getMessage() . '%w');
                        \cli\line("");

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
                        $namespace = array_keys($composerInfomation['autoload']['psr-4'])[0];
                        $class = $namespace . ucfirst($moduleKey);
                        if (!class_exists($class)) {
                            include $composerInfomation['path'] . '/' . $composerInfomation['autoload']['psr-4'][array_keys($composerInfomation['autoload']['psr-4'])[0]] . ucfirst($moduleKey) . '.php';
                        }

                        (new $class)->init($this->terminal, null)->$call();
                    } catch (\throwable $e) {
                        \cli\line("");
                        \cli\line('%yCould not run on' . $process . ' for module ' . $composerInfomation['name'] . ', contact developer!%w');
                        \cli\line('%y' . $e->getMessage() . '%w');
                        \cli\line("");
                    }

                    try {
                        $this->terminal->getAllCommands();
                    } catch (\throwable | UnableToListContents $e) {
                        \cli\line('%rError Loading commands from module ' . $composerInfomation['name'] . ', contact developer!%w' . PHP_EOL);

                        if ($process === 'install') {
                            \cli\line('%yUninstalling installed module%w' . PHP_EOL . PHP_EOL);

                            $this->module->runComposerCommand('remove -n ' . $args[0]);

                            $this->module->readComposerInstallFile();

                            unset($this->terminal->config['modules'][$moduleKey]);

                            $this->terminal->updateConfig($this->terminal->config);
                        }
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
}