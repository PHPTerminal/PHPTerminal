<?php

namespace PHPTerminal\BaseModules\Commands;

class Show
{
    protected $module;

    protected $terminal;

    public function __construct($module)
    {
        $this->module = $module;

        $this->terminal = $module->terminal;

        return $this;
    }

    public function getCommands()
    {
        return
            [
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "",
                    "description"   => "show commands",
                    "function"      => ""
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show run",
                    "description"   => "Show running configuration.",
                    "function"      => "show"
                ],
                [
                    "availableAt"   => "enable",
                    "command"       => "show installed",
                    "description"   => "show installed {plugins/modules}. Show a list of all installed plugins/modules.",
                    "function"      => "show"
                ]
            ];
    }

    public function showRun()
    {
        $savedConfiguration = $this->terminal->getConfig();

        unset($savedConfiguration['id']);

        $savedConfiguration['command_ignore_chars'] = join(',', $savedConfiguration['command_ignore_chars']);

        $this->terminal->addResponse('', 0, ['Running Configuration' => $savedConfiguration]);

        return true;
    }

    public function showInstalled(array $args)
    {
        if (!isset($args[0])) {
            $this->terminal->addResponse('Please provide needs to be checked, plugins or modules.', 1);

            return false;
        }

        if ($args[0] !== 'plugins' && $args[0] !== 'modules') {
            $this->terminal->addResponse('Either plugins or modules can be checked. Don\'t know what ' . $args[0] . ' is...', 1);

            return false;
        }

        if ($args[0] === 'modules') {
            $headers = ['name', 'package_name', 'version', 'location', 'description'];
            $columnsWidth = [10,50,10,40,40];
        } else if ($args[0] === 'plugins') {
            if (!isset($this->terminal->config['plugins']) ||
                (isset($this->terminal->config['plugins']) && count($this->terminal->config['plugins']) === 0)
            ) {
                \cli\line("");
                \cli\line("%yNo plugins available. Search plugins via composer in enable mode.%w");
                \cli\line("Run command : composer search plugins");
                \cli\line("");
                \cli\line("%yTo install new plugins via composer in config mode.%w");
                \cli\line("Run command : composer install plugin {plugin_name}");
                \cli\line("");

                return true;
            }

            $headers = ['name', 'package_name', 'version', 'class', 'description'];
            $columnsWidth = [10,50,10,30,50];
        }

        $this->terminal->addResponse(
            '',
            0,
            ['Installed ' . ucfirst($args[0]) => $this->terminal->config[$args[0]] ?? []],
            true,
            $headers,
            $columnsWidth
        );

        return true;
    }
}