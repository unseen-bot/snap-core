<?php

namespace Snap\Media;

use Snap\Services\Config;
use Snap\Utils\Image;
use Snap\Utils\Theme;

/**
 * Image service for providing placeholder and dynamic image sizes.
 *
 * @since 1.0.0
 */
class Image_Service
{
    /**
     * The file extensions to check when finding placeholders.
     *
     * @since  1.0.0
     * @var array
     */
    protected $placeholder_extensions = [];

    /**
     * The placeholder directory path.
     *
     * @since  1.0.0
     * @var array
     */
    protected $placeholder_directory = '';

    /**
     * The placeholder directory path URI.
     *
     * @since  1.0.0
     * @var array
     */
    protected $placeholder_directory_uri = '';

    /**
     * Register class conditional filters.
     *
     * @since  1.0.0
     */
    public function __construct()
    {
        /**
         * The file extensions to search for when looking for placeholder fallback images.
         *
         * @since  1.0.0
         *
         * @param  array $extensions The file extension list, in order of search preference.
         * @return array $extensions The modified file extension list.
         */
        $this->placeholder_extensions = \apply_filters('snap_placeholder_img_extensions', ['.jpg', '.svg', '.png']);

        $this->placeholder_directory = Theme::getActiveThemePath(
            \trailingslashit(Config::get('images.placeholder_dir'))
        );

        $this->placeholder_directory_uri = Theme::getActiveThemeUri(
            \trailingslashit(Config::get('images.placeholder_dir'))
        );
    }

    /**
     * Searches for a suitable placeholder fallback image.
     *
     * First checks placeholder-${image_size}, then placeholder-${post_type}, then finally placeholder.
     *
     * Runs through $this->placeholder_extensions in order when searching for placeholders.
     *
     * @since 1.0.0
     *
     * @param  int          $post_id           The post ID.
     * @param  string       $post_thumbnail_id The post thumbnail ID.
     * @param  string|array $size              The post thumbnail size. Image size or array of width and height
     *                                         values (in that order). Default 'post-thumbnail'.
     * @param  array        $attr              Array string of attributes.
     * @return string The image HTML
     */
    public function getPlaceholderImage($post_id, $post_thumbnail_id, $size, $attr = [])
    {
        $original_size = $size;

        $post_id = $post_id ?? \get_the_id();

        if (Image::getImageSize($size) === false) {
            $size = 'full';
        }

        // Search for a size specific placeholder first.
        $placeholder_url = $this->searchForPlaceholder('placeholder-' . $size);

        // Then the post type placeholder.
        if ($placeholder_url === false) {
            $placeholder_url = $this->searchForPlaceholder('placeholder-' . get_post_type($post_id));
        }

        // Finally a generic placeholder.
        if ($placeholder_url === false) {
            $placeholder_url = $this->searchForPlaceholder('placeholder');
        }

        if ($placeholder_url !== false) {
            $html = \sprintf(
                /** @lang text */
                '<img src="%s" alt="%s" width="%d" height="%d" %s>',
                $placeholder_url,
                \get_the_title($post_id),
                \is_array($original_size) ? $original_size[0] : Image::getImageWidth($size),
                \is_array($original_size) ? $original_size[1] : Image::getImageHeight($size),
                $this->parseAttributes($attr)
            );

            /**
             * Filter the placeholder image HTML.
             *
             * @since  1.0.0
             *
             * @param string $output The HTML output for the placeholder image tag.
             * @return string $output The HTML output for the placeholder image tag.
             */
            return \apply_filters('snap_placeholder_img_html', $html);
        }

        return '';
    }

