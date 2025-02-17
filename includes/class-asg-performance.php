<?php
namespace ASG;

use Exception;

if (!defined('ABSPATH')) {
    exit('دسترسی مستقیم غیرمجاز است!');
}

/**
 * کلاس مدیریت عملکرد و بهینه‌سازی
 */
class Performance {
    private Cache $cache;

    /**
     * تنظیمات اسکریپت‌ها و تصاویر
     */
    private const ASYNC_SCRIPTS = [
        'asg-optimized-scripts',
        'google-maps'
    ];

    private const DEFER_SCRIPTS = [
        'asg-analytics'
    ];

    private const IMAGE_COMPRESSION = [
        'jpeg' => [
            'quality' => 85,
            'max_width' => 1920,
            'max_height' => 1080
        ],
        'png' => [
            'compression' => 6,
            'max_width' => 1920,
            'max_height' => 1080
        ]
    ];

    private const UNNECESSARY_SCRIPTS = [
        'wp-embed',
        'jquery-migrate'
    ];

    public function __construct() {
        $this->cache = new Cache();
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // بهینه‌سازی Asset ها
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 100);
            add_filter('script_loader_tag', [$this, 'add_async_defer'], 10, 3);
            add_action('wp_footer', [$this, 'dequeue_unnecessary_scripts'], 1);
            add_action('init', [$this, 'start_page_compression']);
        }

        // کش و بهینه‌سازی دیتابیس
        add_action('init', [$this, 'init_caching']);
        add_action('save_post', [$this, 'clear_related_caches']);
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_caches']);

        // بهینه‌سازی تصاویر
        add_filter('wp_handle_upload', [$this, 'handle_image_upload']);
    }

    public function optimize_assets(): void {
        $this->optimize_styles();
        $this->optimize_scripts();
        $this->load_assets_conditionally();

        // حذف emoji ها
        $this->disable_emojis();
    }

    private function optimize_styles(): void {
        global $wp_styles;

        // حذف نسخه از URL فایل‌ها
        foreach ($wp_styles->registered as $style) {
            $style->ver = null;
        }

        // لود CSS های بهینه شده
        wp_enqueue_style(
            'asg-optimized-styles',
            $this->get_optimized_asset_url('css/optimized.min.css'),
            [],
            $this->get_asset_version('css/optimized.min.css')
        );

        // Preload فونت‌های مهم
        $this->add_preload_fonts();
    }

    private function optimize_scripts(): void {
        // استفاده از jQuery بهینه
        wp_deregister_script('jquery');
        wp_register_script(
            'jquery',
            'https://code.jquery.com/jquery-3.6.4.min.js',
            [],
            '3.6.4',
            true
        );

        // لود JS های بهینه شده
        wp_enqueue_script(
            'asg-optimized-scripts',
            $this->get_optimized_asset_url('js/optimized.min.js'),
            ['jquery'],
            $this->get_asset_version('js/optimized.min.js'),
            true
        );

        // اضافه کردن Preload برای اسکریپت‌های مهم
        $this->add_preload_scripts();
    }

    private function add_preload_fonts(): void {
        $fonts = [
            'IRANSans.woff2' => 'font/woff2',
            'IRANSans-Bold.woff2' => 'font/woff2'
        ];

        foreach ($fonts as $font => $type) {
            echo "<link rel='preload' href='" . 
                 esc_url(ASG_PLUGIN_URL . "assets/fonts/$font") . 
                 "' as='font' type='$type' crossorigin>\n";
        }
    }

    private function add_preload_scripts(): void {
        $scripts = [
            'js/optimized.min.js',
            'js/critical.min.js'
        ];

        foreach ($scripts as $script) {
            echo "<link rel='preload' href='" . 
                 esc_url($this->get_optimized_asset_url($script)) . 
                 "' as='script'>\n";
        }
    }

    private function load_assets_conditionally(): void {
        if (!$this->is_warranty_form_page()) {
            wp_dequeue_script('persian-datepicker');
            wp_dequeue_style('persian-datepicker');
        }
    }

    public function add_async_defer(string $tag, string $handle, string $src): string {
        // اضافه کردن module type برای اسکریپت‌های ES modules
        if (str_ends_with($src, '.mjs')) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }

        if (in_array($handle, self::ASYNC_SCRIPTS, true)) {
            return str_replace(' src', ' async src', $tag);
        }

        if (in_array($handle, self::DEFER_SCRIPTS, true)) {
            return str_replace(' src', ' defer src', $tag);
        }

        return $tag;
    }

    public function dequeue_unnecessary_scripts(): void {
        array_walk(self::UNNECESSARY_SCRIPTS, 'wp_dequeue_script');
    }

    public function start_page_compression(): void {
        if (headers_sent() || !function_exists('apache_get_modules')) {
            return;
        }

        if (!in_array('mod_deflate', apache_get_modules(), true)) {
            ob_start('ob_gzhandler');
        }
    }

    public function handle_image_upload(array $file): array {
        try {
            if (str_starts_with($file['type'], 'image/')) {
                $this->optimize_image($file['file']);
            }
        } catch (Exception $e) {
            error_log('ASG Image Optimization Error: ' . $e->getMessage());
        }
        
        return $file;
    }

    private function optimize_image(string $file_path): bool {
        if (!is_readable($file_path) || !is_writable($file_path)) {
            throw new Exception("File not accessible: $file_path");
        }

        $type = wp_check_filetype($file_path)['type'];
        $settings = self::IMAGE_COMPRESSION[str_replace('image/', '', $type)] ?? null;

        if (!$settings) {
            return false;
        }

        // ایجاد نسخه پشتیبان
        $backup_path = $file_path . '.bak';
        if (!copy($file_path, $backup_path)) {
            throw new Exception("Failed to create backup: $file_path");
        }

        try {
            $success = $this->process_image($file_path, $type, $settings);
            
            if ($success) {
                unlink($backup_path);
                return true;
            }

            // بازگرداندن نسخه پشتیبان در صورت شکست
            copy($backup_path, $file_path);
            unlink($backup_path);
            return false;

        } catch (Exception $e) {
            if (file_exists($backup_path)) {
                copy($backup_path, $file_path);
                unlink($backup_path);
            }
            throw $e;
        }
    }

    private function process_image(string $path, string $type, array $settings): bool {
        if (!function_exists('imagecreatefromjpeg')) {
            throw new Exception('GD Library is not available');
        }

        $image = match ($type) {
            'image/jpeg' => $this->optimize_jpeg($path, $settings),
            'image/png' => $this->optimize_png($path, $settings),
            default => false
        };

        if ($image === false) {
            return false;
        }

        // تغییر اندازه تصویر اگر لازم باشد
        if (imagesx($image) > $settings['max_width'] || imagesy($image) > $settings['max_height']) {
            $image = $this->resize_image($image, $settings);
        }

        $success = match ($type) {
            'image/jpeg' => imagejpeg($image, $path, $settings['quality']),
            'image/png' => imagepng($image, $path, $settings['compression']),
            default => false
        };

        imagedestroy($image);
        return $success;
    }

    private function optimize_jpeg(string $path, array $settings): \GdImage|bool {
        $image = imagecreatefromjpeg($path);
        if ($image === false) {
            throw new Exception("Failed to create image from JPEG: $path");
        }
        return $image;
    }

    private function optimize_png(string $path, array $settings): \GdImage|bool {
        $image = imagecreatefrompng($path);
        if ($image === false) {
            throw new Exception("Failed to create image from PNG: $path");
        }
        
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        return $image;
    }

    private function resize_image(\GdImage $image, array $settings): \GdImage {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $ratio = min(
            $settings['max_width'] / $width,
            $settings['max_height'] / $height
        );
        
        $new_width = (int)round($width * $ratio);
        $new_height = (int)round($height * $ratio);
        
        $new_image = imagecreatetruecolor($new_width, $new_height);
        if ($new_image === false) {
            throw new Exception('Failed to create new image');
        }
        
        // حفظ شفافیت
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        
        if (!imagecopyresampled(
            $new_image, $image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $width, $height
        )) {
            imagedestroy($new_image);
            throw new Exception('Failed to resize image');
        }
        
        return $new_image;
    }

    private function get_optimized_asset_url(string $path): string {
        $version = $this->get_asset_version($path);
        return ASG_PLUGIN_URL . "assets/$path?v=$version";
    }

    private function get_asset_version(string $path): string {
        $full_path = ASG_PLUGIN_DIR . "assets/$path";
        return (string)(file_exists($full_path) ? filemtime($full_path) : ASG_VERSION);
    }

    private function is_warranty_form_page(): bool {
        global $post;
        return $post && has_shortcode($post->post_content, 'warranty_form');
    }

    private function disable_emojis(): void {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    public function cleanup_expired_caches(): void {
        $this->cache->cleanup_old_caches();
    }

    public function clear_related_caches(int $post_id): void {
        if (get_post_type($post_id) === 'product') {
            $this->cache->clear_guarantee_cache();
        }
    }
}