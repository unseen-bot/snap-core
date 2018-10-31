<?php

namespace Snap\Hookables;

use http\Exception\BadMethodCallException;
use Snap\Core\Snap;
use Snap\Core\Hookable;

/**
 * A simple wrapper for auto registering Middleware.
 *
 * @since 1.0.0
 */
class Middleware extends Hookable
{
    /**
     * The name of the Middleware.
     *
     * If not present, then the snake cased class name is used instead.
     *
     * @since 1.0.0
     * @var null|string
     */
    protected $name = null;

    /**
     * Run this hookable only on the frontend.
     *
     * @since 1.0.0
     * @var boolean
     */
    protected $admin = false;

    /**
     * Boot the AJAX Hookable, and register the handler.
     *
     * @since  1.0.0
     */
    public function boot()
    {
        $this->add_filter("snap_middleware_{$this->get_name()}", 'handler');
    }

    /**
     * Auto-wire and call the child class's handle method.
     *
     * @since 1.0.0
     *
     * @throws \Hodl\Exceptions\ContainerException
     * @throws \ReflectionException
     */
    public function handler()
    {
        return Snap::services()->resolveMethod($this, 'handle');
    }

    /**
     * Return the unqualified snake case name of the current child class, or $name if set.
     *
     * @since  1.0.0
     *
     * @return string
     */
    private function get_name()
    {
        if ($this->name === null) {
            return $this->get_classname();
        }

        return $this->name;
    }
}
