<?php
namespace C\Bootstrap;

use \Silex\Application;

use Symfony\Component\Console\Application as Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\InputArgument;
use C\FS\LocalFs;

/**
 * Class Common
 * helps to register, boot, and run a C based application.
 *
 * It helps you to deal with cli and web requests handling.
 *
 * @package C\Bootstrap
 */
class Common {
    /**
     * @var Application
     */
    public $app;
    public $console;


    /**
     * Register base modules and system
     * for a C based web application.
     *
     * Use runtime to inject some configuration values
     * which should override configuration file.
     *
     * Use configTokens to inject new configuration tokens
     * to consume into your configuration values.
     *
     * @param $runtime
     * @param $configTokens
     * @return Application
     */
    public function register ($runtime, $configTokens) {

#region silex
        $app = new Application();
#endregion


#region error to exception
// sometimes it s useful to register it to get a stack trace
        function exception_error_handler($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                // Ce code d'erreur n'est pas inclu dans error_reporting
                return;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
        set_error_handler("\\C\\Bootstrap\\exception_error_handler");
#endregion


#region silex

//$app->register(new MonologServiceProvider([]));
        $app->register(new \Silex\Provider\SessionServiceProvider( ));
//$app->register(new \Silex\Provider\SecurityServiceProvider([]));
//$app->register(new RememberMeServiceProvider([]));

        $app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
        $app->register(new \Silex\Provider\ValidatorServiceProvider());
        $app->register(new \Silex\Provider\FormServiceProvider());
#endregion

#region config

        $tokens = [];
        foreach ($configTokens as $configToken) {
            $tokens[$configToken] = $runtime[$configToken];
        }

        foreach( $runtime as $key=>$value ){
            $app[$key] = $value;
        }

        $app->register(new \Igorw\Silex\ConfigServiceProvider("config.php", $tokens));
#endregion


#region service providers

        $app->register(new \Moust\Silex\Provider\CacheServiceProvider(), [
            'caches.default' => 'default',
            'caches.options' => array_merge([
                'default'=>[
                    'driver' => 'array',
                ],
            ], $app['caches.options']),
        ]);
        $app->register(new \C\Provider\CacheProvider());
        $app->register(new \C\Provider\HttpCacheServiceProvider());

        $app->register(new \C\Provider\EsiServiceProvider());
        $app->register(new \C\Provider\IntlServiceProvider());
        $app->register(new \C\Provider\AssetsServiceProvider());
        $app->register(new \C\Provider\FormServiceProvider());

        if (isset($app['capsule.connections']))
            $app->register(new \C\Provider\CapsuleServiceProvider());

        $app->register(new \C\Provider\RepositoryServiceProvider());
        $app->register(new \C\Provider\LayoutServiceProvider());
        $app->register(new \C\Provider\ModernAppServiceProvider());
        $app->register(new \C\Provider\DashboardExtensionProvider());
        $app->register(new \Binfo\Silex\MobileDetectServiceProvider());
#endregion

        $this->app = $app;

        return $app;
    }

    /**
     * Register console actions for a C based web application.
     *
     * This is especially useful when used with c2-bin.
     *
     * @param string $name
     * @param string $version
     * @return Cli
     */
    public function registerCli ($name='Silex - C Edition', $version = '0.1') {

        $app = $this->app;
        $app->register(new \C\Provider\WatcherServiceProvider());

        $this->console = new Cli($name, $version);

        $console = $this->console;

#region Command lines declaration
        // find more about console object at http://symfony.com/doc/current/components/console/introduction.html
        $console
            ->register('cache:init')
            ->setDescription('Generate cached items')
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {

                $watcheds = $app['watchers.watched'];

                foreach ($watcheds as $watched) {
                    /* @var $watched \C\Watch\WatchedInterface */
                    $watched->clearCache();
                }

                foreach ($watcheds as $watched) {
                    /* @var $watched \C\Watch\WatchedInterface */
                    $watched->resolveRuntime();
                }

                foreach ($watcheds as $watched) {
                    /* @var $watched \C\Watch\WatchedInterface */
                    $dump = $watched->build()->saveToCache();
                    echo $watched->getName()." signed with ".$dump['signature']."\n";
                }
            })
        ;

        $console
            ->register('cache:update')
            ->setDefinition([
                new InputArgument('change', InputArgument::REQUIRED, 'Type of change'),
                new InputArgument('file', InputArgument::REQUIRED, 'The path changed'),
            ])
            ->setDescription('Update cached items given a relative file path and the related File System action')
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {

                $file = $input->getArgument('file');
                $change = $input->getArgument('change');

                $k = realpath($file);
                if ($k!==false) $file = $k;

                $watcheds = $app['watchers.watched'];

                foreach ($watcheds as $watched) {
                    try{
                        /* @var $watched \C\Watch\WatchedInterface */
                        $watched->loadFromCache();
                    }catch(\Exception $ex){
                        \C\Misc\Utils::stderr($watched->getName()." has weirdness in cache and can t be loaded !");
                    }
                }

                foreach ($watcheds as $watched) {
                    try{
                        /* @var $watched \C\Watch\WatchedInterface */
                        if ($watched->changed($change, $file)) {
                            \C\Misc\Utils::stdout($watched->getName()." updated with action $change");
                        }
                    }catch(\Exception $ex) {
                        \C\Misc\Utils::stderr($watched->getName()." failed to update !");
                        \C\Misc\Utils::stderr($ex->getPrevious()->getMessage());
                    }
                }
            })
        ;
        $console
            ->register('fs-cache:dump')
            ->setDescription('Dumps all paths to watch for changes.')
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
                $res = [];

