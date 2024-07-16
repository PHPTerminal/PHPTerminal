<?php

namespace PHPTerminal;

use PHPTerminal\ModulesInterface;
use PHPTerminal\Terminal;

class Modules implements ModulesInterface
{
    public function init(Terminal $terminal, $command) : object {}

    public function onInstall() : object {}

    public function onUninstall() : object {}

    public function getCommands() : array {}

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

    protected function runComposerCommand($command)
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
            if ($app === 100) {
                $this->terminal->addResponse('Error while installing plugin via composer. Check network connection.', 1);
            } else {
                $this->terminal->addResponse('Error while installing plugin via composer. Try again later.', 1);
            }

            return false;
        }

        return true;
    }

    protected function readComposerInstallFile()
    {
        $handle = fopen(base_path('composer.install'), "r");

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, '<warning>') === 0) {
                    \cli\line("%y$line%w");
                } else {
                    echo $line;
                }
            }

            fclose($handle);
        }
    }
}