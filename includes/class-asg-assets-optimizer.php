<?php
namespace ASG;

use Exception;

if (!defined('ABSPATH')) {
    exit('دسترسی مستقیم غیرمجاز است!');
}

/**
 * کلاس بهینه‌سازی assets و تصاویر
 */
class Assets_Optimizer {
    /**
     * @var string[] لیست افزونه‌های کش شناخته شده
     */
    private const KNOWN_CACHE_PLUGINS = [
        'wp-super-cache/wp-cache.php',
        'w3-total-cache/w3-total-cache.php',
        'wp-fastest-cache/wpFastestCache.php',
        'litespeed-cache/litespeed-cache.php',
        'wp-rocket/wp-rocket.php'
    ];

    /**
     * @var array<string,array<string,int>> تنظیمات بهینه‌سازی تصاویر
     */
    private const IMAGE_SETTINGS = [
        'jpeg' => [
            'quality' => 85,
            'max_width' => 1920,
            'max_height' => 1080
        ],
        'png' => [
            'compression' => 9,
            'max_width' => 1920,
            'max_height' => 1080
        ]
    ];

    /**
     * @var string|null افزونه کش فعال
     */
    private ?string $active_cache_plugin = null;

    public function __construct() {
        $this->detect_cache_plugin();
        $this->init_hooks();
    }

    /**
     * شناسایی افزونه کش فعال
     */
    private function detect_cache_plugin(): void {
        foreach (self::KNOWN_CACHE_PLUGINS as $plugin) {
            if (is_plugin_active($plugin)) {
                $this->active_cache_plugin = $plugin;
                break;
            }
        }
    }

    /**
     * راه‌اندازی هوک‌ها
     */
    private function init_hooks(): void {
        if (!$this->active_cache_plugin) {
            add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 999);
            add_filter('script_loader_tag', [$this, 'add_async_defer'], 10, 3);
        }

