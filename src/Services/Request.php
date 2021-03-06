<?php

namespace Snap\Services;

/**
 * Allow static access to the Request service.
 *
 * @method static string getMethod()
 * @method static string getUrl()
 * @method static string getScheme()
 * @method static string getPath()
 * @method static string getHost()
 * @method static array getPathSegments()
 * @method static bool isMethod($method)
 * @method static mixed get($key, $default = null)
 * @method static mixed post($key, $default = null)
 * @method static mixed|\Snap\Http\Request\File|\Snap\Http\Request\File[] files($key, $default = null)
 * @method static mixed server($key, $default = null)
 * @method static mixed wp($key, $default = null)
 * @method static mixed cookie($key, $default = null)
 * @method static mixed query($key, $default = null)
 * @method static bool has($key)
 * @method static bool hasFile($key)
 * @method static bool filled($key)
 * @method static bool isLoginPage()
 * @method static bool isPostTemplate($post_template)
 * @method static bool getGlobalErrors()
 * @method static \Snap\Http\Request getRootInstance() Return root instance.
 *
 * @see \Snap\Http\Request
 */
class Request
{
    use ProvidesServiceFacade;

    /**
     * Specify the underlying root class.
     *
     * @return string
     */
    protected static function getServiceName(): string
    {
        return \Snap\Http\Request::class;
    }
}
