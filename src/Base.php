<?php

namespace PHPTerminal;

use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPTerminal\BaseModules\ConfigTerminal;
use PHPTerminal\CommandsData;
use SleekDB\Store;
use cli\progress\Bar;

abstract class Base
{
    public $commandsData;

    public $localContent;

    public $remoteWebContent;

    public $databaseDirectory;

    public $storeConfiguration;

    public $config;

    public $viaComposer = false;

    protected $progress;

    protected $configStore;

    protected $dataPath;

    public $trackCounter;

    public $trackTicksCounter;

    protected $microtime = 0;

    protected $memoryusage = 0;

    protected $microTimers = [];

    public function __construct($createRoot = false, $dataPath = null)
    {
        if ($dataPath) {
            $this->dataPath = $dataPath;

            $this->viaComposer = true;
        }

        $this->checkTerminalPath();

        $this->commandsData = new CommandsData;

        $this->setLocalContent($createRoot, $dataPath);

        $this->remoteWebContent = new Client(
            [
                'debug'           => false,
                'http_errors'     => true,
                'timeout'         => 10,
                'verify'          => false
            ]
        );

        $this->databaseDirectory =  $dataPath ? $dataPath . '/db/' : __DIR__ . '/../terminaldata/db/';

        $this->storeConfiguration =
        [
            "auto_cache"        => true,
            "cache_lifetime"    => null,
            "timeout"           => false,
            "primary_key"       => "id",
            "search"            =>
                [
                    "min_length"    => 2,
                    "mode"          => "or",
                    "score_key"     => "scoreKey",
                    "algorithm"     => \SleekDB\Query::SEARCH_ALGORITHM["hits"]
                ],
            "folder_permissions" => 0777
        ];

        $this->configStore = new Store("config", $this->databaseDirectory, $this->storeConfiguration);

        $this->config = $this->configStore->findById(1);

        if (!$this->config) {
            $this->config = $this->configStore->updateOrInsert(
                [
                    'id'                   => 1,
                    'hostname'              => 'phpterminal',
                    'idleTimeout'           => 3600,//1 Hr. minimum will be 1 min Max will be 3600 1Hr
                    'historyLimit'          => 2000,//Max 2000 lines
                    'active_module'         => 'base',
                    'command_ignore_chars'  => ['/',' ','-'],
                    'modules'               => [
                        'base'              => [
                            'name'          => 'base',
                            'package_name'  => 'phpterminal/phpterminal',
                            'description'   => 'PHP Terminal Base Module',
                            'location'      => __DIR__ . '/BaseModules/',
                            'version'       => 'viaGit',
                            'banner'        => 'Welcome to PHP Terminal!' . PHP_EOL . 'Type help or ? (question mark) for help.' . PHP_EOL,
                        ]
                    ],
                    'plugins'       => []
                ]
            );

            if ($this->viaComposer) {
                $config = (new ConfigTerminal())->init($this, null);

                $config->composerResync(false);
            }
        }

        if ((isset($this->config['modules']) && count($this->config['modules']) === 0) ||
            !isset($this->config['modules'])
        ) {
            $this->config['hostname'] = 'phpterminal';
            $this->config['idleTimeout'] = 3600;//1 Hr. minimum will be 1 min Max will be 3600 1Hr
            $this->config['historyLimit'] = 2000;//1 Hr. minimum will be 1 min Max will be 3600 1Hr
            $this->config['active_module'] = 'base';
            $this->config['command_ignore_chars'] = ['/',' ','-'];
            $this->config['modules']['base']['name'] = 'base';
            $this->config['modules']['base']['package_name'] = 'phpterminal/phpterminal';
            $this->config['modules']['base']['description'] = 'PHP Terminal Base Module';
            $this->config['modules']['base']['location'] = __DIR__ . '/BaseModules/';
            $this->config['modules']['base']['version'] = 'viaGit';
            $this->config['modules']['base']['banner'] = 'Welcome to PHP Terminal!' . PHP_EOL . 'Type help or ? (question mark) for help.' . PHP_EOL;

            if ($this->viaComposer) {
                $config = (new ConfigTerminal())->init($this, null);

                $config->composerResync(false);
            } else {
                $this->configStore->update($this->config);
            }
        }
    }

