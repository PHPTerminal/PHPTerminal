<?php

use PHPTerminal\Terminal;

include 'vendor/autoload.php';

try {
    if (PHP_SAPI === 'cli') {
        (new Terminal())->run();
    } else {
        echo "This is a CLI tool. From CLI, type ./phpterminal to access the terminal.";

        exit;
    }
} catch (\throwable $e) {
    echo $e->getMessage() . PHP_EOL;
}