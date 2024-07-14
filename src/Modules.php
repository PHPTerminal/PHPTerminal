<?php

namespace PHPTerminal;

use PHPTerminal\ModulesInterface;
use PHPTerminal\Terminal;

class Modules implements ModulesInterface
{
    public function __call($method, $args = [])
    {
        $commandArr = explode(' ', $this->command);

        if ($commandArr[0] !== $method) {
            return false;
        }

        foreach ($this->terminal->execCommandsList[$this->terminal->whereAt] as $commands) {
            if (str_starts_with(strtolower($this->command), strtolower($commands['command']))) {
                $commandArg = trim(str_replace($commands['command'], '', $this->command));

                if ($commandArg !== '') {
                    $commandArgArr = explode(' ', $commandArg);
                }

                $orgCommand = explode(' ', $commands['command']);
                array_walk($orgCommand, function(&$command, $index) {
                    if ($index !== 0) {
                        $command = ucfirst($command);
                    }
                });
                $orgCommandMethod = implode('', $orgCommand);
            }
        }

        if (method_exists($this, $orgCommandMethod)) {
            return $this->{$orgCommandMethod}($commandArgArr ?? []);
        }

        return false;
    }

    public function init(Terminal $terminal, $command) : object
    {
        //
    }

    public function getCommands() : array
    {
        //
    }
}