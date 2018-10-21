<?php

namespace Snap\Modules;

use Snap\Core\Snap;
use Snap\Images\Service;
use Snap\Core\Hookable;
use Snap\Core\Utils;
use \Snap\Services\Image_Service;

/**
 * Controls custom image sizes and thumbnail support.
 */
class Images extends Hookable
{
    /**
     * Default WordPress image sizes.
     *
     * @since  1.0.0
     * @var array
     */
    const DEFAULT_IMAGE_SIZES = [
        'medium',
        'medium_large',
        'large',
    ];

    /**
     * Holds any defined image dropdown names.
     *
     * @since  1.0.0
     * @var array
     */
    public static $size_dropdown_names = [];

    /**
     * The filters to run when booted.
     *
     * @since  1.0.0
     * @var array
     */
    protected $filters = [
        'post_thumbnail_html' => 'placeholder_image_fallback',
        'image_downsize' => 'generate_dynamic_image',
        'wp_editor_set_quality' => 'get_upload_quality',
    ];

    /**
     * Inject Snap\Images\Service
     * @param Service $image_service [description]
     */
    public function __construct(Image_Service $image_service)
    {
        $this->image_service = $image_service;
    }

    /**
     * Register class conditional filters.
     *
     * @since  1.0.0
     */
    public function boot()
    {
        // Enable post-thumbnail support.
        $this->enable_thumbnail_support();

        // Register all image sizes.
        $this->register_image_sizes();

        // Remove all default image sizes.
        if (Snap::config('images.reset_image_sizes') !== false) {
            $this->add_filter('intermediate_image_sizes_advanced', 'remove_default_image_sizes');
            $this->add_filter('intermediate_image_sizes_advanced', 'remove_custom_image_sizes');
            $this->add_filter('intermediate_image_sizes', 'remove_default_image_sizes');
        }

        if (! empty(self::$size_dropdown_names)) {
            $this->add_filter('image_size_names_choose', 'enable_custom_image_sizes');
        }
    
        // Set default image size dropdown value.
        if (! empty(Snap::config('images.insert_image_default_size'))) {
            $this->add_filter('after_setup_theme', 'set_insert_image_default_size');
        }
    }

    /**
     * Generate a dynamic image.
     *
     * Snap tries to save server space by only generating images needed for admin use.
     * All other theme images are generated dynamically by this method.
     *
     * @since  1.0.0
     *
     * @param mixed $image Image array to pass on from this filter.
     * @param int          $id   Attachment ID for image.
     * @param array|string $size Optional. Image size to scale to. Accepts any valid image size,
     *                           or an array of width and height values in pixels (in that order).
     *                           Default 'medium'.
     * @return false|array Array containing the image URL, width, height, and boolean for whether
     *                     the image is an intermediate size. False on failure.
     */
    public function generate_dynamic_image($image, $id, $size)
    {
        if ('full' == $size) {
            return $image;
        }
        
        return $this->image_service->generate_dynamic_image($image, $id, $size);
    }

   /**
     * If no post_thumbnail was found, find the corresponding placeholder image and return the image HTML.
     *
     * @since 1.0.0
     *
     * @param  string       $html              The post thumbnail HTML.
     * @param  int          $post_id           The post ID.
     * @param  string       $post_thumbnail_id The post thumbnail ID.
     * @param  string|array $size              The post thumbnail size. Image size or array of width and height
     *                                         values (in that order). Default 'post-thumbnail'.
     * @param  string       $attr              Query string of attributes.
     * @return string The image HTML
     */
    public function placeholder_image_fallback($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        if ($html === '' && Snap::config('images.placeholder_dir') !== false) {
            $html = $this->image_service->get_placeholder_image($post_id, $post_thumbnail_id, $size, $attr);
        }

        return $html;
    }
    
    /**
     * Adds any extra sizes to add media sizes dropdown.
     *
     * @since  1.0.0
     *
     * @param  array $sizes Current sizes for inclusion.
     * @return array Altered $sizes
     */
    public function enable_custom_image_sizes($sizes)
    {
        // Merge custom sizes into $sizes.
        $sizes = \array_merge($sizes, self::$size_dropdown_names);

        // Ensure 'Full size' is always at end.
        unset($sizes['full']);

        if (Snap::config('images.insert_image_allow_full_size') || empty($sizes)) {
            $sizes['full'] = 'Full Size';
        }

        return $sizes;
    }

