<?php

/**
 * Check the system's compatibility with Laraserve.
 */
$inTestingEnvironment = strpos($_SERVER['SCRIPT_NAME'], 'phpunit') !== false;

if (PHP_OS !== 'Darwin' && ! $inTestingEnvironment) {
    echo 'Laraserve only supports the Mac operating system.'.PHP_EOL;

    exit(1);
}

if (version_compare(PHP_VERSION, '5.6.0', '<')) {
    echo "Laraserve requires PHP 5.6 or later.";

    exit(1);
}

if (exec('which brew') == '' && ! $inTestingEnvironment) {
    echo 'Laraserve requires Homebrew to be installed on your Mac.';

    exit(1);
}
