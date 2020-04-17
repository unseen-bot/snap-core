<?php /** @noinspection ClassConstantCanBeUsedInspection */

namespace Snap\Core\Bootstrap;

use Hodl\Container;
use Snap\Core\Hookable;
use Snap\Utils\Str;

class SnapLoader
{
    /**
     * @var \Snap\Core\Bootstrap\SnapLoader
     */
    private static $instance;

    /**
     * List of Snap classes to autoload.
     *
     * @var array
     */
    private $class_list = [
        \Snap\Bootstrap\Assets::class,
        \Snap\Bootstrap\Cleanup::class,
        \Snap\Bootstrap\Comments::class,
        \Snap\Bootstrap\I18n::class,
        \Snap\Media\SizeManager::class,
        \Snap\Media\Placeholders::class,
        \Snap\Media\AttachmentPermalinks::class,
        \Snap\Templating\HandlePostTemplates::class,
        \Snap\Http\Validation\Rules\Nonce::class,
    ];

    /**
     * @var \Hodl\Container
     */
    private $container;

    /**
     * SnapLoader constructor.
     *
     * @param \Hodl\Container $container
     */
    private function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get singleton instance.
     *
     * @param \Hodl\Container $container
     * @return static
     */
    public static function getInstance(Container $container): SnapLoader
    {
        if (static::$instance === null) {
            return static::$instance = new static($container);
        }

        return static::$instance;
    }

    /**
     * Load required files.
     *
     * @throws \Hodl\Exceptions\ContainerException
     * @throws \Hodl\Exceptions\NotFoundException
     * @throws \ReflectionException
     */
    public function load()
    {
        if (\is_admin() || $this->container->get('request')->isLoginPage()) {
            $this->class_list[] = \Snap\Admin\Whitelabel::class;
            $this->class_list[] = \Snap\Admin\Columns\PostTemplate::class;
            $this->class_list[] = \Snap\Media\Admin::class;

            $this->conditionallyLoad('admin.snap_admin_theme', 'Snap\Admin\Theme');
        } else {
            $this->class_list[] = \Snap\Http\Middleware\IsLoggedIn::class;
        }

        $this->conditionallyLoad('theme.disable_comments', 'Snap\Admin\DisableComments');
        $this->conditionallyLoad('theme.disable_customizer', 'Snap\Admin\DisableCustomizer');

        foreach ($this->class_list as $module) {
            $this->initHookable($module);
        }
    }

    /**
     * If the class is a Hookable, initialize the class and fire the run() method.
     *
     * @param string $class_name The path to an included file.
     *
     * @throws \Hodl\Exceptions\ContainerException
     * @throws \Hodl\Exceptions\NotFoundException
     * @throws \ReflectionException
     */
    private function initHookable(string $class_name): void
    {
        // If the included class extends the Hookable abstract.
        if (\class_exists($class_name)) {
            if (\is_subclass_of($class_name, Hookable::class)) {
                // Boot it up and resolve dependencies.
                $this->container->resolve($class_name)->run();
                return;
            }

            if (\is_subclass_of($class_name, 'Rakit\Validation\Rule')) {
                $class_parts = \explode('\\', $class_name);

                $this->container->get('Rakit\Validation\Validator')->addValidator(
                    Str::toSnake(\end($class_parts)),
                    $this->container->resolve($class_name)
                );
            }
        }
    }

    /**
     * Adds a class to the list if the provided config $key is true.
     *
     * @param string $key
     * @param string $class
     *
     * @throws \Hodl\Exceptions\ContainerException
     * @throws \Hodl\Exceptions\NotFoundException
     */
    private function conditionallyLoad(string $key, string $class): void
    {
        if ($this->container->get('config')->get($key) === true) {
            $this->class_list[] = $class;
        }
    }
}