    public function getConfig()
    {
        return $this->configStore->findById(1);
    }

    public function updateConfig($config)
    {
        $this->config = array_replace($this->config, $config);

        $this->configStore->update($this->config);

        $this->config = $this->getConfig();
    }

    public function setLocalContent($createRoot = false, $dataPath = null)
    {
        $this->localContent = new Filesystem(
            new LocalFilesystemAdapter(
                $dataPath ?? __DIR__ . '/../',
                null,
                LOCK_EX,
                LocalFilesystemAdapter::SKIP_LINKS,
                null,
                $createRoot
            ),
            []
        );
    }

    public function addResponse(
        $responseMessage,
        int $responseCode = 0,
        $responseData = null,
        $responseDataIsList = false,
        $showColumns = [],
        $columnsWidths = []
    ) {
        $this->commandsData->responseMessage = $responseMessage;

        $this->commandsData->responseCode = $responseCode;

        if ($responseData !== null && is_array($responseData)) {
            $this->commandsData->responseData = $responseData;
        } else {
            $this->commandsData->responseData = [];
        }

        $this->commandsData->responseDataIsList = $responseDataIsList;

        if ($responseDataIsList &&
            ($this->displayMode === 'table' && (count($showColumns) === 0 || count($showColumns) > 10))
        ) {
            throw new \Exception('Showing data as table needs showColumns array set and only 10 columns can be shown. Contact developer!');
        }
        if ($responseDataIsList &&
            ($this->displayMode === 'table' && (count($columnsWidths) === 0 || count($columnsWidths) !== count($showColumns)))
        ) {
            throw new \Exception('Showing data as table needs columnsWidths array set and they should match the number of columns being displayed. Contact developer!');
        }

        $this->commandsData->showColumns = $showColumns;

        $this->commandsData->columnsWidths = $columnsWidths;
    }

    public function newProgress($endCounter, $processType = 'Downloading...')
    {
        $this->progress = new Bar($processType, $endCounter);

        $this->progress->display();
    }

    public function updateProgress($message)
    {
        $this->progress->tick(1, $message);
    }

    public function finishProgress()
    {
        $this->progress->finish();
    }

