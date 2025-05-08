<?php

namespace PHPTerminal\BaseModules\Commands;

class Auth
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
                        "description"   => "Auth Plugin Commands",
                        "function"      => ""
                    ],
                    [
                        "availableAt"   => "enable",
                        "command"       => "show accounts",
                        "description"   => "Show all accounts.",
                        "function"      => "show"
                    ]
                ];
        } else if ($at === 'configt') {
            $commands =
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
                        "description"   => "Auth Plugin Commands",
                        "function"      => ""
                    ],
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

    public function showAccounts()
    {
        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        $accounts = $auth->getAllAccounts();

        if ($accounts) {
            $this->terminal->addResponse(
                '',
                0,
                ['accounts' => $accounts],
                true,
                [
                    'id', 'username', 'full_name', 'email', 'permissions_enable', 'permissions_config'
                ],
                [
                    3,20,30,30,20,20
                ]
            );
        } else {
            $this->terminal->addResponse('Error retrieving list of accounts', 1);

            return false;
        }

        return true;
    }

    public function accountAdd()
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

    public function accountUpdate(array $args)
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
                [],
                [],
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

    public function accountRemove(array $args)
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

    public function passwd()
    {
        $auth = (new $this->terminal->config['plugins']['auth']['class']())->init($this->terminal);

        $account = $auth->getAccountById($this->terminal->getAccount()['id']);

        if ($account) {
            if ($auth->changePassword($account)) {
                $this->terminal->addResponse('Password updated. Please login again with new password.');
            } else {
                $this->terminal->addResponse('Error changing password, contact developer.', 1);
            }
        } else {
            $this->terminal->addResponse('Error initiating password reset, contact developer.', 1);
        }

        $this->terminal->setWhereAt('disable');
        $this->terminal->setPrompt('> ');
        $this->terminal->setAccount(null);

        return true;
    }
}