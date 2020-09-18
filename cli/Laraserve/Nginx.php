<?php

namespace Laraserve;

use DomainException;

class Nginx
{
    var $brew;
    var $cli;
    var $files;
    var $configuration;
    var $site;
    const NGINX_CONF = '/usr/local/etc/nginx/nginx.conf';

    /**
     * Create a new Nginx instance.
     *
     * @param  Brew  $brew
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     * @param  Configuration  $configuration
     * @param  Site  $site
     * @return void
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files,
                         Configuration $configuration, Site $site)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->site = $site;
        $this->files = $files;
        $this->configuration = $configuration;
    }

    /**
     * Install the configuration files for Nginx.
     *
     * @return void
     */
    function install()
    {
        if (!$this->brew->hasInstalledNginx()) {
            $this->brew->installOrFail('nginx', []);
        }

        $this->installConfiguration();
        $this->installServer();
        $this->installNginxDirectory();
    }

    /**
     * Install the Nginx configuration file.
     *
     * @return void
     */
    function installConfiguration()
    {
        info('Installing nginx configuration...');

        $contents = $this->files->get(__DIR__.'/../stubs/nginx.conf');

        $this->files->putAsUser(
            static::NGINX_CONF,
            str_replace(['LARASERVE_USER', 'LARASERVE_HOME_PATH'], [user(), LARASERVE_HOME_PATH], $contents)
        );
    }

    /**
     * Install the Laraserve Nginx server configuration file.
     *
     * @return void
     */
    function installServer()
    {
        $this->files->ensureDirExists('/usr/local/etc/nginx/laraserve');

        $this->files->putAsUser(
            '/usr/local/etc/nginx/laraserve/laraserve.conf',
            str_replace(
                ['LARASERVE_HOME_PATH', 'LARASERVE_SERVER_PATH', 'LARASERVE_STATIC_PREFIX'],
                [LARASERVE_HOME_PATH, LARASERVE_SERVER_PATH, LARASERVE_STATIC_PREFIX],
                $this->files->get(__DIR__.'/../stubs/laraserve.conf')
            )
        );

        $this->files->putAsUser(
            '/usr/local/etc/nginx/fastcgi_params',
            $this->files->get(__DIR__.'/../stubs/fastcgi_params')
        );
    }

    /**
     * Install the Nginx configuration directory to the ~/.config/laraserve directory.
     *
     * This directory contains all site-specific Nginx servers.
     *
     * @return void
     */
    function installNginxDirectory()
    {
        info('Installing nginx directory...');

        if (! $this->files->isDir($nginxDirectory = LARASERVE_HOME_PATH.'/Nginx')) {
            $this->files->mkdirAsUser($nginxDirectory);
        }

        $this->files->putAsUser($nginxDirectory.'/.keep', "\n");

        $this->rewriteSecureNginxFiles();
    }

    /**
     * Check nginx.conf for errors.
     */
    private function lint()
    {
        $this->cli->run(
            'sudo nginx -c '.static::NGINX_CONF.' -t',
            function ($exitCode, $outputMessage) {
                throw new DomainException("Nginx cannot start; please check your nginx.conf [$exitCode: $outputMessage].");
            }
        );
    }

    /**
     * Generate fresh Nginx servers for existing secure sites.
     *
     * @return void
     */
    function rewriteSecureNginxFiles()
    {
        $tld = $this->configuration->read()['tld'];

        $this->site->resecureForNewTld($tld, $tld);
    }

    /**
     * Restart the Nginx service.
     *
     * @return void
     */
    function restart()
    {
        $this->lint();

        $this->brew->restartService($this->brew->nginxServiceName());
    }

    /**
     * Stop the Nginx service.
     *
     * @return void
     */
    function stop()
    {
        info('Stopping nginx...');

        $this->cli->quietly('sudo brew services stop '. $this->brew->nginxServiceName());
    }

    /**
     * Forcefully uninstall Nginx.
     *
     * @return void
     */
    function uninstall()
    {
        $this->brew->stopService(['nginx', 'nginx-full']);
        $this->brew->uninstallFormula('nginx nginx-full');
        $this->cli->quietly('rm -rf /usr/local/etc/nginx /usr/local/var/log/nginx');
    }
}
