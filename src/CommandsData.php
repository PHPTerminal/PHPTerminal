<?php

namespace PHPTerminal;

class CommandsData
{
    public $commandsData = [];

    public function getAllData()
    {
        return ['commandsData' => $this->commandsData];
    }

    public function __set($key, $value)
    {
        $this->commandsData[$key] = $value;
    }

    public function __unset($key)
    {
        if (isset($this->commandsData[$key])) {
            unset($this->commandsData[$key]);
        }
    }

    public function __get($key)
    {
        if (isset($this->commandsData[$key])) {
            return $this->commandsData[$key];
        } else {
            throw new \Exception('CommandsData key "' . $key . '" does not exists!');
        }
    }

    public function __isset($key)
    {
        return array_key_exists($key, $this->commandsData);
    }

    public function reset()
    {
        $this->commandsData = [];
    }
}