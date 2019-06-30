<?php

namespace Snap\Utils;

use Snap\Media\SizeManager;

/**
 * Class Image_Utils
 */
class Image
{
    /**
     * Get size information for all currently registered image sizes.
     *
     * @return array Data for all currently registered image sizes.
     */
    public static function getImageSizes(): array
    {
        global $_wp_additional_image_sizes;

        $sizes = [];

        $dynamic_sizes = SizeManager::getDynamicSizes();

        foreach (\get_intermediate_image_sizes() as $size) {
            if (\in_array($size, ['thumbnail', 'medium', 'medium_large', 'large'])) {
                $sizes[$size] = [
                    'width' => \get_option("{$size}_size_w"),
                    'height' => \get_option("{$size}_size_h"),
                    'crop' => (bool)\get_option("{$size}_crop"),
                    'generated_on_upload' => !\in_array($size, $dynamic_sizes),
                ];
            }

            if (isset($_wp_additional_image_sizes[$size])) {
                $sizes[$size] = [
                    'width' => $_wp_additional_image_sizes[$size]['width'],
                    'height' => $_wp_additional_image_sizes[$size]['height'],
                    'crop' => $_wp_additional_image_sizes[$size]['crop'],
                    'generated_on_upload' => !\in_array($size, $dynamic_sizes),
                ];
            }
        }

        return $sizes;
    }

    /**
     * Get size information for a specific image size.
     *
     * @param  string $size The image size for which to retrieve data.
     * @return bool|array Size data about an image size or false if the size doesn't exist.
     */
    public static function getImageSize(string $size)
    {
        $sizes = static::getImageSizes();

        if (\is_string($size) && isset($sizes[$size])) {
            return $sizes[$size];
        }

        return false;
    }

    /**
     * Get the width of a specific image size.
     *
     * @param  string $size The image size for which to retrieve data.
     * @return bool|int Width of an image size or false if the size doesn't exist.
     */
    public static function getImageWidth(string $size)
    {
        // Get the size meta array.
        $size = static::getImageSize($size);

        if ($size === false) {
            return false;
        }

        if (isset($size['width'])) {
            return (int)$size['width'];
        }

        return false;
    }

    /**
     * Get the height of a specific image size.
     *
     * @param  string $size The image size for which to retrieve data.
     * @return bool|int Height of an image size or false if the size doesn't exist.
     */
    public static function getImageHeight(string $size)
    {
        // Get the size meta array.
        $size = static::getImageSize($size);

        if ($size === false) {
            return false;
        }

        if (isset($size['height'])) {
            return (int)$size['height'];
        }

        return false;
    }
}
