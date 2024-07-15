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

        $this->terminal->addResponse('Could not update password. Contact developer!', 1);

        return false;
    }

    public function getCommands(): array
    {
        $commands =
            [
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
                    "command"       => "quit",
                    "description"   => "Quit Terminal",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "config",
                    "command"       => "switch module",
                    "description"   => "switch module {module_name}. Switch terminal module.",
                    "function"      => "switchModule"
                ],
            ];

        if (isset($this->terminal->config['plugins']['auth']['settings']['canReset']) &&
            $this->terminal->config['plugins']['auth']['settings']['canReset'] === true
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