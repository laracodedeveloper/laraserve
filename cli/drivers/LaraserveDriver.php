<?php

abstract class LaraserveDriver
{
    /**
     * Determine if the driver serves the request.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return bool
     */
    abstract public function serves($sitePath, $siteName, $uri);

    /**
     * Determine if the incoming request is for a static file.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string|false
     */
    abstract public function isStaticFile($sitePath, $siteName, $uri);

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string
     */
    abstract public function frontControllerPath($sitePath, $siteName, $uri);

    /**
     * Find a driver that can serve the incoming request.
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return LaraserveDriver|null
     */
    public static function assign($sitePath, $siteName, $uri)
    {
        $drivers = [];

        if ($customSiteDriver = static::customSiteDriver($sitePath)) {
            $drivers[] = $customSiteDriver;
        }

        $drivers = array_merge($drivers, static::driversIn(LARASERVE_HOME_PATH.'/Drivers'));

        $drivers[] = 'LaravelLaraserveDriver';

        $drivers[] = 'WordPressLaraserveDriver';
        $drivers[] = 'BedrockLaraserveDriver';
        $drivers[] = 'ContaoLaraserveDriver';
        $drivers[] = 'SymfonyLaraserveDriver';
        $drivers[] = 'CraftLaraserveDriver';
        $drivers[] = 'StatamicLaraserveDriver';
        $drivers[] = 'StatamicV1LaraserveDriver';
        $drivers[] = 'CakeLaraserveDriver';
        $drivers[] = 'SculpinLaraserveDriver';
        $drivers[] = 'JigsawLaraserveDriver';
        $drivers[] = 'KirbyLaraserveDriver';
        $drivers[] = 'KatanaLaraserveDriver';
        $drivers[] = 'JoomlaLaraserveDriver';
        $drivers[] = 'DrupalLaraserveDriver';
        $drivers[] = 'Concrete5LaraserveDriver';
        $drivers[] = 'Typo3LaraserveDriver';
        $drivers[] = 'NeosLaraserveDriver';
        $drivers[] = 'Magento2LaraserveDriver';

        $drivers[] = 'BasicLaraseveDriver';

        foreach ($drivers as $driver) {
            $driver = new $driver;

            if ($driver->serves($sitePath, $siteName, $driver->mutateUri($uri))) {
                return $driver;
            }
        }
    }

    /**
     * Get the custom driver class from the site path, if one exists.
     *
     * @param  string  $sitePath
     * @return string
     */
    public static function customSiteDriver($sitePath)
    {
        if (! file_exists($sitePath.'/LocalLaraserveDriver.php')) {
            return;
        }

        require_once $sitePath.'/LocalLaraserveDriver.php';

        return 'LocalLaraserveDriver';
    }

    /**
     * Get all of the driver classes in a given path.
     *
     * @param  string  $path
     * @return array
     */
    public static function driversIn($path)
    {
        if (! is_dir($path)) {
            return [];
        }

        $drivers = [];

        $dir = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($dir);
        $regex = new RegexIterator($iterator, '/^.+LaraserveDriver\.php$/i', RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            require_once $file[0];

            $drivers[] = basename($file[0], '.php');
        }

        return $drivers;
    }

    /**
     * Mutate the incoming URI.
     *
     * @param  string  $uri
     * @return string
     */
    public function mutateUri($uri)
    {
        return $uri;
    }

    /**
     * Serve the static file at the given path.
     *
     * @param  string  $staticFilePath
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return void
     */
    public function serveStaticFile($staticFilePath, $sitePath, $siteName, $uri)
    {
        /**
         * Back story...
         *
         * PHP docs *claim* you can set default_mimetype = "" to disable the default
         * Content-Type header. This works in PHP 7+, but in PHP 5.* it sends an
         * *empty* Content-Type header, which is significantly different than
         * sending *no* Content-Type header.
         *
         * However, if you explicitly set a Content-Type header, then explicitly
         * remove that Content-Type header, PHP seems to not re-add the default.
         *
         * I have a hard time believing this is by design and not coincidence.
         *
         * Burn. it. all.
         */
        header('Content-Type: text/html');
        header_remove('Content-Type');

        header('X-Accel-Redirect: /' . LARASERVE_STATIC_PREFIX . $staticFilePath);
    }

    /**
     * Determine if the path is a file and not a directory.
     *
     * @param  string  $path
     * @return bool
     */
    protected function isActualFile($path)
    {
        return ! is_dir($path) && file_exists($path);
    }

    /**
     * Load server environment variables if available.
     * Processes any '*' entries first, and then adds site-specific entries
     *
     * @param  string  $sitePath
     * @param  string  $siteName
     * @return void
     */
    public function loadServerEnvironmentVariables($sitePath, $siteName)
    {
        $varFilePath = $sitePath . '/.laraserve-env.php';
        if (! file_exists($varFilePath)) {
            $varFilePath = LARASERVE_HOME_PATH . '/.laraserve-env.php';
        }
        if (! file_exists($varFilePath)) {
            return;
        }

        $variables = include $varFilePath;

        $variablesToSet = isset($variables['*']) ? $variables['*'] : [];

        if (isset($variables[$siteName])) {
            $variablesToSet = array_merge($variablesToSet, $variables[$siteName]);
        }

        foreach ($variablesToSet as $key => $value) {
            if (! is_string($key)) continue;
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

}
