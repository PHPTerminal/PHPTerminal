<?php

namespace PHPTerminal;

use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use PHPTerminal\CommandsData;
use SleekDB\Store;
use cli\progress\Bar;

abstract class Base
{
    public $commandsData;

    public $localContent;

    public $remoteWebContent;

    protected $progress;

    protected $settings = [];

    public $databaseDirectory;

    public $storeConfiguration;

    protected $configStore;

    public $config;

    public function __construct($createRoot = false, $dataPath = null)
    {
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
            "primary_key"       => "_id",
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
                    '_id'           => 1,
                    'hostname'      => 'phpterminal',
                    'banner'        => 'Welcome to PHP Terminal!\n\n"Type help or ? (question mark) for help at any time\n\nEnter command and ? (question mark) for specific command help/options\n',
                    'modules'       => [
                        'base'      => [
                            'name'          => 'base',
                            'description'   => 'Base Module',
                            'type'          => 'location',//location/package
                            'location'      => __DIR__ . '/BaseCommands/'
                        ]
                    ]
                ]
            );
        }
    }

    public function getConfig()
    {
        return $this->config;
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

    public function addResponse($responseMessage, int $responseCode = 0, $responseData = null)
    {
        $this->commandsData->responseMessage = $responseMessage;

        $this->commandsData->responseCode = $responseCode;

        if ($responseData !== null && is_array($responseData)) {
            $this->commandsData->responseData = $responseData;
        } else {
            $this->commandsData->responseData = [];
        }
    }

    // protected function newProgress($processType = 'Downloading...')
    // {
    //     $this->progress =
    //         new Bar($processType,
    //                 ((bool) $this->settings['--resume'] && $this->resumeFrom > 0) ?
    //                 ($this->hashRangesEnd - $this->resumeFrom) :
    //                 $this->hashRangesEnd
    //         );

    //     $this->progress->display();
    // }

    // public function updateProgress($message)
    // {
    //     $this->progress->tick(1, $message);
    // }

    // protected function finishProgress()
    // {
    //     $this->progress->finish();
    // }

    // protected function writeToFile($file, $hash)
    // {
    //     try {
    //         $separator = ',';

    //         if (isset($this->settings['--type']) &&
    //             ($file === $this->settings['--type'] . 'checkfile.txt' || $file === $this->settings['--type'] . 'pool.txt')
    //         ) {
    //             if ($file === $this->settings['--type'] . 'pool.txt') {
    //                 $separator = PHP_EOL;
    //             }

    //             $fileLocation = $file;
    //         } else {
    //             $fileLocation = 'logs/' . $this->now . '/' . $file;
    //         }

    //         if ($this->localContent->fileExists($fileLocation)) {
    //             @file_put_contents(__DIR__ . '/../data/' . $fileLocation, $hash . $separator, FILE_APPEND | LOCK_EX);
    //         } else {
    //             $this->localContent->write($fileLocation, $hash . $separator);
    //         }

    //         return true;
    //     } catch (UnableToCheckExistence | UnableToWriteFile | FilesystemException $e) {
    //         \cli\line('%r' . $e->getMessage() . '%w');

    //         exit;
    //     }
    // }
    //
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