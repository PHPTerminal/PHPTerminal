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

    public function run($args = [], $initial = true)
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

        $command = [];

        if ($initial) {
            \cli\line("%r%w");
            \cli\line("%bEnter username and password\n");
            \cli\out("%wUsername: ");
        } else {
            \cli\out("%wPassword: ");
            readline_callback_handler_install("", function () {});
        }

        while (true) {
            $input = stream_get_contents(STDIN, 1);

            if (ord($input) == 10) {
                if (!$initial) {
                    \cli\line("%r%w");
                }
                break;
            } else if (ord($input) == 127) {
                if (count($command) === 0) {
                    continue;
                }
                array_pop($command);
                fwrite(STDOUT, chr(8));
                fwrite(STDOUT, "\033[0K");
            } else {
                $command[] = $input;
                if (!$initial) {
                    fwrite(STDOUT, '*');
                }
            }
        }

        $command = join($command);

        while (true) {
            if ($command !== '') {
                if ($initial) {
                    $this->username = $command;
                } else {
                    $this->password = $command;
                }
                if ($this->username && !$this->password) {
                    $initial = false;
                } else if (!$this->username && $this->password) {
                    $initial = true;
                }
            } else {
                if ($initial) {
                    $this->userPromptCount++;
                } else {
                    $this->passPromptCount++;
                }
            }

            break;
        }

        if ($this->username && $this->password) {
            readline_callback_handler_remove();

            return $this->performLogin();
        }


        if ($this->userPromptCount >= 3 || $this->passPromptCount >= 3) {
            readline_callback_handler_remove();

            $this->terminal->addResponse('Login Incorrect! Try again...', 1);

            return true;
        }

        return $this->run($args, $initial);
    }

    protected function performLogin()
    {
        try {
            $this->auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

            $account = $this->auth->attempt($this->username, $this->password);

            if ($account) {
                $this->setEnableMode($account);

                $this->terminal->addResponse(
                    'Authenticated! Welcome ' . $this->terminal->getAccount()['profile']['full_name'] ?? $this->terminal->getAccount()['profile']['email'] . '...'
                );

                return true;
            }
        } catch (\Exception $e) {
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

            readline_read_history(base_path('var/terminal/history/' . $this->terminal->getAccount()['id']));
        }
    }

    public function getCommands() : array
    {
        return
            [
                [
                    "availableAt"   => "disable",
                    "command"       => "enable",
                    "description"   => "Enter enable mode",
                    "function"      => "run"
                ],
                [
                    "availableAt"   => "disable",
                    "command"       => "exit",
                    "description"   => "Quit Terminal",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "disable",
                    "command"       => "quit",
                    "description"   => "Quit Terminal",
                    "function"      => ""
                ]
            ];
    }
}