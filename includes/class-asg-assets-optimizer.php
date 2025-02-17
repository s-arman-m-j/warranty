<?php
namespace ASG;

if (!defined('ABSPATH')) {
    exit;
}

class Assets_Optimizer {
    /**
     * @var array لیست افزونه‌های کش شناخته شده
     */
    private const KNOWN_CACHE_PLUGINS = [
        'wp-super-cache/wp-cache.php',
        'w3-total-cache/w3-total-cache.php',
        'wp-fastest-cache/wpFastestCache.php',
        'litespeed-cache/litespeed-cache.php',
        'wp-rocket/wp-rocket.php'
    ];

    /**
     * @var array تنظیمات بهینه‌سازی تصاویر
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

    private $active_cache_plugin;

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

    private function init_hooks(): void {
        // اجرای هوک‌ها فقط اگر افزونه کش خاصی فعال نباشد
        if (!$this->active_cache_plugin) {
            add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 999);
            add_filter('script_loader_tag', [$this, 'add_async_defer'], 10, 3);
        }

        // بهینه‌سازی تصاویر قبل از ذخیره
        add_filter('wp_handle_upload', [$this, 'optimize_upload'], 10, 2);
        
        // پاکسازی header همیشه اجرا شود
        add_action('init', [$this, 'cleanup_header']);
    }

    /**
     * بهینه‌سازی تصویر آپلود شده
     */
    public function optimize_upload(array $upload): array {
        // اگر افزونه‌های بهینه‌ساز تصویر فعال هستند، رد شود
        if ($this->has_image_optimization_plugin()) {
            return $upload;
        }

        if (strpos($upload['type'], 'image/') !== 0) {
            return $upload;
        }

        $file_path = $upload['file'];
        $image_type = wp_check_filetype($file_path)['type'];

        // بهینه‌سازی با حفظ فایل اصلی
        $this->optimize_image($file_path, $image_type);

        return $upload;
    }

    /**
     * بررسی وجود افزونه بهینه‌ساز تصویر
     */
    private function has_image_optimization_plugin(): bool {
        $optimization_plugins = [
            'imagify/imagify.php',
            'wp-smushit/wp-smush.php',
            'ewww-image-optimizer/ewww-image-optimizer.php',
            'shortpixel-image-optimiser/wp-shortpixel.php'
        ];

        foreach ($optimization_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * بهینه‌سازی تصویر با حفظ نسخه اصلی
     */
    private function optimize_image(string $file_path, string $mime_type): bool {
        if (!function_exists('imagecreatefromjpeg')) {
            return false;
        }

        $type = str_replace('image/', '', $mime_type);
        $settings = self::IMAGE_SETTINGS[$type] ?? null;

        if (!$settings) {
            return false;
        }

        // ایجاد نسخه بهینه در کنار فایل اصلی
        $optimized_path = $this->get_optimized_path($file_path);

        try {
            copy($file_path, $optimized_path);

            switch ($mime_type) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($optimized_path);
                    $this->resize_image($image, $settings);
                    imagejpeg($image, $optimized_path, $settings['quality']);
                    break;

                case 'image/png':
                    $image = imagecreatefrompng($optimized_path);
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    $this->resize_image($image, $settings);
                    imagepng($image, $optimized_path, $settings['compression']);
                    break;
            }

            if (isset($image)) {
                imagedestroy($image);
            }

            return true;
        } catch (\Exception $e) {
            error_log('ASG Image Optimization Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * مسیر فایل بهینه شده
     */
    private function get_optimized_path(string $original_path): string {
        $info = pathinfo($original_path);
        return $info['dirname'] . '/' . $info['filename'] . '-optimized.' . $info['extension'];
    }

    /**
     * تغییر اندازه تصویر اگر بزرگتر از حد مجاز باشد
     */
    private function resize_image(&$image, array $settings): void {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $settings['max_width'] && $height <= $settings['max_height']) {
            return;
        }

        $ratio = min($settings['max_width'] / $width, $settings['max_height'] / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);

        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        if (imageistruecolor($image)) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }

        imagecopyresampled(
            $new_image, $image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        );

        imagedestroy($image);
        $image = $new_image;
    }

    /**
     * پاکسازی header
     */
    public function cleanup_header(): void {
        // این موارد با افزونه‌های کش تداخل ندارند
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
    }
}
