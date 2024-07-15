<?php

namespace PHPTerminal;

use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToListContents;
use PHPTerminal\Base;
use ReflectionClass;

class Terminal extends Base
{
    public $whereAt = 'disable';

    public $execCommandsList = [];

    public $module = 'base';

    protected $banner;

    protected $account = null;

    protected $hostname = 'PHPTerminal';

    protected $prompt = '> ';

    protected $exit = 'Bye!';

    protected $commands = [];

    protected $modules = [];

    protected $autoCompleteList = [];

    protected $helpList = [];

    protected $sessionTimeout = 3600;

    protected $loginAt;

    public function __construct($dataPath = null)
    {
        parent::__construct(false, $dataPath);

        $this->setActiveModule();
        $this->setHostname();
        $this->setBanner();

        try {
            $this->getAllCommands();
        } catch (\throwable | UnableToListContents $e) {
            var_dump($e);
            \cli\line("%W%1Error Loading commands, contact Developer!\n\n");

            exit(1);
        }

        system('clear');

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
                if ($this->whereAt === 'enable' || $this->whereAt === 'config') {
                    if ($this->account) {
                        $path = $this->checkHistoryPath();

                        if ($path) {
                            readline_write_history($path . $this->account['id']);
                        }
                    }
                }

                break;
            } else if ($command === 'exit') {
                if ($this->whereAt === 'disable') {
                    break;
                } else {
                    if ($this->whereAt !== 'enable') {
                        $this->whereAt = 'enable';
                        $this->prompt = '# ';
                    } else {
                        if ($this->account) {
                            $path = $this->checkHistoryPath();

                            if ($path) {
                                readline_write_history($path . $this->account['id']);
                            }
                        }

                        $this->account = null;
                        $this->whereAt = 'disable';
                        $this->prompt = '> ';
                    }
                }
            } else if (str_contains($command, '?') || $command === '?' || $command === 'help') {
                $this->showHelp();
            } else if (checkCtype($command, 'alnum', ['/',' ','-'])) {
                if (!$this->searchCommand(trim(strtolower($command)))) {
                    echo "Command " . trim($command) . " not found!\n";
                } else {
                    readline_add_history($command);
                }
            } else if ($command && $command !== '') {
                echo "Command " . trim($command) . " not found!\n";
            }

