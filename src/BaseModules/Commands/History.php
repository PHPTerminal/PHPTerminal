<?php

namespace PHPTerminal\BaseModules\Commands;

class History
{
    protected $module;

    protected $terminal;

    public function __construct($module)
    {
        $this->module = $module;

        $this->terminal = $module->terminal;

        return $this;
    }

    public function getCommands()
    {
        return
            [
                [
                    "availableAt"   => "enable",
                    "command"       => "show history",
                    "description"   => "Show history {number_of_last_commands}. show history 10, will show last 10 history commands. 20 is default value.",
                    "function"      => "show",
                    "availableIn"   => "all"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "clear history",
                    "description"   => "Clear terminal history",
                    "function"      => "clearHistory",
                    "availableIn"   => "all"
                ]
            ];
    }

    public function showHistory($args = [])
    {
        if (!isset($args[0])) {
            $args[0] = 20;
        }

        if ($this->terminal->getAccount() && $this->terminal->getAccount()['id']) {
            $history = readline_list_history();
            if ($history && count($history) > 0) {
                if ($args[0] < count($history)) {
                    $history = array_slice($history, (count($history) - $args[0]), $args[0], true);
                }

                $showSearch = 'Showing';
                if ($this->terminal->getFilters()) {
                    $showSearch = 'Searching';
                }
                $this->terminal->addResponse(
                    'History limit is set to ' . $this->terminal->config['historyLimit'] . PHP_EOL . $showSearch . ' last ' . $args[0] . ' entries...',
                    4,
                    ['history' => $history]
                );

                return true;
            }
        }

        $this->terminal->addResponse('No history!', 2);

        return true;
    }

    public function clearHistory()
    {
        if ($this->terminal->getAccount() && $this->terminal->getAccount()['id']) {
            if (file_exists(base_path('var/terminal/history/' . $this->terminal->getAccount()['id']))) {
                unlink(base_path('var/terminal/history/' . $this->terminal->getAccount()['id']));
            }

            readline_clear_history();

            $this->terminal->addResponse(
                'Cleared history for ' . $this->terminal->getAccount()['profile']['full_name'] ?? $this->terminal->getAccount()['email']
            );
        }

        return true;
    }
}