        add_filter('wp_handle_upload', [$this, 'optimize_upload'], 10, 2);
        add_action('init', [$this, 'cleanup_header']);
    }

    /**
     * بهینه‌سازی تصویر آپلود شده
     * 
     * @param array<string,mixed> $upload اطلاعات فایل آپلود شده
     * @return array<string,mixed>
     */
    public function optimize_upload(array $upload): array {
        if ($this->has_image_optimization_plugin()) {
            return $upload;
        }

        if (!isset($upload['type']) || strpos($upload['type'], 'image/') !== 0) {
            return $upload;
        }

        $file_path = $upload['file'];
        $file_type = wp_check_filetype($file_path);
        $image_type = $file_type['type'] ?? '';

        if ($image_type && $this->optimize_image($file_path, $image_type)) {
            // لاگ موفقیت بهینه‌سازی
            error_log(sprintf('ASG: تصویر %s با موفقیت بهینه شد.', basename($file_path)));
        }

        return $upload;
    }

    /**
     * بررسی وجود افزونه بهینه‌ساز تصویر
     */
    private function has_image_optimization_plugin(): bool {
        static $has_optimizer = null;

        if ($has_optimizer === null) {
            $optimization_plugins = [
                'imagify/imagify.php',
                'wp-smushit/wp-smush.php',
                'ewww-image-optimizer/ewww-image-optimizer.php',
                'shortpixel-image-optimiser/wp-shortpixel.php'
            ];

            foreach ($optimization_plugins as $plugin) {
                if (is_plugin_active($plugin)) {
                    $has_optimizer = true;
                    break;
                }
            }

            $has_optimizer = $has_optimizer ?? false;
        }

        return $has_optimizer;
    }

    /**
     * بهینه‌سازی تصویر
     */
    private function optimize_image(string $file_path, string $mime_type): bool {
        if (!function_exists('imagecreatefromjpeg') || !is_readable($file_path)) {
            return false;
        }

        $type = str_replace('image/', '', $mime_type);
        $settings = self::IMAGE_SETTINGS[$type] ?? null;

        if (!$settings) {
            return false;
        }

        $optimized_path = $this->get_optimized_path($file_path);

        try {
            if (!copy($file_path, $optimized_path)) {
                throw new Exception('Failed to create optimized copy');
            }

            $image = match ($mime_type) {
                'image/jpeg' => $this->optimize_jpeg($optimized_path, $settings),
                'image/png' => $this->optimize_png($optimized_path, $settings),
                default => false
            };

            if ($image === false) {
                unlink($optimized_path);
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log('ASG Image Optimization Error: ' . $e->getMessage());
            if (file_exists($optimized_path)) {
                unlink($optimized_path);
            }
            return false;
        }
    }

    /**
     * بهینه‌سازی تصویر JPEG
     * 
     * @throws Exception
     */
    private function optimize_jpeg(string $path, array $settings): bool {
        $image = imagecreatefromjpeg($path);
        if (!$image) {
            throw new Exception('Failed to create image from JPEG');
        }

        $this->resize_image($image, $settings);
        $result = imagejpeg($image, $path, $settings['quality']);
        imagedestroy($image);

        return $result;
    }

    /**
     * بهینه‌سازی تصویر PNG
     * 
     * @throws Exception
     */
    private function optimize_png(string $path, array $settings): bool {
        $image = imagecreatefrompng($path);
        if (!$image) {
            throw new Exception('Failed to create image from PNG');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $this->resize_image($image, $settings);
        $result = imagepng($image, $path, $settings['compression']);
        imagedestroy($image);

        return $result;
    }

    /**
     * ایجاد مسیر فایل بهینه شده
     */
    private function get_optimized_path(string $original_path): string {
        $info = pathinfo($original_path);
        return sprintf(
            '%s/%s-optimized.%s',
            $info['dirname'],
            $info['filename'],
            $info['extension']
        );
    }

    /**
     * تغییر اندازه تصویر
     * 
     * @param resource $image
     * @param array<string,int> $settings
     */
    private function resize_image(&$image, array $settings): void {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $settings['max_width'] && $height <= $settings['max_height']) {
            return;
        }

        $ratio = min(
            $settings['max_width'] / $width,
            $settings['max_height'] / $height
        );

        $new_width = (int)round($width * $ratio);
        $new_height = (int)round($height * $ratio);

        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        if (!$new_image) {
            throw new Exception('Failed to create new image');
        }

        if (imageistruecolor($image)) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }

        if (!imagecopyresampled(
            $new_image, $image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        )) {
            imagedestroy($new_image);
            throw new Exception('Failed to resize image');
        }

        imagedestroy($image);
        $image = $new_image;
    }

    /**
     * پاکسازی header
     */
    public function cleanup_header(): void {
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
    }

    /**
     * اضافه کردن async/defer به اسکریپت‌ها
     */
    public function add_async_defer(string $tag, string $handle, string $src): string {
        // اجرای اسکریپت‌ها به صورت async برای بهبود سرعت بارگذاری
        if (strpos($handle, 'async') !== false) {
            $tag = str_replace(' src', ' async src', $tag);
        }

        // اجرای اسکریپت‌ها به صورت defer برای اسکریپت‌های غیر ضروری
        if (strpos($handle, 'defer') !== false) {
            $tag = str_replace(' src', ' defer src', $tag);
        }

        return $tag;
    }

    /**
     * بهینه‌سازی asset ها
     */
    public function optimize_assets(): void {
        if (is_admin()) {
            return;
        }

        // حذف emoji ها
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');

        // حذف نسخه از URL های استاتیک
        add_filter('style_loader_src', [$this, 'remove_version_query'], 10, 2);
        add_filter('script_loader_src', [$this, 'remove_version_query'], 10, 2);
    }

    /**
     * حذف query string نسخه از URL های استاتیک
     */
    public function remove_version_query(string $src): string {
        if (strpos($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
}