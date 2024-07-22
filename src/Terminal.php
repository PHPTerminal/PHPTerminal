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

    protected $idleTimeout;

    protected $loginAt;

    protected $filterCommand;

    protected $filters;

    protected $displayMode = 'table';

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
            \cli\line("%rError Loading commands, contact Developer!%w" . PHP_EOL . PHP_EOL);

            exit(1);
        }

        \cli\line("");
        \cli\line($this->banner);
        \cli\line("");

        $this->resetLastAccessTime();

        return $this;
    }

    public function run($terminated = false)
    {
        if ($this->connectionReachedIdleTimeout()) {
            $this->quit(true);
        }

        $this->resetLastAccessTime();

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
            $command = trim(strtolower($command));

            $this->filters = null;

            if (str_contains($command, '> list')) {
                $this->displayMode = 'list';

                $commandArr = explode('> list', $command);

                $command = trim($commandArr[0]);
            }

            if (str_contains($command, '| grep') || str_contains($command, '| grepkey')) {
                $this->filterCommand = $command;

                $commandArr = explode('|', $command);

                $command = trim($commandArr[0]);
                unset($commandArr[0]);

                $this->processFilters($commandArr);
            }

            if (str_starts_with($command, 'do')) {
                if ($this->whereAt === 'config') {
                    $this->whereAt = 'enable';
                    $command = trim(str_replace('do', '', $command));

                    $this->extractAllCommands(true, false, false);

                    if (str_contains($command, '?')) {
                        $this->showHelp();
                    } else {
                        if (!$this->searchCommand($command)) {
                            echo "Command " . $command . " not found!\n";
                        } else {
                            readline_add_history($command);
                        }
                    }

                    $this->whereAt = 'config';
                    $this->extractAllCommands(true, false, false);
                }
            } else if ($command === 'clear') {
                system('clear');
            } else if ($command === 'quit') {
                break;
            } else if ($command === 'exit') {
                if ($this->whereAt === 'disable') {
                    break;
                } else {
                    if ($this->whereAt !== 'enable') {
                        $this->whereAt = 'enable';
                        $this->prompt = '# ';
                    } else {
                        $this->writeHistory();

                        $this->account = null;
                        $this->whereAt = 'disable';
                        $this->prompt = '> ';
                        $this->setHostname();
                    }
                }
            } else if (str_contains($command, '?') || $command === '?' || $command === 'help') {
                $this->showHelp();
            } else if (checkCtype($command, 'alnum', ['/',' ','-','.',':'])) {
                if (!$this->searchCommand($command)) {
                    echo "Command " . $command . " not found!\n";
                } else {
                    if ($this->filters) {
                        if ($this->displayMode === 'list') {
                            readline_add_history($this->filterCommand . ' > list');
                        } else {
                            readline_add_history($this->filterCommand);
                        }
                    } else {
                        if ($this->displayMode === 'list') {
                            readline_add_history($command . ' > list');
                        } else {
                            readline_add_history($command);
                        }
                    }
                }
            } else if ($command && $command !== '') {
                echo "Command " . $command . " not found!\n";
            }

            $this->displayMode = 'table';

            $this->run();
        }

        $this->quit();
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function setActiveModule()
    {
        if (isset($this->config['active_module'])) {
            $this->module = strtolower($this->config['active_module']);
        }
    }

    public function setHostname()
    {
        $hostname = ($this->account ? $this->account['username'] . '@' : '') . $this->config['hostname'];

        $this->hostname = "\001\033[1;36m\002$hostname\001\033[0m\002:\001\033[1;35m\002$this->module\001\033[0m\002";
    }

    public function setBanner()
    {
        if ($this->module !== 'base') {
            if (isset($this->config['modules'][$this->module]['banner'])) {
                $this->config['modules'][$this->module]['banner'] = str_replace('\\\\', '\\', $this->config['modules'][$this->module]['banner']);

                $this->banner = "%B" . $this->config['modules'][$this->module]['banner'] . "%w";
            } else {
                $this->banner = "%B" . str_replace('\\\\', '\\', $this->config['modules']['base']['banner']) . "%w";
            }
        } else {
            $this->banner = "%B" . str_replace('\\\\', '\\', $this->config['modules']['base']['banner']) . "%w";
        }
    }

    public function getBanner()
    {
        return $this->banner;
    }

    public function setWhereAt($whereAt)
    {
        $this->whereAt = $whereAt;
    }

    public function getWhereAt()
    {
        return $this->whereAt;
    }

    public function resetLastAccessTime()
    {
        $this->updateConfig(['lastAccessAt' => time()]);
    }

    public function setIdleTimeout($timeout = 3600)
    {
        if ($timeout < 60) {
            $timeout = 60;
        }

        if ($timeout > 3600) {
            $timeout = 3600;
        }

        $this->config['idleTimeout'] = (int) $timeout;

        $this->updateConfig(['idleTimeout' => (int) $timeout]);
    }

    public function setHistoryLimit($limit = 2000)
    {
        if ($limit > 2000) {
            $limit = 2000;
        }

        $this->config['historyLimit'] = (int) $limit;

        $this->updateConfig(['historyLimit' => (int) $limit]);
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

        $this->setLocalContent();

        $this->extractAllCommands();
    }

    public function extractAllCommands($helpList = true, $autoCompleteList = true, $execCommandsList = true)
    {
        if ($helpList) {
            $this->helpList = [];
        }
        if ($autoCompleteList) {
            $this->autoCompleteList = [];
        }

        foreach ($this->modules as $moduleClass => $modulesArr) {
            foreach ($modulesArr as $module) {
                if ($autoCompleteList) {
                    if (!isset($this->autoCompleteList[$module['availableAt']])) {
                        $this->autoCompleteList[$module['availableAt']] = [];
                    }
                    if ($module['command'] !== '') {
                        array_push($this->autoCompleteList[$module['availableAt']], $module['command']);
                    }
                }

                if ($helpList) {
                    if (!isset($this->helpList[$module['availableAt']][$module['module_name']])) {
                        $this->helpList[$module['availableAt']][$module['module_name']] = [];
                    }

                    array_push($this->helpList[$module['availableAt']][$module['module_name']], [$module['command'], $module['description']]);
                }

                if ($execCommandsList) {
                    if (!isset($this->execCommandsList[$module['availableAt']])) {
                        $this->execCommandsList[$module['availableAt']] = [];
                    }
                    if ($module['command'] !== '') {
                        array_push($this->execCommandsList[$module['availableAt']], $module);
                    }
                }
            }
        }

        if ($autoCompleteList) {
            foreach (array_keys($this->autoCompleteList) as $whereAt) {//Add global to autocomplete
                foreach ($this->getGlobalCommands()['global']['base'] as $commandToAdd) {
                    array_push($this->autoCompleteList[$whereAt], $commandToAdd[0]);
                }
                if ($whereAt === 'config') {
                    foreach ($this->autoCompleteList['enable'] as $enableCommandKey => $enableCommand) {
                        if (recursive_array_search($enableCommand, $this->getGlobalCommands()['global']['base']) === false) {
                            array_push($this->autoCompleteList['config'], 'do ' . $enableCommand);
                        }
                    }
                } else {

                }
            }
        }

        //Add global to help
        if ($helpList) {
            $this->helpList = array_merge($this->helpList, $this->getGlobalCommands());
        }
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

    protected function getGlobalCommands()
    {
        return
        [
            'global' => [
                'base'  => [
                    [
                        "clear", "Clear screen"
                    ],
                    [
                        "exit", "Change to previous mode or quit terminal if in disable mode"
                    ],
                    [
                        "quit", "Quit Terminal"
                    ]
                ]
            ]
        ];
    }

    protected function connectionReachedIdleTimeout()
    {
        if (!isset($this->config['idleTimeout'])) {
            return false;
        }
        if (!isset($this->config['lastAccessAt'])) {
            return false;
        }

        $timediff = time() - (int) $this->config['lastAccessAt'];

        if ($timediff > (int) $this->config['idleTimeout']) {
            return true;
        }

        return false;
    }

    protected function quit($reachedIdleTimeout = false)
    {
        $this->writeHistory();

        \cli\line('');
        if ($reachedIdleTimeout) {
            \cli\line('%rConnection idle timeout reached!%w');
            \cli\line('');
        }
        \cli\line('%bBye!%w');
        \cli\line('');

        exit(0);
    }

    protected function writeHistory()
    {
        if ($this->account) {
            $path = $this->checkHistoryPath();

            if ($path) {
                $historyArr = readline_list_history();

                if ($historyArr && count($historyArr) > 0) {
                    if (count($historyArr) > ($this->config['historyLimit'] ?? 2000)) {
                        $historyArr = array_slice($historyArr, (count($historyArr) - ($this->config['historyLimit'] ?? 2000)), ($this->config['historyLimit'] ?? 2000), true);

                        readline_clear_history();

                        array_walk($historyArr, function($history) {
                            readline_add_history($history);
                        });
                    }
                }

                readline_write_history($path . $this->account['id']);

                readline_clear_history();
            }
        }
    }

    protected function processFilters(array $filters)
    {
        $this->filters['values'] = [];
        $this->filters['keys'] = [];

        foreach ($filters as $filter) {
            if (str_contains($filter, 'grepkey')) {
                if (trim(str_replace('grepkey', '', $filter)) !== '') {
                    array_push($this->filters['keys'], trim(str_replace('grepkey', '', $filter)));
                }
            } else if (str_contains($filter, 'grep')) {
                if (trim(str_replace('grep', '', $filter)) !== '') {
                    array_push($this->filters['values'], trim(str_replace('grep', '', $filter)));
                }
            }
        }

        if (count($this->filters['values']) === 0 && count($this->filters['keys']) === 0) {
            $this->filters = null;
        }
    }

    protected function showHelp()
    {
        if (isset($this->helpList[$this->whereAt]) &&
            count($this->helpList[$this->whereAt]) > 0
        ) {
            \cli\line('');

            foreach ($this->helpList[$this->whereAt] as $moduleName => $moduleCommands) {
                if (isset($this->filters['values']) &&
                    count($this->filters['values']) > 0 &&
                    isset($this->helpList[$this->whereAt][strtolower($this->filters['values'][0])]) &&
                    strtolower($this->filters['values'][0]) !== $moduleName
                ) {
                    continue;
                }

                \cli\line("%y" . strtoupper($moduleName) . " MODULE COMMANDS%w");
                $table = new \cli\Table();
                $table->setHeaders(['AVAILABLE COMMANDS', 'DESCRIPTION']);
                foreach ($moduleCommands as &$moduleCommand) {
                    if (strtolower($moduleCommand[0]) === '') {
                        $moduleCommand[0] = '%c' . strtoupper($moduleCommand[1]) . '%w';
                        $moduleCommand[1] = '';
                    }
                }
                $table->setRows($moduleCommands);
                $table->setRenderer(new \cli\table\Ascii([30, 125]));
                $table->display();
                \cli\line('%w');
            }

            if (isset($this->filters['values']) &&
                count($this->filters['values']) > 0 &&
                isset($this->helpList[$this->whereAt][strtolower($this->filters['values'][0])])
            ) {
                return true;
            }

            foreach ($this->helpList['global'] as $moduleName => $moduleCommands) {
                \cli\line("%yGLOBAL COMMANDS%w");
                $table = new \cli\Table();
                $table->setHeaders(['AVAILABLE COMMANDS', 'DESCRIPTION']);
                foreach ($moduleCommands as &$moduleCommand) {
                    if (strtolower($moduleCommand[0]) === '') {
                        $moduleCommand[0] = '%c' . strtoupper($moduleCommand[1]) . '%w';
                        $moduleCommand[1] = '';
                    }
                }
                $table->setRows($moduleCommands);
                $table->setRenderer(new \cli\table\Ascii([30, 125]));
                $table->display();
                \cli\line('%w');
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
                    } catch (\throwable $e) {
                        \cli\line("%r" . $e->getMessage() . "%w");

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

            $color = "%w";
            if ($this->commandsData->responseCode == 0) {
                $color = "%g";
            } else if ($this->commandsData->responseCode == 1) {
                $color = "%r";
            } else if ($this->commandsData->responseCode == 2) {
                $color = "%y";
            } else if ($this->commandsData->responseCode == 3) {
                $color = "%m";
            } else if ($this->commandsData->responseCode == 4) {
                $color = "%c";
            }

            \cli\line("");
            if ($this->commandsData->responseMessage && $this->commandsData->responseMessage !== '') {
                \cli\line($color . $this->commandsData->responseMessage);
            }
            \cli\line("%w");

            if ($this->commandsData->responseData && count($this->commandsData->responseData) > 0) {
                if ($this->commandsData->responseDataIsList) {
                    $this->commandsData->responseData = array_values($this->commandsData->responseData);
                    $responseHeaders = [];
                    $responseDataRows = [];

                    \cli\line("%y" . trim(strtoupper($command)) . " OUTPUT%w");

                    if ($this->filters) {
                        if (isset($this->filters['keys']) && count($this->filters['keys']) > 0) {
                            $columnsKeys = [];

                            foreach ($this->filters['keys'] as $keysKey => $keysValue) {
                                $columnKey = array_search(strtolower($keysValue), $this->commandsData->showColumns);

                                if ($columnKey !== false) {
                                    if (!in_array($columnKey, $columnsKeys)) {
                                        array_push($columnsKeys, $columnKey);
                                    }
                                }
                            }

                            if (count($columnsKeys) > 0) {
                                $headers = [];
                                foreach ($this->commandsData->showColumns as $showColumnKey => $showColumn) {
                                    if (in_array($showColumnKey, $columnsKeys)) {
                                        array_push($headers, $showColumn);
                                    }
                                }
                                $this->commandsData->showColumns = $headers;

                                $widths = [];
                                foreach ($this->commandsData->columnsWidths as $columnWidthKey => $columnWidth) {
                                    if (in_array($columnWidthKey, $columnsKeys)) {
                                        array_push($widths, $columnWidth);
                                    }
                                }
                                $this->commandsData->columnsWidths = $widths;
                            }
                        }
                    }

                    foreach ($this->commandsData->responseData as $responseKey => $responseValues) {
                        array_walk($responseValues, function(&$responseValue) {
                            $responseValue = array_replace(array_flip($this->commandsData->showColumns), $responseValue);
                        });

                        $responseValues = array_values($responseValues);
                        $responseData = true_flatten($responseValues);

                        foreach ($responseData as $key => $value) {
                            if ($value === null || $value === '') {
                                $value = 'null';
                            }

                            //true_flatten add the parent key to key as [parentKey_key],
                            //we remove that and use that to differentiate between different data.
                            $key = explode(' > ', $key);

                            $rowKey = (int) $key[0];
                            $key = $key[1];

                            if (!in_array($key, $responseHeaders) && in_array($key, $this->commandsData->showColumns)) {
                                array_push($responseHeaders, $key);
                            }

                            if (in_array($key, $this->commandsData->showColumns)) {
                                if (!isset($responseDataRows[$rowKey])) {
                                    $responseDataRows[$rowKey] = [];
                                }

                                if ($this->displayMode === 'table') {
                                    array_push($responseDataRows[$rowKey], $value);
                                } else if ($this->displayMode === 'list') {
                                    $responseDataRows[$rowKey][$key] = $value;
                                }
                            }
                        }
                    }

                    if ($this->filters) {
                        if (isset($this->filters['values']) && count($this->filters['values']) > 0) {
                            foreach ($responseDataRows as $responseDataRowsKey => $responseDataRow) {
                                if (isset($this->filters['values']) && count($this->filters['values']) === 1) {
                                    foreach ($responseDataRow as $rDR) {
                                        if (str_contains(strtolower($rDR), strtolower($this->filters['values'][0]))) {
                                            continue 2;
                                        }
                                    }
                                } else if (isset($this->filters['values']) && count($this->filters['values']) > 1) {
                                    foreach ($this->filters['values'] as $filterValue) {
                                        foreach ($responseDataRow as $rDR) {
                                            if (str_contains(strtolower($rDR), strtolower($filterValue))) {
                                                continue 3;
                                            }
                                        }
                                    }
                                }

                                unset($responseDataRows[$responseDataRowsKey]);
                            }

                            $responseDataRows = array_values($responseDataRows);
                        }
                    }

                    if (count($responseDataRows) > 0) {
                        if ($this->displayMode === 'table') {//Draw Table here
                            $table = new \cli\Table();

                            array_walk($responseHeaders, function(&$header) {
                                $header = strtoupper($header);
                            });

                            $table->setHeaders($responseHeaders);
                            $table->setRows($responseDataRows);
                            $table->setRenderer(new \cli\table\Ascii($this->commandsData->columnsWidths));
                            $table->display();
                        } else {//Show list here
                            foreach ($responseDataRows as $responseDataRow) {
                                foreach ($responseDataRow as $key => $value) {
                                    \cli\line('%b' . strtoupper($key) . ' : ' . '%w' . $value);
                                }
                                \cli\line("");
                            }
                        }
                    } else {
                        if ($this->filters) {
                            \cli\line("");
                            \cli\line("%rNo data found with filter(s)%w");
                        }
                    }
                } else {
                    \cli\line("%y" . trim(strtoupper($command)) . " OUTPUT%w");

                    $responseData = true_flatten($this->commandsData->responseData);

                    $filterFound = false;

                    foreach ($responseData as $key => $value) {
                        if ($value === null || $value === '') {
                            $value = 'null';
                        }

                        if ($this->filters) {
                            if (isset($this->filters['keys']) && count($this->filters['keys']) === 1) {
                                if (str_contains(strtolower($key), strtolower($this->filters['keys'][0]))) {
                                    \cli\line('%b' . strtoupper($key) . ' : ' . '%w' . $value);
                                    $filterFound = true;
                                }
                            } else if (isset($this->filters['keys']) && count($this->filters['keys']) > 1) {
                                foreach ($this->filters['keys'] as $filterKey) {
                                    if (str_contains(strtolower($key), strtolower($filterKey))) {
                                        \cli\line('%b' . strtoupper($key) . ' : ' . '%w' . $value);
                                        $filterFound = true;
                                    }
                                }
                            }
                            if (isset($this->filters['values']) && count($this->filters['values']) === 1) {
                                if (str_contains(strtolower($value), strtolower($this->filters['values'][0]))) {
                                    \cli\line('%b' . strtoupper($key) . ' : ' . '%w' . $value);
                                    $filterFound = true;
                                }
                            } else if (isset($this->filters['values']) && count($this->filters['values']) > 1) {
                                foreach ($this->filters['values'] as $filterValue) {
                                    if (str_contains(strtolower($value), strtolower($filterValue))) {
                                        \cli\line('%b' . strtoupper($key) . ' : ' . '%w' . $value);
                                        $filterFound = true;
                                    }
                                }
                            }
                        } else {

                            \cli\line('%b' . strtoupper($key) . ' : ' . '%w' . $value);
                        }
                    }

                    if ($this->filters && !$filterFound) {
                        \cli\line("%rNo data found with filter(s)%w");
                    }
                }
            }

            \cli\line("");

            return true;
        }

        return false;
    }

    protected function updateAutoComplete($do = false)
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
}