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

    public function getCommands(): array
    {
        $commands =
            [
                [
                    "availableAt"   => "config",
                    "command"       => "do",
                    "description"   => "Run enable mode commands in config mode. Example: do show run, will show running configuration from config mode. do ? will show list of enable mode commands.",
                    "function"      => "",
                    "availableIn"   => "all"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "set hostname",
                    "description"   => "Set hostname {hostname}",
                    "function"      => "set",
                    "availableIn"   => "all"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "set banner",
                    "description"   => "Set banner. Enter new banner for the active module.",
                    "function"      => "set",
                    "availableIn"   => "all"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "set idle timeout",
                    "description"   => "Set idle timeout {seconds}. Seconds can be between 60-3600 (1 min to 1 hr)",
                    "function"      => "set",
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "set history limit",
                    "description"   => "Set history limit {number_of_lines}. Max 2000.",
                    "function"      => "set",
                ]
            ];

        if (count($this->terminal->config['modules']) > 1) {
            array_push($commands,
                [
                    "availableAt"   => "config",
                    "command"       => "switch module",
                    "description"   => "switch module {module_name}. Switch terminal module.",
                    "function"      => "switch",
                    "availableIn"   => "all"
                ]
            );
        }

            array_push($commands,
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
            );

        if (isset($this->terminal->config['plugins']['auth'])) {
            array_push($commands,
                [
                    "availableAt"   => "config",
                    "command"       => "",
                    "description"   => "",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "",
                    "description"   => "Auth Plugin Commands",
                    "function"      => ""
                ]
            );

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

            array_push($commands,
                [
                    "availableAt"   => "config",
                    "command"       => "account add",
                    "description"   => "Add new user account.",
                    "function"      => "account"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "account update",
                    "description"   => "account update {user_name}. Update user account.",
                    "function"      => "account"
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "account remove",
                    "description"   => "account remove {user_name}.",
                    "function"      => "account"
                ]
            );
        }

        return $commands;
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

    public function composerResync($showOutput = true)
    {
        if ($showOutput) {
            \cli\line("");
            \cli\line("%bRe-syncing...%w");
            \cli\line("");
        }

        if ($this->runComposerCommand('show -n -f json')) {
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
                $this->readComposerInstallFile(true);
            }
        } else {
            $this->readComposerInstallFile(true);
        }

        return true;
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

        $this->terminal->config['hostname'] = $args[0];

        $this->terminal->updateConfig($this->terminal->config);

        $this->terminal->setHostname();

        return true;
    }

    protected function setBanner(array $args)
    {
        \cli\line("");
        \cli\line('%yEnter new banner for module : ' . $this->terminal->config['active_module'] . '%w');
        \cli\line("");

        $banner = $this->terminal->inputToArray(['banner']);

        if ($banner && isset($banner['banner'])) {
            if (strlen($banner['banner']) > 1024 || strlen($banner['banner']) < 1) {
                $this->terminal->addResponse('Please provide valid banner. Banner can not be less than 1 character or greater than 1024 characters', 1);

                return false;
            }

            $this->terminal->config['modules'][$this->terminal->config['active_module']]['banner'] = $banner['banner'];

            $this->terminal->updateConfig($this->terminal->config);

            $this->terminal->setBanner();

            return true;
        }

        $this->terminal->addResponse('Please provide valid banner', 1);

        return false;
    }

    protected function setIdleTimeout(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide valid timeout. Between 60-3600 seconds', 1);

            return false;
        }

        if (!checkCtype($args[0], 'digits')) {
            $this->terminal->addResponse('Please provide valid timeout. Between 60-3600 seconds', 1);

            return false;
        }

        $this->terminal->setIdleTimeout($args[0]);

        return true;
    }

    protected function setHistoryLimit(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide valid number. Max 2000', 1);

            return false;
        }

        if (!checkCtype($args[0], 'digits')) {
            $this->terminal->addResponse('Please provide valid number. Max 2000', 1);

            return false;
        }

        $this->terminal->setHistoryLimit($args[0]);

        return true;
    }

    protected function accountAdd()
    {
        \cli\line("");
        \cli\line('%yEnter new user account details...%w');
        \cli\line("");

        $user = $this->terminal->inputToArray(
            ['username', 'password__secret', 'full_name', 'email', 'permissions_enable', 'permissions_config']
        );

        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        if ($auth->addAccount($user)) {
            $this->terminal->addResponse('New user account ' . $user['username'] . ' added successfully.');

            return true;
        }

        $this->terminal->addResponse('Error: Could not add user!', 1);

        return true;
    }

    protected function accountUpdate(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide valid username', 1);

            return false;
        }

        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        $account = $auth->getAccountByUsername($args[0]);

        if ($account) {
            \cli\line("");
            \cli\line('%yUpdate user account details...%w');
            \cli\line("");

            $user = $this->terminal->inputToArray(
                ['full_name', 'email', 'permissions_enable', 'permissions_config'],
                [
                    'full_name' => $account['profile']['full_name'],
                    'email' => $account['profile']['email'],
                    'permissions_enable' => $account['permissions']['enable'] == 1 ? 'true' : 'false',
                    'permissions_config' => $account['permissions']['config'] == 1 ? 'true' : 'false'
                ]
            );

            $user['username'] = $account['username'];

            if ($auth->updateAccount($user)) {
                $this->terminal->addResponse('User account ' . $account['username'] . ' updated successfully.');

                return true;
            } else {
                $this->terminal->addResponse('Error updating user!', 1);
            }
        } else {
            $this->terminal->addResponse('Account not found!', 1);
        }

        return true;
    }

    protected function accountRemove(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide valid username', 1);

            return false;
        }

        if ($args[0] === $this->terminal->getAccount()['username']) {
            $this->terminal->addResponse('Cannot remove your own account!', 1);

            return false;
        }

        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        $account = $auth->getAccountByUsername($args[0]);

        if ($account) {
            if ($auth->removeAccount($account['id'])) {
                $this->terminal->addResponse('User account removed successfully');

                return true;
            } else {
                $this->terminal->addResponse('Error removing user account ' . $args[0], 1);
            }
        } else {
            $this->terminal->addResponse('Account with username ' . $args[0] . ' not found', 1);
        }

        return true;
    }

    protected function switchModule($args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide module name.', 1);

            return false;
        }

        $module = strtolower($args[0]);

        if (strtolower($this->terminal->config['active_module']) === $args[0]) {
            $this->terminal->addResponse('Module ' . $module . ' is currently active!', 2);

            return false;
        }

        if (isset($this->terminal->config['modules'][$module])) {
            if ($module !== 'base') {
                if ($this->runComposerCommand('show -n -f json ' . $this->terminal->config['modules'][$module]['package_name'])) {
                    $composerInfomation = file_get_contents(base_path('composer.install'));

                    $composerInfomation = trim(preg_replace('/<warning>.*<\/warning>/', '', $composerInfomation));

                    $composerInfomation = @json_decode($composerInfomation, true);

                    try {
                        $namespace = array_keys($composerInfomation['autoload']['psr-4'])[0];
                        $class = $namespace . ucfirst($module);
                        if (!class_exists($class)) {
                            include $composerInfomation['path'] . '/' . $composerInfomation['autoload']['psr-4'][array_keys($composerInfomation['autoload']['psr-4'])[0]] . ucfirst($module) . '.php';
                        }

                        (new $class)->init($this->terminal, null)->onActive();
                    } catch (\throwable $e) {
                        \cli\line("");
                        \cli\line('%yCould not run onActive method for module ' . $composerInfomation['name'] . ', contact developer!%w');
                        \cli\line('%y' . $e->getMessage() . '%w');
                        \cli\line("");

                        return false;
                    }
                } else {
                    $this->readComposerInstallFile(true);

                    return false;
                }
            }

            $this->terminal->updateConfig(['active_module' => $module]);
            $this->terminal->setActiveModule($module);
            $this->terminal->getAllCommands();
            $this->terminal->setHostname();
            $this->terminal->setBanner();
            \cli\line("");
            \cli\line($this->terminal->getBanner());
            \cli\line("");
        } else {
            $this->terminal->addResponse('Unknwon module: ' . $module . '. Run show installed modules from enable mode to see all installed modules', 1);

            return false;
        }

        return true;
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

        if ($this->runComposerCommand('require -n ' . $args[0])) {
            $this->readComposerInstallFile();

            if ($this->composerAddUpdateDetails($type, $args)) {
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

        if ($this->runComposerCommand('require -n ' . $args[0])) {
            $this->readComposerInstallFile();

            if ($this->composerAddUpdateDetails($type, $args, 'upgrade')) {
                return true;
            }
        } else {
            $this->readComposerInstallFile(true);
        }

        return true;
    }

    protected function composerRemove($type, $args)
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

        if ($this->runComposerCommand('remove --dry-run -n ' . $args[0])) {
            $found = false;
            if ($type === 'plugin') {
                foreach ($this->terminal->config['plugins'] as $pluginType => $plugin) {
                    if ($plugin['package_name'] === $args[0]) {
                        if ((new $plugin['class'])->init($this->terminal)->onUninstall()) {
                            if ($this->runComposerCommand('remove -n ' . $args[0])) {
                                $this->readComposerInstallFile();

                                unset($this->terminal->config['plugins'][$pluginType]);
                            } else {
                                $this->readComposerInstallFile(true);

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

                        if ($this->runComposerCommand('show -n -f json ' . $args[0])) {
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
                            $this->readComposerInstallFile(true);

                            return false;
                        }

                        if ($this->runComposerCommand('remove -n ' . $args[0])) {
                            $this->readComposerInstallFile();

                            unset($this->terminal->config['modules'][$moduleKey]);
                        } else {
                            $this->readComposerInstallFile(true);

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

                $this->readComposerInstallFile(true);

                return false;
            }
        } else {
            $this->readComposerInstallFile(true);

            return true;
        }

        return true;
    }

    protected function composerAddUpdateDetails($type, $args, $process = 'install')
    {
        if ($process === 'install') {
            $call = 'onInstall';
        } else if ($process === 'upgrade') {
            $call = 'onUpgrade';
        }

        if ($this->runComposerCommand('show -n -f json ' . $args[0])) {
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

                if ($this->runComposerCommand('show -n -f json')) {
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

                            $this->runComposerCommand('remove -n ' . $args[0]);

                            $this->readComposerInstallFile();

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