<?php defined('SYSPATH') or die('No direct script access.');

// Load the core Kohana class
require SYSPATH.'classes/Kohana/Core'.EXT;

if (is_file(APPPATH.'classes/Kohana'.EXT))
{
    // Application extends the core
    require APPPATH.'classes/Kohana'.EXT;
}
else
{
    // Load empty core extension
    require SYSPATH.'classes/Kohana'.EXT;
}

/**
 * Set the default time zone.
 *
 * @see  http://kohanaframework.org/guide/using.configuration
 * @see  http://php.net/timezones
 */
date_default_timezone_set('America/Denver');

/**
 * Set the default locale.
 *
 * @see  http://kohanaframework.org/guide/using.configuration
 * @see  http://php.net/setlocale
 */
setlocale(LC_ALL, 'en_US.utf-8');

/**
 * Enable the Kohana auto-loader.
 *
 * @link http://kohanaframework.org/guide/using.autoloading
 * @link http://www.php.net/manual/function.spl-autoload-register
 */
spl_autoload_register(array('Kohana', 'auto_load'));

/**
 * Optionally, you can enable a compatibility auto-loader for use with
 * older modules that have not been updated for PSR-0.
 *
 * It is recommended to not enable this unless absolutely necessary.
 */
// spl_autoload_register(array('Kohana', 'auto_load_lowercase'));

/**
 * Enable the Kohana auto-loader for unserialization.
 *
 * @see  http://php.net/spl_autoload_call
 * @see  http://php.net/manual/var.configuration.php#unserialize-callback-func
 */
ini_set('unserialize_callback_func', 'spl_autoload_call');


/**
 * Set the default language
 */
I18n::lang('en-us');

Cookie::$salt = '0421008445828ceb46f496700a5fa65e';

/**
 * Set Kohana::$environment if a 'KOHANA_ENV' environment variable has been supplied.
 *
 * Note: If you supply an invalid environment name, a PHP warning will be thrown
 * saying "Couldn't find constant Kohana::<INVALID_ENV_NAME>"
 */
if (isset($_SERVER['KOHANA_ENV']))
{
    Kohana::$environment = constant('Kohana::'.strtoupper($_SERVER['KOHANA_ENV']));
}

/**
 * Initialize Kohana, setting the default options.
 *
 * The following options are available:
 *
 * - string   base_url    path, and optionally domain, of your application   NULL
 * - string   index_file  name of your index file, usually "index.php"       index.php
 * - string   charset     internal character set used for input and output   utf-8
 * - string   cache_dir   set the internal cache directory                   APPPATH/cache
 * - integer  cache_life  lifetime, in seconds, of items cached              60
 * - boolean  errors      enable or disable error handling                   TRUE
 * - boolean  profile     enable or disable internal profiling               TRUE
 * - boolean  caching     enable or disable internal caching                 FALSE
 * - boolean  expose      set the X-Powered-By header                        FALSE
 */
Kohana::init(array(
    'base_url'   => '/',
    'index_file' => '',
));

/**
 * Attach the file write to logging. Multiple writers are supported.
 */
Kohana::$log->attach(new Log_File(APPPATH.'logs'));

/**
 * Attach a file reader to config. Multiple readers are supported.
 */
Kohana::$config->attach(new Config_File);

/**
 * Enable modules. Modules are referenced by a relative or absolute path.
 */
Kohana::modules(array(
    'annex'       => MODPATH.'annex',       // Our custom CMS system
    'bandit'      => MODPATH.'bandit',      // Our custom scrape system
    'minion'      => MODPATH.'minion',      // Our CLI task manager
    'cache'       => MODPATH.'cache',       // Caching with multiple backends
    'codebench'   => MODPATH.'codebench',   // Benchmarking tool
    'image'       => MODPATH.'image',       // Image manipulation
    'kostache'    => MODPATH.'kostache',    // Mustache Templating Engine
    'pagination'  => MODPATH.'pagination',  // Paging of results
    'unittest'    => MODPATH.'unittest',    // Unit testing
    'peruse'      => MODPATH.'peruse',      // New Scrape Engine base off old scrape
    'scrape'      => MODPATH.'scrape',      // For mugshot and profile data webscrape
    'admin'       => MODPATH.'admin',       // admin module for reporting and management
    'mango'       => MODPATH.'mango',       // Mongo ORM
));

/**
 * Include 3rd party auto-loaders
 */
require_once(APPPATH.'vendor/decaptcher/ccproto_client.php');

/**
 * Set the routes. Each route must have a minimum of a name, a URI and a set of
 * defaults for the URI.
 */
Route::set('default', '(<controller>(/<action>(/<id>(/<id2>(/<id3>)))))')
  ->defaults(array(
    'controller' => 'dashboard',
    'action'     => 'index',
  ));

Route::set('maint', '(<controller>(/<action>(/<id>(/<id2>(/<id3>)))))')
  ->defaults(array(
    'controller' => 'maint',
    'action'     => 'index',
  ));