<?php
/**
 * File PhpMyAdmin.php.
 * @copyright 2020
 * @version 1.0
 */

namespace Laraserve;

class PhpMyAdmin
{

    var $brew, $cli, $files, $site;

    private $mysql      = 'mysql@5.7';
    private $path       = '/usr/local/share/phpmyadmin';
    private $config     = '/config.inc.php';
    private $repository = 'phpmyadmin';

    /**
     * Create a new PHP FPM class instance.
     *
     * @param Brew $brew
     * @param CommandLine $cli
     * @param Filesystem $files
     * @param Site $site
     */
    function __construct(Brew $brew, CommandLine $cli, Filesystem $files, Site $site)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->files = $files;
        $this->site = $site;
    }

    /**
     * Install and configure phpMyAdmin.
     *
     * @param string $tld
     * @return void
     */
    public function install($tld = '.test')
    {
        # Check if is installed mysql
        if(!$this->brew->installed($this->mysql))
        {
            info($this->mysql. " installing...");
            $this->brew->installOrFail($this->mysql);
        }else{
            info($this->mysql. " is pre-installed");
        }

        if(!$this->brew->installed($this->repository))
        {
            info('phpMyAdmin Installing...');
            $this->brew->installOrFail($this->repository);
            $this->configPma($tld);
            $this->linkPma();
        }else{
            info('phpMyAdmin is pre-installed');
        }
    }

    /**
     * Uninstall the phpmyadmin and mysql
     */
    public function uninstall()
    {
        info($this->mysql. " uninstalling");
        $this->cli->runAsUser('brew uninstall --force '.$this->mysql);
        info($this->mysql. ' is unistalled!!');
        info($this->repository. " uninstalling");
        $this->cli->runAsUser('brew uninstall --force '.$this->repository);
        info($this->repository. ' is unistalled!!');
    }

    /**
     * Link the phpmyadmin in server
     * @param $tld
     */
    public function linkPma($tld)
    {
        if($this->files->isDir($this->path)) {
            $this->site->link($this->repository, $this->path);
            info("Phpmyadmin has been linked ". $this->repository.$tld);
        }
    }

    /**
     * Configuration for phpmyadmin config
     */
    public function configPma()
    {
        $configFile = $this->path.$this->config;
        $needle = '[\'AllowNoPassword\'] = false';
        $replace = '[\'AllowNoPassword\'] = true';
        if($this->files->exists($configFile)) {
            $file = $this->files->get($configFile);

            if(str_contains($file, $needle)){
                $file = str_replace($needle, $replace, $file);
                $this->files->unlink($configFile);
                $this->files->appendAsUser($configFile, $file);
                info("Successfully has been replaced");
            }else{
                info("AllowNoPassword is already changed");
            }
        }
    }


}
