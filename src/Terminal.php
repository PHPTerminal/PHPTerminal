<?php

namespace PHPTerminal;

use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToListContents;
use PHPTerminal\Base;
use ReflectionClass;

class Terminal extends Base
{
    protected $banner;

    protected $account = null;

    protected $whereAt = 'disable';

    protected $hostname = 'PHPTerminal';

    protected $prompt = '> ';

    protected $exit = 'Bye!';

    protected $commands = [];

    protected $autoCompleteList = [];

    protected $helpList = [];

    protected $execCommandsList = [];

    protected $sessionTimeout = 3600;

    protected $loginAt;

    protected $commandsDir = 'BaseModules/';

    protected $module = 'base';

    protected $moduleCommandsDir;

    public function __construct($dataPath = null)
    {
        parent::__construct(false, $dataPath);

        try {
            $this->getAllCommands();
        } catch (\throwable | UnableToListContents $e) {
            var_dump($e);
            \cli\line("%W%1Error Loading commands, contact Developer!\n\n");

            exit(1);
        }

        parent::__construct(false, $dataPath);

        system('clear');

        $this->setDefaultMode();
        $this->setHostname();
        $this->setBanner();

        \cli\line($this->banner);

        return $this;
    }

    public function run($terminated = false)
    {
        $this->resetTime();

        $this->updateAutoComplete();

        if (!pcntl_async_signals()) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function() {
                $this->run(true);
            });
        }

        if ($terminated) {
            $command = readline();
        } else {
            $command = readline($this->hostname . $this->prompt);
        }

        while (true) {
            if ($command === 'quit') {
                \cli\line('');
                \cli\line('Bye!');
                \cli\line('');

                exit;
            } else if ($command === 'exit') {
                if ($this->whereAt === 'disable') {
                    break;
                } else {
                    if ($this->whereAt !== 'enable') {
                        $this->whereAt = 'enable';
                        $this->prompt = '# ';
                    } else {
                        if ($this->account && $this->checkHistoryPath()) {
                            readline_write_history(base_path('var/terminal/history/' . $this->account['id']));
                        }

                        $this->account = null;
                        $this->whereAt = 'disable';
                        $this->prompt = '> ';
                    }
                }
            } else if (str_contains($command, '?') || $command === '?' || $command === 'help') {
                $this->showHelp();
            } else if (checkCtype($command)) {
                if (!$this->searchCommand(trim(strtolower($command)))) {
                    echo "Command " . trim($command) . " not found!\n";
                } else {
                    readline_add_history($command);
                }
            } else if ($command !== '') {
                echo "Command " . trim($command) . " not found!\n";
            }

            $this->run();
        }

        \cli\line('');
        \cli\line('Bye!');
        \cli\line('');

        exit;
    }

    protected function showHelp()
    {
        if (isset($this->helpList[$this->whereAt]) &&
            count($this->helpList[$this->whereAt]) > 0
        ) {
            $table = new \cli\Table();
            $table->setHeaders(['Available Commands', 'Description']);
            $table->setRows($this->helpList[$this->whereAt]);
            $table->setRenderer(new \cli\table\Ascii([25, 100]));
            $table->display();
        }
    }

    protected function searchCommand($command)
    {
        if (isset($this->execCommandsList[$this->whereAt]) &&
            count($this->execCommandsList[$this->whereAt]) > 0
        ) {
            foreach ($this->execCommandsList[$this->whereAt] as $commands) {
                if (str_starts_with(strtolower($command), strtolower($commands['command']))) {
                    return $this->execCommand($command, $commands);
                }
            }
        }

        return false;
    }

    protected function execCommand($command, $commandArr)
    {
        $this->commandsData->reset();

        if (!isset($commandArr['class'])) {
            return false;
        }

        $class = new $commandArr['class'];

        $response = $class->init($this, $command)->{$commandArr['function']}();

        if ($response !== null) {
            if (count($this->commandsData->getAllData()['commandsData']) === 0) {
                return true;
            }

            if ($this->commandsData->responseCode == 0) {
                $color = "%G";
            } else {
                $color = "%R";
            }

            \cli\line("");
            \cli\line($color . $this->commandsData->responseMessage);
            \cli\out("%W");
            if ($this->commandsData->responseData && count($this->commandsData->responseData) > 0) {

                $responseData = true_flatten($this->commandsData->responseData);

                foreach ($responseData as $key => $value) {
                    if ($value === null || $value === '') {
                        $value = 'null';
                    }
                    \cli\line("%b$key : %W$value");
                }
            }
            \cli\line("");

            return true;
        }

        return false;
    }

    protected function updateAutoComplete()
    {
        readline_completion_function(function($input, $index) {
            if ($input !== '') {
                $rl_info = readline_info();
                $full_input = substr($rl_info['line_buffer'], 0, $rl_info['end']);

                $matches = [];

                if (isset($this->autoCompleteList[$this->whereAt])) {
                    foreach ($this->autoCompleteList[$this->whereAt] as $list) {
                        if (str_starts_with($list, $full_input)) {
                            $matches[] = substr($list, $index);
                        }
                    }
                }

                return $matches;
            }

            return [];
        });
    }

    public function getMode()
    {
        return $this->module;
    }

    public function setDefaultMode()
    {
        if (isset($this->config['default_module'])) {
            $this->module = strtolower($this->config['default_module']);

            if (isset($this->config['modules'][$this->module]['commandsDir'])) {
                $this->moduleCommandsDir = $this->config['modules'][$this->module]['commandsDir'];
            }
        }
    }

    public function setHostname()
    {
        $this->hostname = '[' . $this->module . '] '  . $this->config['hostname'];
    }

    public function setBanner()
    {
        if ($this->module !== 'base') {
            if (isset($this->config['modules'][$this->module]['banner'])) {
                $this->config['modules'][$this->module]['banner'] = str_replace('\\\\', '\\', $this->config['modules'][$this->module]['banner']);

                $this->banner = "%B" . $this->config['modules'][$this->module]['banner'] . "%W";
            }
        } else {
            $this->banner = "%B" . $this->config['banner'] . "%W";
        }
    }

    public function setWhereAt($whereAt)
    {
        $this->whereAt = $whereAt;
    }

    public function getWhereAt()
    {
        return $this->whereAt;
    }

    public function resetTime()
    {
        $this->updateConfig(['updatedAt' => time()]);
    }

    public function getSessionTimeout()
    {
        return $this->sessionTimeout;
    }

    public function setPrompt($prompt)
    {
        $this->prompt = $prompt;
    }

    public function getPrompt()
    {
        return $this->prompt;
    }

    public function setLoginAt($time)
    {
        $this->loginAt = $time;
    }

    public function getLoginAt()
    {
        return $this->loginAt;
    }

    public function setAccount($account)
    {
        $this->account = $account;
    }

    public function getAccount()
    {
        return $this->account;
    }

    protected function getAllCommands()
    {
        if (!isset($this->config['modules']) ||
            (isset($this->config['modules']) && count($this->config['modules']) === 0)
        ) {
            return;
        }

        $this->commands = [];

        foreach ($this->config['modules'] as $module) {
            if ($module['name'] === 'base') {
                $module['location'] = base_path('src/BaseModules/');
            }

            $this->setLocalContent(false, $module['location']);

            $modulesFiles =
                $this->localContent->listContents('.', true)
                ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
                ->map(fn (StorageAttributes $attributes) => $attributes->path())
                ->toArray();

            if (count($modulesFiles) > 0) {
                foreach ($modulesFiles as $moduleFile) {
                    $moduleFileNamespace = $this->extractNamespace($module['location'] . $moduleFile);
                    $moduleFilePath = $moduleFile;
                    $moduleFile = str_replace('.php', '', $moduleFile);
                    $moduleFile = explode('/', $moduleFile);
                    $moduleFileNamespace = '\\' . $moduleFileNamespace . '\\' . $moduleFile[array_key_last($moduleFile)];

                    try {
                        include $module['location'] . $moduleFilePath;

                        $moduleInit = new $moduleFileNamespace();

                        $moduleReflection = new ReflectionClass($moduleInit);
                        $moduleInterfaces = $moduleReflection->getInterfaceNames();

                        if ($moduleInterfaces && count($moduleInterfaces) > 0) {
                            if (!in_array('PHPTerminal\ModulesInterface', $moduleInterfaces)) {
                                continue;
                            } else {
                                $moduleKey = str_replace('\\', '', $moduleFileNamespace);

                                $this->modules[$moduleKey] = $moduleInit->getCommands();

                                foreach ($this->modules[$moduleKey] as &$moduleArr) {
                                    $moduleArr['class'] = $moduleFileNamespace;
                                }
                            }
                        }
                    } catch (\throwable $e) {
                        throw $e;
                    }
                }
            }
        }

        // if ($this->module === 'base') {
        //     break;
        // }

        // if (isset($this->config['modules'][$this->module])) {
            //Read module files
        // }

        foreach ($this->modules as $moduleClass => $modulesArr) {
            foreach ($modulesArr as $module) {
                if (!isset($this->autoCompleteList[$module['availableAt']])) {
                    $this->autoCompleteList[$module['availableAt']] = [];
                }
                array_push($this->autoCompleteList[$module['availableAt']], $module['command']);

                if (!isset($this->helpList[$module['availableAt']])) {
                    $this->helpList[$module['availableAt']] = [];
                }

                array_push($this->helpList[$module['availableAt']], [$module['command'], $module['description']]);

                if (!isset($this->execCommandsList[$module['availableAt']])) {
                    $this->execCommandsList[$module['availableAt']] = [];
                }
                array_push($this->execCommandsList[$module['availableAt']], $module);
            }
        }

        $this->setLocalContent();
    }

    protected function checkHistoryPath()
    {
        if (!is_dir(base_path('terminaldata/var/terminal/history/'))) {
            if (!mkdir(base_path('terminaldata/var/terminal/history/'), 0777, true)) {
                return false;
            }
        }

        return true;
    }

    protected function extractNamespace($file)
    {
        $ns = NULL;

        $handle = fopen($file, "r");

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, 'namespace') === 0) {
                    $parts = explode(' ', $line);

                    $ns = rtrim(trim($parts[1]), ';');

                    break;
                }
            }

            fclose($handle);
        }

        return $ns;
    }
}