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
                    'id'            => 1,
                    'hostname'      => 'phpterminal',
                    'idleTimeout'   => 3600,//1 Hr. minimum will be 1 min Max will be 3600 1Hr
                    'historyLimit'  => 2000,//Max 2000 lines
                    'active_module' => 'base',
                    'modules'       => [
                        'base'      => [
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

    public function updateConfig($config)
    {
        $this->config = array_replace($this->config, $config);

        $this->configStore->update($this->config);
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

    public function inputToArray(array $inputFields, array $inputFieldsData = [])
    {
        $outputArr = [];

        foreach ($inputFields as $inputField) {
            $inputFieldArr = [];
            $isSecret = false;

            if (str_contains($inputField, '__secret')) {
                $inputField = str_replace('__secret', '', $inputField);
                $isSecret = true;
                readline_callback_handler_install("", function () {});
            }

            \cli\out("%b" . strtoupper($inputField) . (isset($inputFieldsData[$inputField]) ? '%c(' . $inputFieldsData[$inputField] . ')%b' : '') . ' : %w');

            while (true) {
                $input = stream_get_contents(STDIN, 1);

                if (ord($input) == 10 || ord($input) == 13) {
                    if ($isSecret) {
                        \cli\line("");
                    }
                    break;
                } else if (ord($input) == 27) {
                    return [];
                } else if (ord($input) == 127) {
                    if (count($inputFieldArr) === 0) {
                        continue;
                    }
                    array_pop($inputFieldArr);
                    fwrite(STDOUT, chr(8));
                    fwrite(STDOUT, "\033[0K");
                } else {
                    $inputFieldArr[] = $input;

                    if ($isSecret) {
                        fwrite(STDOUT, '*');
                    }
                }
            }

            $outputArr[$inputField] = join($inputFieldArr);

            if ($outputArr[$inputField] === '' &&
                isset($inputFieldsData[$inputField])
            ) {
                $outputArr[$inputField] = $inputFieldsData[$inputField];
            }

            if ($isSecret) {
                readline_callback_handler_remove();
            }
        }

        \cli\line("");


        return $outputArr;
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
}