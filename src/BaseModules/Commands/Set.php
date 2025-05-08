<?php

namespace PHPTerminal\BaseModules\Commands;

class Set
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
    }

    public function setHostname(array $args)
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

    public function setBanner(array $args)
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

    public function setIdleTimeout(array $args)
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

    public function setHistoryLimit(array $args)
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
}