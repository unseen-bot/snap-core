<?php

namespace Snap\Core;

use Exception;
use Rakit\Validation\Validator;
use Snap\Exceptions\Startup_Exception;
use Snap\Templating\View;
use Snap\Templating\Templating_Interface;
use \Snap\Media\Image_Service;

/**
 * The main Snap class.
 *
 * @since 1.0.0
 */
class Snap
{
    /**
     * SnapWP website.
     */
    const SNAPWP_HOME = 'https://snapwp.io';

    /**
     * Current Snap version.
     */
    const VERSION = '1.0.0';

    /**
     * Container instance.
     *
     * @since 1.0.0
     * @var Container
     */
    private static $container;

    /**
     * Whether Snap has been setup yet.
     *
     * @since 1.0.0
     * @var boolean
     */
    private static $setup = false;

    /**
     * This class never needs to be instantiated.
     *
     * @since  1.0.0
     */
    final private function __construct()
    {
        // No code here...
    }

    /**
     * This class never needs to be instantiated.
     *
     * @since  1.0.0
     */
    final private function __clone()
    {
        // No code here...
    }

    /**
     * Setup Snap.
     *
     * Must be run in order for anything to work.
     *
     * @since  1.0.0
     *
     * @throws Startup_Exception
     */
    public static function setup()
    {
        add_action(
            'after_switch_theme',
            function () {
                \flush_rewrite_rules();
            }
        );

        if (static::$setup === false) {
            try {
                static::create_container();
                static::init_config();
                static::init_routing();
                static::init_services();

                // Add global WP classes.
                global $wpdb, $wp_query;
                static::$container->add_instance($wp_query);
                static::$container->add_instance($wpdb);

                // Run the loader.
                $loader = new Loader();
                $loader->boot();

                static::init_templating();
                static::register_providers();

                $loader->load_theme();

            } catch (Exception $e) {
                throw new Startup_Exception($e->getMessage());
            }
        }

        static::$setup = true;
    }

    /**
     * Create the static Container instance.
     *
     * @since 1.0.0
     *
     * @throws \Hodl\Exceptions\ContainerException
     */
    public static function create_container()
    {
        if (static::$setup === false) {
            static::$container = new Container();

            static::$container->add_instance(static::$container);
        }
    }

    /**
     * Create a config instance, provide config directories, and add to the container.
     *
     * @since 1.0.0
     *
     * @throws \Hodl\Exceptions\ContainerException
     */
    public static function init_config($theme_root = null)
    {
        if (static::$setup === false) {
            $config = new Config();

            if ($theme_root === null) {
                $config->add_path(\get_template_directory() . '/config');

                if (\is_child_theme()) {
                    $config->add_path(\get_stylesheet_directory() . '/config');
                }
            } else {
                $config->add_path($theme_root . '/config');
            }

            static::$container->add_instance($config);
            static::$container->alias(Config::class, 'config');
        }
    }

    /**
     * Return the Container object.
     *
     * @since  1.0.0
     *
     * @return Container
     */
    public static function get_container()
    {
        return static::$container;
    }

    /**
     * Registers any service providers defined in theme config.
     *
     * @since 1.0.0
     *
     * @throws \Hodl\Exceptions\ContainerException
     */
    public static function register_providers()
    {
        $provider_instances = [];

        foreach (static::$container->get(Config::class)->get('services.providers') as $provider) {
            $provider = static::$container->resolve($provider);
            $provider->register();

            $provider_instances[] = $provider;
        }

        foreach ($provider_instances as $provider) {
            static::$container->resolve_method($provider, 'boot');
        }
    }

    /**
     * Add the templating definitions to the container.
     *
     * Adds the View class, and if no other templating strategy is present, adds and binds the default.
     *
     * @since  1.0.0
     */
    private static function init_templating()
    {
        // If no templating strategy has already been registered.
        if (! static::$container->has(Templating_Interface::class)) {
            // Add the default rendering engine.
            static::$container->add(
                \Snap\Templating\Standard\Strategy::class,
                function () {
                    return new \Snap\Templating\Standard\Strategy;
                }
            );

            static::$container->bind(\Snap\Templating\Standard\Strategy::class, Templating_Interface::class);

            static::$container->add(
                \Snap\Templating\Standard\Partial::class,
                function (Container $hodl) {
                    return $hodl->resolve(\Snap\Templating\Standard\Partial::class);
                }
            );
        }

        static::$container->add_singleton(
            View::class,
            function (Container $hodl) {
                return $hodl->resolve(View::class);
            }
        );
    }

    /**
     * Add Snap services to the container.
     *
     * @since  1.0.0
     */
    private static function init_services()
    {
        // Add Image service.
        static::$container->add_singleton(
            Image_Service::class,
            function () {
                return new Image_Service();
            }
        );

        // Bind Image service to alias.
        static::$container->alias(Image_Service::class, 'image');
    }

    /**
     * Add Snap routing, request, and validation services to the container.
     *
     * @since  1.0.0
     */
    private static function init_routing()
    {
        static::$container->add_singleton(
            Router::class,
            function () {
                return new Router();
            }
        );

        static::$container->add_singleton(
            Request::class,
            function () {
                return new Request();
            }
        );

        static::$container->add_singleton(
            Validator::class,
            function () {
                return new Validator();
            }
        );

        static::$container->alias(Router::class, 'router');
        static::$container->alias(Request::class, 'request');
    }
}