    public function inputToArray(
        array $inputFields,
        array $inputFieldsOptions = [],
        array $inputFieldsDefaults = [],
        array $inputFieldsCurrentValues = [],
        array $inputFieldsRequired = [],
        int $fieldReenterCount = 3,
        bool $showHint = true
    ) {
        if ($showHint) {
            \cli\line('');
            \cli\line('%bHit Esc+enter key anytime to quit form.');
            \cli\line('%bHit enter for next field. If previous value is defined, no need to re-enter the value.');
            \cli\line('%bIf default value is defined, enter few characters and hit tab to autofill and move to next field.');
            \cli\line('%bEnter null to remove previous value.');
            \cli\line('%w');
        }

        $outputArr = [];
        $registerEscape = false;

        foreach ($inputFields as $inputField) {
            \cli\line('');
            $inputFieldInputCounter = 1;

            $inputFieldArr = [];
            $isSecret = false;

            readline_callback_handler_install("", function () {});
            if (str_contains($inputField, '__secret')) {
                $inputField = str_replace('__secret', '', $inputField);
                $isSecret = true;
            }

            if (isset($inputFieldsOptions[$inputField])) {
                $options = $inputFieldsOptions[$inputField];

                \cli\line('%bOPTIONS: %m[' . join(' | ', $options) . ']%w');
                \cli\line('');
            }

            if (isset($inputFieldsDefaults[$inputField])) {
                \cli\line('%bDEFAULT: %m[' . $inputFieldsDefaults[$inputField] . ']%w');
                \cli\line('');
            }

            $initial = true;
            while (true) {
                if ($initial) {
                    $initialValue = '';
                    if (isset($inputFieldsCurrentValues[$inputField])) {
                        $initialValue = '%c (' . $inputFieldsCurrentValues[$inputField] . ')%b';
                    } else if (isset($inputFieldsDefaults[$inputField])) {
                        $initialValue = '%c (' . $inputFieldsDefaults[$inputField] . ')%b';
                    }

                    \cli\out('%b' . strtoupper($inputField) . $initialValue . ' : %w');
                }

                $input = stream_get_contents(STDIN, 1);

                if (ord($input) == 10 || ord($input) == 13 || ord($input) == 9) {//Hit enter or tab key
                    if ($registerEscape) {
                        \cli\line('');
                        \cli\line('');
                        \cli\line('%rTerminated!%w');
                        \cli\line('');

                        readline_callback_handler_remove();

                        return false;
                    } else {
                        $registerEscape = false;
                    }

                    $outputArr[$inputField] = join($inputFieldArr);

                    if (ord($input) == 9) {
                        if (isset($inputFieldsDefaults[$inputField]) &&
                            $outputArr[$inputField] !== '' &&
                            str_starts_with($inputFieldsDefaults[$inputField], $outputArr[$inputField])
                        ) {
                            $strOutput = str_replace($outputArr[$inputField], '', $inputFieldsDefaults[$inputField]);

                            $outputArr[$inputField] = $inputFieldsDefaults[$inputField];

                            fwrite(STDOUT, $strOutput);
                        } else {
                            $initial = false;

                            continue;
                        }
                    }

                    if ($outputArr[$inputField] === '' &&
                        isset($inputFieldsCurrentValues[$inputField])
                    ) {
                        $outputArr[$inputField] = $inputFieldsCurrentValues[$inputField];
                    }

                    if ($outputArr[$inputField] === '') {
                        if ($inputFieldInputCounter < $fieldReenterCount) {
                            \cli\line('');
                            $inputFieldInputCounter++;

                            $outputArr = [];
                            $inputFieldArr = [];
                            $initial = true;

                            continue;
                        } else {
                            readline_callback_handler_remove();

                            \cli\line('');

                            if (count($inputFieldsRequired) > 0 && in_array($inputField, $inputFieldsRequired)) {
                                \cli\line('');
                                \cli\line('%rField : ' . $inputField  . ' is a required field and cannot be empty. Terminated!%w');
                                \cli\line('');
                            } else {
                                \cli\line('');
                                \cli\line('%rMax re-enter counter reached. Terminated!%w');
                                \cli\line('');
                            }

                            return false;
                        }
                    }

                    if ($outputArr[$inputField] === 'null') {
                        if (count($inputFieldsRequired) > 0 &&
                            isset($inputFieldsRequired[$inputField]) &&
                            $inputFieldsRequired[$inputField] === true
                        ) {
                            \cli\line('');
                            \cli\line('%rField : ' . $inputField  . ' is a required field and cannot be null!%w');
                            $outputArr = [];
                            $inputFieldArr = [];
                            $initial = true;

                            continue;
                        }
                    }

                    if (isset($inputFieldsOptions[$inputField]) && is_array($inputFieldsOptions[$inputField])) {
                        if ($outputArr[$inputField] !== 'null' &&
                            !in_array($outputArr[$inputField], $inputFieldsOptions[$inputField])
                        ) {
                            \cli\line('');
                            \cli\line('%rError: ' . strtoupper($inputField) . ' should only contain one of the options. HINT: input is case sensitive.');

                            $outputArr = [];
                            $inputFieldArr = [];
                            $initial = true;

                            continue;
                        }
                    }

                    break;
                } else if (ord($input) == 27) {//Escape key pressed
                    $registerEscape = true;

                    $initial = false;

                    continue;
                } else if (ord($input) == 127) {
                    if (count($inputFieldArr) === 0) {
                        continue;
                    }
                    array_pop($inputFieldArr);
                    fwrite(STDOUT, chr(8));
                    fwrite(STDOUT, "\033[0K");
                    $registerEscape = false;
                } else {
                    $inputFieldArr[] = $input;

                    if ($isSecret) {
                        fwrite(STDOUT, '*');
                    } else {
                        fwrite(STDOUT, $input);
                    }

                    $initial = false;
                    $registerEscape = false;
                }
            }

            readline_callback_handler_remove();
        }

        \cli\line("");

        return $outputArr;
    }

