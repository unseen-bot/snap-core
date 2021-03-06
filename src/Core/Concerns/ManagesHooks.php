<?php


namespace Snap\Core\Concerns;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

trait ManagesHooks
{
    /**
     * Syntactic sugar around add_filter for grouping hooks together based on type.
     *
     * @param string|array $tag             The name of the filter to hook the $function_to_add callback to.
     * @param callable     $function_to_add The callback to be run when the filter is applied.
     * @param integer      $priority        The priority of the callback.
     * @param integer      $accepted_args   The amount of arguments the callback accepts.
     */
    final public function addAction($tag, $function_to_add, $priority = 10, $accepted_args = 1): void
    {
        $this->addFilter($tag, $function_to_add, $priority, $accepted_args);
    }

    /**
     * Syntactic sugar around remove_hook.
     *
     * @see    Hookable::removeHook
     *
     * @param  string|array $tag                The hook(s) to remove the callback from.
     * @param  callable     $function_to_remove The callback to remove.
     * @param  integer      $priority           Optional. The priority of the callback to remove. Defaults to 10.
     */
    final public function removeAction($tag, $function_to_remove, $priority = 10): void
    {
        $this->removeHook($tag, $function_to_remove, $priority);
    }

    /**
     * Syntactic sugar around remove_hook.
     *
     * @see    Hookable::removeHook
     *
     * @param  string|array $tag                The hook(s) to remove the callback from.
     * @param  callable     $function_to_remove The callback to remove.
     * @param  integer      $priority           Optional. The priority of the callback to remove. Defaults to 10.
     */
    final public function removeFilter($tag, $function_to_remove, $priority = 10): void
    {
        $this->removeHook($tag, $function_to_remove, $priority);
    }

    /**
     * Removes a the given callback from a specific hook.
     *
     * @param  string|array $tag                The hook(s) to remove the callback from.
     * @param  callable     $function_to_remove The callback to remove.
     * @param  integer      $priority           Optional. The priority of the callback to remove. Defaults to 10.
     */
    final public function removeHook($tag, $function_to_remove, $priority = 10): void
    {
        if (\is_string($function_to_remove) && \is_callable([$this, $function_to_remove])) {
            $function_to_remove = [$this, $function_to_remove];
        }

        if (\is_array($tag)) {
            foreach ($tag as $hook) {
                \remove_filter($hook, $function_to_remove, $priority);
            }
        } else {
            \remove_filter($tag, $function_to_remove, $priority);
        }
    }


    /**
     * A wrapper for add_filter. Multiple hooks can be passed as an array to apply the callback
     * to multiple filters within the same method call.
     *
     * If the supplied callback is from a child class, it will be bound to that instance automatically.
     *
     * @param string|array $tag             The name of the filter to hook the $function_to_add callback to.
     *                                      Can also be an array of filters.
     * @param callable     $function_to_add The callback to be run when the filter is applied.
     * @param integer      $priority        The priority of the callback.
     * @param integer      $accepted_args   The amount of arguments the callback accepts.
     */
    final public function addFilter($tag, $function_to_add, $priority = 10, $accepted_args = 1): void
    {
        $callback = $function_to_add;

        if (\is_string($function_to_add) && \is_callable([$this, $function_to_add])) {
            // Bind the callback to the current child class.
            $callback = [$this, $function_to_add];
        }

        if (!\is_array($tag)) {
            $tag = [$tag];
        }

        // Add the callback to all provided hooks.
        foreach ($tag as $hook) {
            \add_filter(
                $hook,
                $callback,
                $priority ?: 10,
                $this->getArgumentCount($function_to_add, $accepted_args)
            );
        }
    }

    /**
     * Use reflection to count the amount of arguments a hook callback expects.
     *
     * @param  callable|string $callback      Closure or function name.
     * @param  integer         $accepted_args The amount of arguments passed into the hook.
     * @return integer
     */
    private function getArgumentCount($callback, $accepted_args = 1): int
    {
        try {
            if (\is_string($callback) && \is_callable([$this, $callback])) {
                $reflector = new ReflectionMethod($this, $callback);
                return $reflector->getNumberOfParameters();
            }

            if (\is_object($callback) && $callback instanceof Closure) {
                $reflector = new ReflectionFunction($callback);
                return $reflector->getNumberOfParameters();
            }
        } catch (ReflectionException $exception) {
            \error_log($exception->getMessage());
        }

        return $accepted_args ?: 1;
    }
}
