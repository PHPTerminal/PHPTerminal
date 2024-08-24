<?php

namespace PHPTerminal;

use PHPTerminal\ModulesInterface;
use PHPTerminal\Terminal;

class Modules implements ModulesInterface
{
    public function init(Terminal $terminal, $command) : object
    {
        return $this;
    }

    public function onInstall() : object
    {
        return $this;
    }

    public function onUpgrade() : object
    {
        return $this;
    }

    public function onUninstall() : object
    {
        return $this;
    }

    public function onActive() : object
    {
        return $this;
    }

    public function getCommands() : array
    {
        return [];
    }

    public function __call($method, $args = []) : bool
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

                if (strtolower($this->command) === strtolower($commands['command'])) {
                    break;
                }
                //Put lists view commands on top for a perfect match
                //Ex: show filters vs show filter
                //put show filters before show filter.
                if (method_exists($this, $orgCommandMethod)) {
                    break;
                }
            }
        }

        if (method_exists($this, $orgCommandMethod)) {
            return $this->{$orgCommandMethod}($commandArgArr ?? []);
        }

        return false;
    }

    public function runComposerCommand($command)
    {
        try {
            $stream = fopen(base_path('composer.install'), 'w');
            $input = new \Symfony\Component\Console\Input\StringInput($command);
            $output = new \Symfony\Component\Console\Output\StreamOutput($stream);

            $application = new \Composer\Console\Application();
            $application->setAutoExit(false); // prevent `$application->run` method from exiting the script

            $app = $application->run($input, $output);
        } catch (\throwable $e) {
            $this->terminal->addResponse($e->getMessage(), 1);

            return false;
        }

        if ($app !== 0) {
            return false;
        }

        return true;
    }

    public function readComposerInstallFile($error = false)
    {
        if ($error) {
            \cli\line("");
            \cli\line("%rComposer error...%w");
            \cli\line("");
        }

        $handle = fopen(base_path('composer.install'), "r");

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (str_contains($line, '<warning>')) {
                    \cli\line("%y$line%w");
                } else {
                    if ($error) {
                        continue;
                    }
                    if (!str_contains($line, '.php line')) {
                        echo $line;
                    }
                }
            }

            fclose($handle);
        }

        \cli\line("%w");
    }
}