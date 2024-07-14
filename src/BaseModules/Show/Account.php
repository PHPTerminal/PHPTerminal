<?php

namespace PHPTerminal\BaseModules\Show;

use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class Account extends Modules
{
    protected $terminal;

    protected $command;

    public function init(Terminal $terminal, $command) : object
    {
        $this->terminal = $terminal;

        $this->command = $command;

        return $this;
    }

    public function account($args = [])
    {
        $account = $this->terminal->getAccount();

        unset($account['security']);
        unset($account['identifier']);
        unset($account['role']);
        unset($account['canlogin']);
        unset($account['sessions']);
        unset($account['agents']);
        unset($account['api_clients']);
        unset($account['tunnels']);
        unset($account['profile']['initials_avatar']);

        $this->addResponse('Ok', 0, ['account' => $account]);

        return true;
    }

    public function show($args = [])
    {
        var_dump($args);
        $this->addResponse('Ok', 0, ['show' => $this->terminal->getAccount()['profile']['full_name']]);

        return true;
    }

    public function getCommands() : array
    {
        return
            [
                [
                    "availableAt"   => "enable",
                    "command"       => "show account",
                    "description"   => "Show logged in account details",
                    "function"      => "account"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show user",
                    "description"   => "Shows logged in user",
                    "function"      => "show user"
                ]
            ];
    }
}