    /**
     * Returns the image quality option.
     *
     * @since  1.0.0
     *
     * @param  int $quality Existing value.
     * @return int A number between 0-100.
     */
    public function get_upload_quality($quality)
    {
        if (\is_numeric(Snap::config('images.default_image_quality'))) {
            return (int) Snap::config('images.default_image_quality');
        }
        
        return $quality;
    }

    /**
     * Removes all built in image sizes, leaving only full and thumbnail.
     *
     * @since  1.0.0
     *
     * @param  array $sizes Current registered sizes.
     * @return array Modified $sizes array.
     */
    public function remove_default_image_sizes($sizes = [])
    {
        if (! \is_array(\current($sizes))) {
            return \array_diff($sizes, self::DEFAULT_IMAGE_SIZES);
        }

        return \array_diff_key($sizes, \array_values(self::DEFAULT_IMAGE_SIZES));
    }

    /**
     * Removes all custom image sizes that do not have dropdown names.
     *
     * This allows develoeprs to specify which image sizes are choosable within an editor context, and which
     * should only be generated if actually needed.
     *
     * @since  1.0.0
     *
     * @param  array $sizes Current registered sizes.
     * @return array Modified $sizes array.
     */
    public function remove_custom_image_sizes($sizes = [])
    {
        $not_defaults = [];

        foreach (Snap::config('images.image_sizes') as $size => $data) {
            if (! isset($data[3]) || ! $data[3]) {
                $not_defaults[ $size ] = true;
            }
        }
        return \array_diff_key($sizes, $not_defaults);
    }

    /**
     * Sets the default selected option of the insert image size dropdown.
     *
     * Defaults to medium_large.
     * Also sets default alignment to center.
     *
     * @since  1.0.0
     */
    public function set_insert_image_default_size()
    {
        update_option('image_default_align', 'center');
        update_option('image_default_size', Snap::config('images.insert_image_default_size'));
    }




    /**
     * Enabled theme support for thumbnails.
     *
     * Uses the value of Snap::config( 'images.supports_featured_images' ) enable thumbnails for all post types or a select few.
     *
     * @since  1.0.0
     */
    private function enable_thumbnail_support()
    {
        $enabled_thumbails = Snap::config('images.supports_featured_images');

        if (! empty($enabled_thumbails)) {
            if (\is_array($enabled_thumbails)) {
                add_theme_support('post-thumbnails', $enabled_thumbails);
            } elseif ($enabled_thumbails === true) {
                add_theme_support('post-thumbnails');
            }
        }
    }

    /**
     * Registers image sizes.
     *
     * Also allows easy overwriting of default sizes, as well as the ability to disable them one by one.
     *
     * @since 1.0.0
     */
    private function register_image_sizes()
    {
        if (empty(Snap::config('images.image_sizes'))) {
            return;
        }

        // Loop through sizes.
        foreach (Snap::config('images.image_sizes') as $name => $size_info) {
            // Get size properties with basic fallbacks.
            $width = (int) isset($size_info[0]) ? $size_info[0] : 0;
            $height = (int) isset($size_info[1]) ? $size_info[1] : 0;
            $crop = isset($size_info[2]) ? $size_info[2] : false;

            if (\in_array($name, self::DEFAULT_IMAGE_SIZES)) {
                if ($size_info !== false) {
                    // Set other built-in sizes.
                    update_option($name . '_size_w', $width);
                    update_option($name . '_size_h', $height);
                    update_option($name . '_crop', $crop);
                } else {
                    $callback = function ($sizes = []) use ($name) {
                        return \array_diff($sizes, [ $name ]);
                    };

                    // Remove the size.
                    add_filter('intermediate_image_sizes_advanced', $callback);
                    add_filter('intermediate_image_sizes', $callback);
                }
            } else {
                // Add custom image size.
                add_image_size($name, $width, $height, $crop);
            }

            // If a custom dropdown name has been definaed.
            if (isset($size_info[3]) && ! empty($size_info[3])) {
                self::$size_dropdown_names[ $name ] = $size_info[3];
            }
        }
    }
}