                $watcheds = $app['watchers.watched'];

                foreach ($watcheds as $watched) {
                    /* @var $watched \C\Watch\WatchedInterface */
                    $watched->resolveRuntime();
                }

                foreach ($watcheds as $watched) {
                    /* @var $watched \C\Watch\WatchedInterface */
                    $dump = $watched->dump();
                    if ($dump) $res[] = $dump;
                }
                echo json_encode($res);
            })
        ;
        $console
            ->register('http:bridge')
            ->setDescription('Generate an http bridge file for your webserver.')
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
                $app['assets.bridger']->generate(
                    $app['assets.bridge_file_path'],
                    $app['assets.bridge_type'],
                    $app['assets.fs']
                );
            })
        ;
        $console
            ->register('db:init')
            ->setDescription('Initialize your database. Clear all, construct schema, insert fixtures.')
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
                if (isset($app['capsule.connections'])) {
                    $connections = $app['capsule.connections'];
                    foreach ($connections as $connection => $options) {
                        if ($options["driver"]==='sqlite') {
                            if ($options["database"]!==':memory:') {
                                $exists = LocalFs::file_exists($options['database']);
                                if (!$exists) {
                                    $dir = dirname($options["database"]);
                                    if (!LocalFs::is_dir($dir)) LocalFs::mkdir($dir, 0700, true);
                                    LocalFs::touch($options["database"]);
                                }
                            }
                        }
                    }
                    $app['capsule.schema']->loadSchemas();
                    $app['capsule.schema']->cleanDb();
                    $app['capsule.schema']->initDb();
                } else {
                    \C\Misc\Utils::stderr("There is no database configuration available.");
                }
            })
        ;
        $console
            ->register('db:refresh')
            ->setDescription('Refresh your database.')
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
                if (isset($app['capsule.connections'])) {
                    $app['capsule.schema']->loadSchemas();
                    $app['capsule.schema']->refreshDb();
                } else {
                    \C\Misc\Utils::stderr("There is no database configuration available.");
                }
            })
        ;

        return $console;
    }

    /**
     * Call this method to run the cli handler.
     *
     * @return mixed
     */
    public function runCli () {
        $this->app->boot();
        return $this->console->run();
    }

}