            $this->run();
        }

        $this->quit();
    }

    protected function quit()
    {
        \cli\line('');
        \cli\line('Bye!');
        \cli\line('');

        exit(0);
    }

    protected function showHelp()
    {
        if (isset($this->helpList[$this->whereAt]) &&
            count($this->helpList[$this->whereAt]) > 0
        ) {
            \cli\line('');
            foreach ($this->helpList[$this->whereAt] as $moduleName => $moduleCommands) {
                \cli\line("%y" . strtoupper($moduleName) . " MODULE COMMANDS%W");
                $table = new \cli\Table();
                $table->setHeaders(['Available Commands', 'Description']);
                foreach ($moduleCommands as &$moduleCommand) {
                    if (strtolower($moduleCommand[0]) === '') {
                        $moduleCommand[0] = '%y' . strtoupper($moduleCommand[1]) . '%w';
                        $moduleCommand[1] = '';
                    }
                }
                $table->setRows($moduleCommands);
                $table->setRenderer(new \cli\table\Ascii([25, 100]));
                $table->display();
                \cli\line('');
            }
        }
    }

    protected function searchCommand($command)
    {
        if (isset($this->execCommandsList[$this->whereAt]) &&
            count($this->execCommandsList[$this->whereAt]) > 0
        ) {
            foreach ($this->execCommandsList[$this->whereAt] as $commands) {
                if (str_starts_with(strtolower($command), strtolower($commands['command']))) {
                    try {
                        return $this->execCommand($command, $commands);
                    } catch (\Exception $e) {
                        \cli\line("%r" . $e->getMessage() . "%W");

                        return true;
                    }
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
            if ($this->commandsData->responseMessage && $this->commandsData->responseMessage !== '') {
                \cli\line($color . $this->commandsData->responseMessage);
            }
            \cli\out("%W");

            if ($this->commandsData->responseData && count($this->commandsData->responseData) > 0) {
                if ($this->commandsData->responseDataIsList) {
                    if ($this->commandsData->showAsTable) {
                        $responseHeaders = [];
                        $responseDataRows = [];
                    }

                    \cli\line("%y" . strtoupper(array_key_first($this->commandsData->responseData)) . "%W");

                    foreach ($this->commandsData->responseData as $responseKey => $responseValues) {
                        $responseValues = array_values($responseValues);
                        $responseData = true_flatten($responseValues);

                        $initialKey = 0;

                        foreach ($responseData as $key => $value) {
                            if ($value === null || $value === '') {
                                $value = 'null';
                            }

                            //true_flatten add the parent key to key as [parentKey_key], we remove that and use that to differentiate between different data.
                            $key = explode(' > ', $key);

                            if ($this->commandsData->showAsTable) {
                                $rowKey = (int) $key[0];
                            }

                            if ((int) $key[0] !== $initialKey) {
                                $initialKey = (int) $key[0];

                                if (!$this->commandsData->showAsTable) {
                                    \cli\line("");
                                }
                            }

                            array_shift($key);
                            $key = join('_', $key);

                            if (count($this->commandsData->replaceColumnNames) > 0 &&
                                isset($this->commandsData->replaceColumnNames[$key])
                            ) {
                                $key = $this->commandsData->replaceColumnNames[$key];
                            }

                            if ($this->commandsData->showAsTable) {
                                if (!in_array($key, $responseHeaders) && in_array($key, $this->commandsData->showColumns)) {
                                    array_push($responseHeaders, $key);
                                }

                                if (in_array($key, $this->commandsData->showColumns)) {
                                    if (!isset($responseDataRows[$rowKey])) {
                                        $responseDataRows[$rowKey] = [];
                                    }
                                    array_push($responseDataRows[$rowKey], $value);
                                }
                            } else {
                                \cli\line("%b$key : %W$value");
                            }
                        }
                    }

                    if ($this->commandsData->showAsTable) {//Draw Table here
                        $table = new \cli\Table();
                        $table->setHeaders($responseHeaders);
                        $table->setRows($responseDataRows);
                        $table->setRenderer(new \cli\table\Ascii($this->commandsData->columnsWidths));
                        $table->display();
                    }
                } else {
                    $responseData = true_flatten($this->commandsData->responseData);

                    foreach ($responseData as $key => $value) {
                        if ($value === null || $value === '') {
                            $value = 'null';
                        }

                        if (count($this->commandsData->replaceColumnNames) > 0 &&
                            isset($this->commandsData->replaceColumnNames[$key])
                        ) {
                            $key = $this->commandsData->replaceColumnNames[$key];
                        }

                        \cli\line("%b$key : %W$value");
                    }
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

    public function setActiveModule()
    {
        if (isset($this->config['active_module'])) {
            $this->module = strtolower($this->config['active_module']);
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
            } else {
                $this->banner = "%B" . str_replace('\\\\', '\\', $this->config['banner']) . "%W";
            }
        } else {
            $this->banner = "%B" . str_replace('\\\\', '\\', $this->config['banner']) . "%W";
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

    public function getAllCommands()
    {
        if (!isset($this->config['modules']) ||
            (isset($this->config['modules']) && count($this->config['modules']) === 0)
        ) {
            return;
        }

        $this->commands = [];
        $this->modules = [];

        foreach ($this->config['modules'] as $module) {
            if ($module['name'] === 'base') {
                $module['location'] = base_path('src/BaseModules/');
            }

            if ($module['name'] !== 'base' &&
                strtolower($this->module) !== strtolower($module['name'])
            ) {
                continue;
            }

            $this->setLocalContent(false, $module['location']);

            $modulesFiles =
                $this->localContent->listContents('.', true)
                ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
                ->map(fn (StorageAttributes $attributes) => $attributes->path())
                ->toArray();

            if (count($modulesFiles) > 0) {
                foreach ($modulesFiles as $moduleFile) {
                    $moduleFileNamespace = extractLineFromFile($module['location'] . $moduleFile, 'namespace');
                    $moduleFilePath = $moduleFile;
                    $moduleFile = str_replace('.php', '', $moduleFile);
                    $moduleFile = explode('/', $moduleFile);
                    $moduleFileNamespace = '\\' . $moduleFileNamespace . '\\' . $moduleFile[array_key_last($moduleFile)];

                    try {
                        if (!class_exists($moduleFileNamespace)) {
                            include $module['location'] . $moduleFilePath;
                        }

                        $moduleInit = (new $moduleFileNamespace())->init($this, null);

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
                                    $moduleArr['module_name'] = $module['name'];
                                }
                            }
                        }
                    } catch (\throwable $e) {
                        throw $e;
                    }
                }
            }
        }

        $this->autoCompleteList = [];
        $this->helpList = [];

        foreach ($this->modules as $moduleClass => $modulesArr) {
            foreach ($modulesArr as $module) {
                if (!isset($this->autoCompleteList[$module['availableAt']])) {
                    $this->autoCompleteList[$module['availableAt']] = [];
                }
                if ($module['command'] !== '') {
                    array_push($this->autoCompleteList[$module['availableAt']], $module['command']);
                }

                if (!isset($this->helpList[$module['availableAt']][$module['module_name']])) {
                    $this->helpList[$module['availableAt']][$module['module_name']] = [];
                }

                array_push($this->helpList[$module['availableAt']][$module['module_name']], [$module['command'], $module['description']]);

                if (!isset($this->execCommandsList[$module['availableAt']])) {
                    $this->execCommandsList[$module['availableAt']] = [];
                }
                if ($module['command'] !== '') {
                    array_push($this->execCommandsList[$module['availableAt']], $module);
                }
            }
        }

        $this->setLocalContent();
    }

    public function checkHistoryPath()
    {
        if ($this->dataPath) {
            $path = $this->dataPath . 'var/terminal/history/';
        } else {
            $path = base_path('terminaldata/var/terminal/history/');
        }

        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true)) {
                return false;
            }
        }

        return $path;
    }
}