    /**
     * Generate a dynamic image.
     *
     * Snap tries to save server space by only generating images needed for admin use.
     * All other theme images are generated dynamically by this method.
     *
     * @since  1.0.0
     *
     * @param mixed        $image Image array to pass on from this filter.
     * @param int          $id    Attachment ID for image.
     * @param array|string $size  Optional. Image size to scale to. Accepts any valid image size,
     *                            or an array of width and height values in pixels (in that order).
     *                            Default 'medium'.
     * @return false|array Array containing the image URL, width, height, and boolean for whether
     *                            the image is an intermediate size. False on failure.
     */
    public function generateDynamicImage($image, $id, $size)
    {
        global $_wp_additional_image_sizes;

        if (!\wp_attachment_is_image($id)) {
            return $image;
        }

        // Get parent image meta data.
        $meta = \wp_get_attachment_metadata($id);

        // Very rarely the image has no meta - Like if added via fakerpress. Bail early.
        if (!isset($meta['file'])) {
            return $image;
        }

        if (\is_array($size)) {
            list($width, $height) = $size;

            if ($meta['width'] < $width) {
                $width = $meta['width'];
                $size[0] = $width;
            }

            if ($meta['height'] < $height) {
                $height = $meta['height'];
                $size[1] = $height;
            }

            $crop = !\wp_image_matches_ratio($meta['height'], $meta['width'], $height, $width);
        } else {
            // Short-circuit if $size has not been registered.
            if (!isset($_wp_additional_image_sizes[$size])) {
                return $image;
            }

            $width = $_wp_additional_image_sizes[$size]['width'];
            $height = $_wp_additional_image_sizes[$size]['height'];
            $crop = $_wp_additional_image_sizes[$size]['crop'];
        }

        $check = \image_get_intermediate_size($id, $size);

        // Bail early if we can.
        if ($check !== false) {
            return [$check['url'], $check['width'], $check['height'], false];
        }

        $parent_image_path = apply_filters(
            'snap_dynamic_image_source',
            \wp_upload_dir()['basedir'] . '/' . $meta['file'],
            $id
        );

        if ($check === false || !\file_exists(\wp_upload_dir()['basedir'] . '/' . $check['path'])) {
            $update = false;

            if (\is_array($size)) {
                $new_meta = \image_make_intermediate_size($parent_image_path, $width, $height, $crop);

                if ($new_meta !== false) {
                    $meta['sizes'][\implode('x', [$width, $height])] = $new_meta;
                    $update = true;
                }
            }

            if ($update === false) {
                foreach ($_wp_additional_image_sizes as $key => $size_data) {
                    if (\array_key_exists($key, $meta['sizes']) === true) {
                        continue;
                    }

                    if ($key == $size) {
                        /*
                         * Generate the requested dynamic size.
                         */
                        $new_meta = \image_make_intermediate_size($parent_image_path, $width, $height, $crop);

                        if ($new_meta === false) {
                            continue;
                        }

                        $meta['sizes'][$size] = $new_meta;

                        $update = true;
                    } elseif (\wp_image_matches_ratio($size_data['width'], $size_data['height'], $width, $height)) {
                        /*
                         * This size is has not been requested, but matches the requested size ratio so should be generated
                         * for use within the srcset.
                         */
                        $new_meta = \image_make_intermediate_size(
                            $parent_image_path,
                            $size_data['width'],
                            $size_data['height'],
                            $size_data['crop']
                        );

                        if ($new_meta === false) {
                            continue;
                        }

                        $meta['sizes'][$key] = $new_meta;
                        $update = true;
                    }
                }
            }

            if ($update === true) {
                \wp_update_attachment_metadata($id, $meta);
            }
        }

        return $image;
    }


    /**
     * Scans the file system to see if a given file exists with an extension from $this->placeholder_extensions.
     *
     * @since  1.0.0
     *
     * @param  string $file_name The placeholder to look for, minus extension.
     * @return string|bool false if not found, otherwise the public URI to the found placeholder.
     */
    private function searchForPlaceholder($file_name)
    {
        $placeholder_url = false;

        foreach ($this->placeholder_extensions as $ext) {
            // Check if the file exists.
            $file_path = $this->placeholder_directory . $file_name . $ext;

            if (\file_exists($file_path) === true) {
                $placeholder_url = $this->placeholder_directory_uri . $file_name . $ext;
                break;
            }
        }

        return $placeholder_url;
    }

    /**
     * Parses image $attr array, turning them into HTML.
     *
     * @since  1.0.0
     *
     * @param  array $attr The $attr array.
     * @return string
     */
    private function parseAttributes($attr)
    {
        $html = '';

        if (!empty($attr)) {
            $html = '';

            foreach ($attr as $key => $value) {
                $html .= \sprintf('%s="%s" ', $key, \esc_attr($value));
            }
        }

        return \trim($html);
    }
}
