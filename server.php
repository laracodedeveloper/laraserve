<?php

/**
 * Define the user's "~/.config/laraserve" path.
 */

define('LARASERVE_HOME_PATH', posix_getpwuid(fileowner(__FILE__))['dir'].'/.config/laraserve');
define('LARASERVE_STATIC_PREFIX', '41c270e4-5535-4daa-b23e-c269744c2f45');

/**
 * Show the Laraserve 404 "Not Found" page.
 */
function show_laraserve_404()
{
    http_response_code(404);
    require __DIR__.'/cli/templates/404.html';
    exit;
}

/**
 * Show directory listing or 404 if directory doesn't exist.
 */
function show_directory_listing($laraserveSitePath, $uri)
{
    $is_root = ($uri == '/');
    $directory = ($is_root) ? $laraserveSitePath : $laraserveSitePath.$uri;

    if (!file_exists($directory)) {
        show_laraserve_404();
    }

    // Sort directories at the top
    $paths = glob("$directory/*");
    usort($paths, function ($a, $b) {
        return (is_dir($a) == is_dir($b)) ? strnatcasecmp($a, $b) : (is_dir($a) ? -1 : 1);
    });

    // Output the HTML for the directory listing
    echo "<h1>Index of $uri</h1>";
    echo "<hr>";
    echo implode("<br>\n", array_map(function ($path) use ($uri, $is_root) {
        $file = basename($path);
        return ($is_root) ? "<a href='/$file'>/$file</a>" : "<a href='$uri/$file'>$uri/$file/</a>";
    }, $paths));

    exit;
}

/**
 * You may use wildcard DNS providers xip.io or nip.io as a tool for testing your site via an IP address.
 * It's simple to use: First determine the IP address of your local computer (like 192.168.0.10).
 * Then simply use http://project.your-ip.xip.io - ie: http://laravel.192.168.0.10.xip.io
 */
function laraserve_support_wildcard_dns($domain)
{
    if (in_array(substr($domain, -7), ['.xip.io', '.nip.io'])) {
        // support only ip v4 for now
        $domainPart = explode('.', $domain);
        if (count($domainPart) > 6) {
            $domain = implode('.', array_reverse(array_slice(array_reverse($domainPart), 6)));
        }
    }

    if (strpos($domain, ':') !== false) {
        $domain = explode(':',$domain)[0];
    }

    return $domain;
}

/**
 * @param array $config Laraserve configuration array
 *
 * @return string|null If set, default site path for uncaught urls
 * */
function laraserve_default_site_path($config)
{
    if (isset($config['default']) && is_string($config['default']) && is_dir($config['default'])) {
        return $config['default'];
    }

    return null;
}

/**
 * Load the Laraserve configuration.
 */
$laraserveConfig = json_decode(
    file_get_contents(LARASERVE_HOME_PATH.'/config.json'), true
);

/**
 * Parse the URI and site / host for the incoming request.
 */
$uri = rawurldecode(
    explode("?", $_SERVER['REQUEST_URI'])[0]
);

$siteName = basename(
    // Filter host to support wildcard dns feature
    laraserve_support_wildcard_dns($_SERVER['HTTP_HOST']),
    '.'.$laraserveConfig['tld']
);

if (strpos($siteName, 'www.') === 0) {
    $siteName = substr($siteName, 4);
}

/**
 * Determine the fully qualified path to the site.
 */
$laraserveSitePath = null;
$domain = array_slice(explode('.', $siteName), -1)[0];

foreach ($laraserveConfig['paths'] as $path) {
    if (is_dir($path.'/'.$siteName)) {
        $laraserveSitePath = $path.'/'.$siteName;
        break;
    }

    if (is_dir($path.'/'.$domain)) {
        $laraserveSitePath = $path.'/'.$domain;
        break;
    }
}

if (is_null($laraserveSitePath) && is_null($laraserveSitePath = laraserve_default_site_path($laraserveConfig))) {
    show_laraserve_404();
}

$laraserveSitePath = realpath($laraserveSitePath);

/**
 * Find the appropriate Laraserve driver for the request.
 */
$laraserveDriver = null;

require __DIR__.'/cli/drivers/require.php';

$laraserveDriver = LaraserveDriver::assign($laraserveSitePath, $siteName, $uri);

if (! $laraserveDriver) {
    show_laraserve_404();
}

/**
 * ngrok uses the X-Original-Host to store the forwarded hostname.
 */
if (isset($_SERVER['HTTP_X_ORIGINAL_HOST']) && !isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = $_SERVER['HTTP_X_ORIGINAL_HOST'];
}

/**
 * Attempt to load server environment variables.
 */
$laraserveDriver->loadServerEnvironmentVariables(
    $laraserveSitePath, $siteName
);

/**
 * Allow driver to mutate incoming URL.
 */
$uri = $laraserveDriver->mutateUri($uri);

/**
 * Determine if the incoming request is for a static file.
 */
$isPhpFile = pathinfo($uri, PATHINFO_EXTENSION) === 'php';

if ($uri !== '/' && ! $isPhpFile && $staticFilePath = $laraserveDriver->isStaticFile($laraserveSitePath, $siteName, $uri)) {
    return $laraserveDriver->serveStaticFile($staticFilePath, $laraserveSitePath, $siteName, $uri);
}

/**
 * Attempt to dispatch to a front controller.
 */
$frontControllerPath = $laraserveDriver->frontControllerPath(
    $laraserveSitePath, $siteName, $uri
);

if (! $frontControllerPath) {
    if (isset($laraserveConfig['directory-listing']) && $laraserveConfig['directory-listing'] == 'on') {
        show_directory_listing($laraserveSitePath, $uri);
    }

    show_laraserve_404();
}

chdir(dirname($frontControllerPath));

require $frontControllerPath;