    public function downloadData($url, $sink)
    {
        $this->trackCounter = 0;
        $this->trackTicksCounter = 0;

        $download = $this->remoteWebContent->request(
            'GET',
            $url,
            [
                'progress' => function(
                    $downloadTotal,
                    $downloadedBytes,
                    $uploadTotal,
                    $uploadedBytes
                ) {
                    if ($downloadTotal === 0 || $downloadedBytes === 0) {
                        return;
                    }

                    //Trackcounter is needed as guzzelhttp runs this in a while loop causing too many updates with same download count.
                    //So this way, we only update progress when there is actually an update.
                    if ($downloadedBytes === $this->trackCounter) {
                        return;
                    }

                    $this->trackCounter = $downloadedBytes;

                    if (!$this->progress) {
                        $this->newProgress(100);
                    }

                    if ($downloadedBytes === $downloadTotal) {
                        if ($this->progress) {
                            $this->updateProgress('Downloading file ' . '... (' . $downloadTotal . '/' . $downloadTotal . ')');

                            $this->finishProgress();

                            $this->progress = null;
                        }
                    } else {
                        $downloadPercentTicks = (int) (($downloadedBytes * 100) / $downloadTotal);

                        if ($downloadPercentTicks > $this->trackTicksCounter) {
                            $this->trackTicksCounter = $downloadPercentTicks;

                            $this->updateProgress('Downloading file ' . '... (' . $downloadedBytes . '/' . $downloadTotal . ')');
                        }
                    }
                },
                'verify'            => false,
                'connect_timeout'   => 5,
                'timeout'           => 360,
                'sink'              => $sink
            ]
        );


        if ($download->getStatusCode() === 200) {
            $this->addResponse('Download file from URL: ' . $url);

            return $download;
        }

        $this->addResponse('Download resulted in : ' . $download->getStatusCode(), 1);

        return false;
    }

    public function getMicroTimer()
    {
        return $this->microTimers;
    }

    public function setActiveModule()
    {
        if (isset($this->config['active_module'])) {
            $this->module = strtolower($this->config['active_module']);
        }
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

    public function setCommandIgnoreChars(array $chars)
    {
        $this->config['command_ignore_chars'] = array_unique(array_merge($this->config['command_ignore_chars'], $chars));

        $this->updateConfig($this->config);
    }

    public function getCommandIgnoreChars()
    {
        return $this->config['command_ignore_chars'];
    }
    protected function checkTerminalPath()
    {
        if (!is_dir(base_path('terminaldata/'))) {
            if (!mkdir(base_path('terminaldata/'), 0777, true)) {
                return false;
            }
        }

        return true;
    }

    protected function setMicroTimer($reference, $calculateMemoryUsage = false)
    {
        $microtime['reference'] = $reference;

        if ($this->microtime === 0) {
            $microtime['difference'] = 0;
            $this->microtime = microtime(true);
        } else {
            $now = microtime(true);
            $microtime['difference'] = $now - $this->microtime;
            $this->microtime = $now;
        }

        if ($calculateMemoryUsage) {
            if ($this->memoryusage === 0) {
                $microtime['memoryusage'] = 0;
                $this->memoryusage = memory_get_usage();
            } else {
                $currentMemoryUsage = memory_get_usage();
                $microtime['memoryusage'] = $this->getMemUsage($currentMemoryUsage - $this->memoryusage);
                $this->memoryusage = $currentMemoryUsage;
            }
        }

        array_push($this->microTimers, $microtime);
    }

    protected function getMemUsage($bytes)
    {
        $unit=array('b','kb','mb','gb','tb','pb');

        return @round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2).' '.$unit[$i];
    }
}