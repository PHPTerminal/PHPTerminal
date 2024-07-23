<?php

namespace PHPTerminal\BaseModules;

use PHPTerminal\Modules;
use PHPTerminal\Terminal;

class Disable extends Modules
{
    protected $terminal;

    protected $auth;

    protected $command;

    protected $username;

    protected $password;

    protected $userPromptCount = 0;

    protected $passPromptCount = 0;

    public function init(Terminal $terminal = null, $command) : object
    {
        $this->terminal = $terminal;

        $this->command = $command;

        return $this;
    }

    public function enable()
    {
        if (!isset($this->terminal->config['plugins']['auth'])) {
            $this->setEnableMode();

            return true;
        }

        if (isset($this->terminal->config['plugins']['auth']['class']) &&
            !class_exists($this->terminal->config['plugins']['auth']['class'])
        ) {
            unset($this->terminal->config['plugins']['auth']);

            $this->terminal->updateConfig($this->terminal->config);

            $this->setEnableMode();

            return true;
        }

        $credentials = $this->terminal->inputToArray(
            ['username', 'password__secret'],
            [],
            [],
            [],
            [
                'username', 'password'
            ],
            3,
            false
        );

        if ($credentials) {
            [$this->username, $this->password] = [$credentials['username'], $credentials['password']];

            return $this->performLogin();
        }
    }

    public function getCommands() : array
    {
        return
            [
                [
                    "availableAt"   => "disable",
                    "command"       => "",
                    "description"   => "General commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "disable",
                    "command"       => "enable",
                    "description"   => "Enter enable mode",
                    "function"      => "enable"
                ]
            ];
    }

    protected function performLogin()
    {
        try {
            $this->auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

            $account = $this->auth->attempt($this->username, $this->password);

            if ($account) {
                if ($account['permissions']['enable'] === false) {
                    $this->terminal->addResponse('Permissions denied!', 1);

                    return false;
                }

                $this->setEnableMode($account);

                $this->terminal->addResponse(
                    'Authenticated! Welcome ' . ($this->terminal->getAccount()['profile']['full_name'] ?? $this->terminal->getAccount()['profile']['email']) . '...'
                );

                return true;
            }
        } catch (\Exception $e) {
            $this->terminal->addResponse($e->getMessage(), 1);

            return false;
        }

        $this->terminal->addResponse('Login Incorrect! Try again...', 1);

        return false;
    }

    protected function setEnableMode($account = null)
    {
        $this->terminal->setWhereAt('enable');
        $this->terminal->setPrompt('# ');
        if ($account) {
            $this->terminal->setAccount($account);
            $this->terminal->setLoginAt(time());
            $this->terminal->setHostname();

            $path = $this->terminal->checkHistoryPath();

            if ($path) {
                readline_read_history($path . $this->terminal->getAccount()['id']);
            }
        }
    }
}