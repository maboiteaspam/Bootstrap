<?php
namespace C\Bootstrap;

use C\Misc\ArrayHelpers;
use \Silex\Application;

use Silex\ServiceProviderInterface;
use Symfony\Component\Console\Application as Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpFoundation\Request;

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
    protected $app;
    /**
     * @var ArrayHelpers
     */
    protected $appServices;
    protected $appServiceValues;

    /**
     * @var Cli
     */
    protected $console;
    /**
     * @var ArrayHelpers
     */
    protected $cliServices;

    public function __construct(){
#region silex
        $this->app = new Application();
#endregion
    }

    /**
     * @param ServiceProviderInterface $provider
     * @param array $values
     */
    public function register (ServiceProviderInterface $provider, $values = array()) {
        $this->appServices[] = $provider;
        $this->appServiceValues[] = $values;
    }

    /**
     * @param $some
     * @return array|null
     */
    public function disable ($some) {
        $index = $this->appServices->indexOf($some);
        if ($index!==false){
            $service = $this->appServices->removeAt($index);
            $values = array_splice($this->appServiceValues, $index, 1);
            return [$service, $values];
        }
        return null;
    }

    /**
     * @param Command $command A Command object
     *
     * @return Command The registered command
     */
    public function add (Command $command) {
        $this->cliServices[] = $command;
    }

    /**
     * @param $some
     * @return mixed|null
     */
    public function disableCommand ($some) {
        return $this->cliServices->remove($some);
    }

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
     */
    public function setup ($runtime, $configTokens) {

#region config
        $tokens = [];
        foreach ($configTokens as $configToken) {
            $tokens[$configToken] = $runtime[$configToken];
        }
        $this->register(new \Igorw\Silex\ConfigServiceProvider("config.php", $tokens), $runtime);
#endregion



#region foreign components
//$app->register(new MonologServiceProvider([]));
        $this->register(new \Silex\Provider\SessionServiceProvider( ));
//$app->register(new \Silex\Provider\SecurityServiceProvider([]));
//$app->register(new RememberMeServiceProvider([]));

        $this->register(new \Silex\Provider\UrlGeneratorServiceProvider());
        $this->register(new \Silex\Provider\ValidatorServiceProvider());
        $this->register(new \Silex\Provider\FormServiceProvider());

        $this->register(new \Moust\Silex\Provider\CacheServiceProvider(), function($app){
            return [
                'caches.default' => 'default',
                'caches.options' => array_merge([
                    'default'=>[
                        'driver' => 'array',
                    ],
                ], $app['caches.options']),
            ];
        });
        $this->register(new \Binfo\Silex\MobileDetectServiceProvider());
#endregion


#region C service providers
        $this->register(new \C\Provider\CacheProvider());
        $this->register(new \C\Provider\HttpCacheServiceProvider());

        $this->register(new \C\Provider\EsiServiceProvider());
        $this->register(new \C\Provider\IntlServiceProvider());
        $this->register(new \C\Provider\AssetsServiceProvider());
        $this->register(new \C\Provider\FormServiceProvider());

        $this->register(new \C\Provider\SchemaServiceProvider());

        $this->register(new \C\Provider\RepositoryServiceProvider());
        $this->register(new \C\Provider\LayoutServiceProvider());
        $this->register(new \C\Provider\ModernAppServiceProvider());
        $this->register(new \C\Provider\DashboardExtensionProvider());
#endregion
    }

    /**
     * @param string $name
     * @param string $version
     */
    public function setupCli ($name='Silex - C Edition', $version = '0.1') {

        $app = $this->app;
        $app->register(new \C\Provider\WatcherServiceProvider());

        $this->console = new Cli($name, $version);

#region Command lines declaration
        $command = new \C\Cli\CacheInit();
        $command->setWebApp($this->app);
        $this->add($command);

        $command = new \C\Cli\CacheUpdate();
        $command->setWebApp($this->app);
        $this->add($command);

        $command = new \C\Cli\FsCacheDump();
        $command->setWebApp($this->app);
        $this->add($command);

        $command = new \C\Cli\DbInit();
        $command->setWebApp($this->app);
        $this->add($command);

        $command = new \C\Cli\DbRefresh();
        $command->setWebApp($this->app);
        $this->add($command);

        $command = new \C\Cli\HttpBridge();
        $command->setWebApp($this->app);
        $this->add($command);
#endregion

    }


    /**
     * @return Application
     */
    public function boot () {

        $app = $this->app;

        foreach ($this->appServices as $index=>$service) {
            $values = $this->appServiceValues[$index];
            if (is_callable($values)) $values = $values($app);
            $app->register($service, $values);
        }

        return $app;
    }

    /**
     * @param Request|null $request
     */
    public function run (Request $request = null) {
        $this->app->run($request);
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int|mixed
     */
    public function runCli (InputInterface $input = null, OutputInterface $output = null) {
        $this->app->boot();
        foreach ($this->cliServices as $service) {
            $this->console->add($service);
        }
        return $this->console->run($input, $output);
    }
}

#region error to exception
// sometimes it s useful to register it to get a stack trace
if (!function_exists('\\C\\Bootstrap\\exception_error_handler')) {
    function exception_error_handler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            // Ce code d'erreur n'est pas inclu dans error_reporting
            return;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    set_error_handler("\\C\\Bootstrap\\exception_error_handler");
}
#endregion
