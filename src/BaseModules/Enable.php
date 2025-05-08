<?php

namespace PHPTerminal\BaseModules;

use PHPTerminal\BaseModules\Commands\Auth;
use PHPTerminal\BaseModules\Commands\Composer;
use PHPTerminal\BaseModules\Commands\History;
use PHPTerminal\BaseModules\Commands\Show;
use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class Enable extends Modules
{
    public $terminal;

    protected $command;

    protected $history;

    protected $auth;

    protected $show;

    protected $composer;

    public function init(Terminal $terminal = null, $command) : object
    {
        $this->terminal = $terminal;

        $this->command = $command;

        if (isset($this->terminal->config['plugins']['auth'])) {
            $this->history = new History($this);

            $this->auth = new Auth($this);
        }

        $this->show = new Show($this);

        $this->composer = new Composer($this);

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
                    "function"      => "",
                    "availableIn"   => "all"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "config terminal",
                    "description"   => "Configure terminal Settings",
                    "function"      => "configTerminal",
                    "availableIn"   => "all"
                ]
            ];

        $commands = array_merge($commands, $this->show->getCommands());

        if (isset($this->terminal->config['plugins']['auth'])) {
            $commands = array_merge($commands, $this->history->getCommands());

            $commands = array_merge($commands, $this->auth->getCommands());
        }

        $commands = array_merge($commands, $this->composer->getCommands());

        return $commands;
    }

    protected function showHistory($args = [])
    {
        return $this->history->showHistory($args);
    }

    public function clearHistory()
    {
        return $this->history->clearHistory();
    }

    public function configTerminal()
    {
        if (isset($this->terminal->config['plugins']['auth'])) {
            $account = $this->terminal->getAccount();

            if ($account && $account['permissions']['config'] === false) {
                $this->terminal->addResponse('Permissions denied!', 1);

                return false;
            }
        }

        $this->terminal->setWhereAt('config');
        $this->terminal->setPrompt('(config)# ');

        return true;
    }

    protected function showRun()
    {
        return $this->show->showRun();
    }

    protected function showAccounts()
    {
        return $this->auth->showAccounts();
    }

    protected function showInstalled(array $args)
    {
        return $this->show->showInstalled($args);
    }

    protected function composerSearch(array $args)
    {
        return $this->composer->composerSearch($args);
    }

    protected function composerCheck($args)//Check for latest release
    {
        return $this->composer->composerCheck($args);
    }
}