<?php

use Laraserve\Brew;
use Laraserve\Laraserve;
use Laraserve\Filesystem;
use Laraserve\Configuration;
use function Laraserve\user;
use function Laraserve\resolve;
use function Laraserve\swap;
use Illuminate\Container\Container;

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $_SERVER['SUDO_USER'] = user();

        Container::setInstance(new Container);
    }


    public function tearDown()
    {
        Mockery::close();
    }


    public function test_configuration_directory_is_created_if_it_doesnt_exist()
    {
        $files = Mockery::mock(Filesystem::class.'[ensureDirExists,isDir]');
        $files->shouldReceive('ensureDirExists')->once()->with(preg_replace('~/laraserve$~', '', LARASERVE_HOME_PATH), user());
        $files->shouldReceive('ensureDirExists')->once()->with(LARASERVE_HOME_PATH, user());
        $files->shouldReceive('isDir')->once();
        swap(Filesystem::class, $files);
        resolve(Configuration::class)->createConfigurationDirectory();
    }


    public function test_drivers_directory_is_created_with_sample_driver_if_it_doesnt_exist()
    {
        $files = Mockery::mock(Filesystem::class.'[isDir,mkdirAsUser,putAsUser]');
        $files->shouldReceive('isDir')->with(LARASERVE_HOME_PATH.'/Drivers')->andReturn(false);
        $files->shouldReceive('mkdirAsUser')->with(LARASERVE_HOME_PATH.'/Drivers');
        $files->shouldReceive('putAsUser');
        swap(Filesystem::class, $files);
        resolve(Configuration::class)->createDriversDirectory();
    }

    public function test_log_directory_is_created_with_log_files_if_it_doesnt_exist()
    {
        $files = Mockery::mock(Filesystem::class.'[ensureDirExists,touch]');
        $files->shouldReceive('ensureDirExists')->with(LARASERVE_HOME_PATH.'/Log', user());
        $files->shouldReceive('touch')->once();
        swap(Filesystem::class, $files);
        resolve(Configuration::class)->createLogDirectory();
    }

    public function test_add_path_adds_a_path_to_the_paths_array_and_removes_duplicates()
    {
        $config = Mockery::mock(Configuration::class.'[read,write]', [new Filesystem]);
        $config->shouldReceive('read')->andReturn([
            'paths' => ['path-1', 'path-2'],
        ]);
        $config->shouldReceive('write')->with([
            'paths' => ['path-1', 'path-2', 'path-3'],
        ]);
        $config->addPath('path-3');

        $config = Mockery::mock(Configuration::class.'[read,write]', [new Filesystem]);
        $config->shouldReceive('read')->andReturn([
            'paths' => ['path-1', 'path-2', 'path-3'],
        ]);
        $config->shouldReceive('write')->with([
            'paths' => ['path-1', 'path-2', 'path-3'],
        ]);
        $config->addPath('path-3');
    }


    public function test_paths_may_be_removed_from_the_configuration()
    {
        $config = Mockery::mock(Configuration::class.'[read,write]', [new Filesystem]);
        $config->shouldReceive('read')->andReturn([
            'paths' => ['path-1', 'path-2'],
        ]);
        $config->shouldReceive('write')->with([
            'paths' => ['path-1'],
        ]);
        $config->removePath('path-2');
    }


    public function test_prune_removes_directories_from_paths_that_no_longer_exist()
    {
        $files = Mockery::mock(Filesystem::class.'[exists,isDir]');
        swap(Filesystem::class, $files);
        $files->shouldReceive('exists')->with(LARASERVE_HOME_PATH.'/config.json')->andReturn(true);
        $files->shouldReceive('isDir')->with('path-1')->andReturn(true);
        $files->shouldReceive('isDir')->with('path-2')->andReturn(false);
        $config = Mockery::mock(Configuration::class.'[read,write]', [$files]);
        $config->shouldReceive('read')->andReturn([
            'paths' => ['path-1', 'path-2'],
        ]);
        $config->shouldReceive('write')->with([
            'paths' => ['path-1'],
        ]);
        $config->prune();
    }


    public function test_prune_doesnt_execute_if_configuration_directory_doesnt_exist()
    {
        $files = Mockery::mock(Filesystem::class.'[exists]');
        swap(Filesystem::class, $files);
        $files->shouldReceive('exists')->with(LARASERVE_HOME_PATH.'/config.json')->andReturn(false);
        $config = Mockery::mock(Configuration::class.'[read,write]', [$files]);
        $config->shouldReceive('read')->never();
        $config->shouldReceive('write')->never();
        $config->prune();
    }


    public function test_update_key_updates_the_specified_configuration_key()
    {
        $config = Mockery::mock(Configuration::class.'[read,write]', [new Filesystem]);
        $config->shouldReceive('read')->once()->andReturn(['foo' => 'bar']);
        $config->shouldReceive('write')->once()->with(['foo' => 'bar', 'bar' => 'baz']);
        $config->updateKey('bar', 'baz');
    }


    public function test_trust_adds_the_sudoer_files()
    {
        $files = Mockery::mock(Filesystem::class.'[ensureDirExists,put]');
        $files->shouldReceive('ensureDirExists')->with('/etc/sudoers.d')->twice();
        $files->shouldReceive('put')->twice();
        swap(Filesystem::class, $files);
        resolve(Brew::class)->createSudoersEntry();
        resolve(Laraserve::class)->createSudoersEntry();
    }
}
