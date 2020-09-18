<?php

namespace Laraserve;

use Httpful\Request;

class Laraserve
{
    var $cli, $files;

    var $laraserveBin = '/usr/local/bin/laraserve';

    /**
     * Create a new Laraserve instance.
     *
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     */
    function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Symlink the Laraserve Bash script into the user's local bin.
     *
     * @return void
     */
    function symlinkToUsersBin()
    {
        $this->unlinkFromUsersBin();

        $this->cli->runAsUser('ln -s "'.realpath(__DIR__.'/../../laraserve').'" '.$this->laraserveBin);
    }

    /**
     * Remove the symlink from the user's local bin.
     *
     * @return void
     */
    function unlinkFromUsersBin()
    {
        $this->cli->quietlyAsUser('rm '.$this->laraserveBin);
    }

    /**
     * Get the paths to all of the Laraserve extensions.
     *
     * @return array
     */
    function extensions()
    {
        if (! $this->files->isDir(LARASERVE_HOME_PATH.'/Extensions')) {
            return [];
        }

        return collect($this->files->scandir(LARASERVE_HOME_PATH.'/Extensions'))
                    ->reject(function ($file) {
                        return is_dir($file);
                    })
                    ->map(function ($file) {
                        return LARASERVE_HOME_PATH.'/Extensions/'.$file;
                    })
                    ->values()->all();
    }

    /**
     * Determine if this is the latest version of Laraserve.
     *
     * @param  string  $currentVersion
     * @return bool
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    function onLatestVersion($currentVersion)
    {
        $response = Request::get('https://api.github.com/repos/laravel/laraserve/releases/latest')->send();

        return version_compare($currentVersion, trim($response->body->tag_name, 'v'), '>=');
    }

    /**
     * Create the "sudoers.d" entry for running Laraserve.
     *
     * @return void
     */
    function createSudoersEntry()
    {
        $this->files->ensureDirExists('/etc/sudoers.d');

        $this->files->put('/etc/sudoers.d/laraserve', 'Cmnd_Alias LARASERVE = /usr/local/bin/laraserve *
%admin ALL=(root) NOPASSWD:SETENV: LARASERVE'.PHP_EOL);
    }

    /**
     * Remove the "sudoers.d" entry for running Laraserve.
     *
     * @return void
     */
    function removeSudoersEntry()
    {
        $this->cli->quietly('rm /etc/sudoers.d/laraserve');
    }

    /**
     * Run composer global diagnose
     */
    function composerGlobalDiagnose()
    {
        $this->cli->runAsUser('composer global diagnose');
    }

    /**
     * Run composer global update
     */
    function composerGlobalUpdate()
    {
        $this->cli->runAsUser('composer global update');
    }
}
