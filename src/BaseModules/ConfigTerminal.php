<?php

namespace PHPTerminal\BaseModules;

use League\Flysystem\UnableToListContents;
use PHPTerminal\BaseModules\Commands\Auth;
use PHPTerminal\BaseModules\Commands\Composer;
use PHPTerminal\BaseModules\Commands\Set;
use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class ConfigTerminal extends Modules
{
    public $terminal;

    protected $command;

    protected $set;

    protected $auth;

    protected $composer;

    public function init(Terminal $terminal, $command) : object
    {
        $this->terminal = $terminal;

        $this->command = $command;

        $this->set = new Set($this);

        $this->auth = new Auth($this);

        $this->composer = new Composer($this);

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
                ]
            ];

        $commands = array_merge($commands, $this->set->getCommands());

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

        if (isset($this->terminal->config['plugins']['auth'])) {
            $commands = array_merge($commands, $this->auth->getCommands('configt'));
        }

        $commands = array_merge($commands, $this->composer->getCommands('configt'));

        return $commands;
    }

    protected function setHostname(array $args)
    {
        return $this->set->setHostname($args);
    }

    protected function setBanner(array $args)
    {
        return $this->set->setBanner($args);
    }

    protected function setIdleTimeout(array $args)
    {
        return $this->set->setIdleTimeout($args);
    }

    protected function setHistoryLimit(array $args)
    {
        return $this->set->setHistoryLimit($args);
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

    protected function accountAdd()
    {
        return $this->auth->accountAdd();
    }

    protected function accountUpdate(array $args)
    {
        return $this->auth->accountUpdate($args);
    }

    protected function accountRemove(array $args)
    {
        return $this->auth->accountRemove($args);
    }

    public function passwd()
    {
        return $this->auth->passwd();
    }

    protected function composerInstallPlugin($args)
    {
        return $this->composer->composerInstall('plugin', $args);
    }

    protected function composerUpgradePlugin($args)
    {
        return $this->composer->composerUpgrade('plugin', $args);
    }

    protected function composerRemovePlugin($args)
    {
        return $this->composer->composerRemove('plugin', $args);
    }

    protected function composerInstallModule($args)
    {
        return $this->composer->composerInstall('module', $args);
    }

    protected function composerUpgradeModule($args)
    {
        return $this->composer->composerUpgrade('module', $args);
    }

    protected function composerRemoveModule($args)
    {
        return $this->composer->composerRemove('module', $args);
    }

    public function composerResync($showOutput = true)
    {
        return $this->composer->composerResync($showOutput);
    }
}