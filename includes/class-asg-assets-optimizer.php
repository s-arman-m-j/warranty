<?php
/**
 * کلاس بهینه‌سازی فایل‌های استاتیک
 */
class ASG_Assets_Optimizer {
    /**
     * @var ASG_Assets_Optimizer نمونه منحصر به فرد از کلاس
     */
    private static $instance = null;

    /**
     * نسخه فایل‌های استاتیک
     */
    private $assets_version;

    /**
     * سازنده کلاس
     */
    private function __construct() {
        $this->assets_version = defined('WP_DEBUG') && WP_DEBUG ? time() : ASG_VERSION;
        $this->init_hooks();
    }

    /**
     * دریافت نمونه منحصر به فرد از کلاس
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * اضافه کردن هوک‌ها
     */
    private function init_hooks() {
        // لود فایل‌ها در بخش عمومی
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // لود فایل‌ها در بخش مدیریت
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // اضافه کردن تنظیمات به هدر
        add_action('wp_head', array($this, 'add_head_settings'));
        
        // بهینه‌سازی لود فایل‌ها
        add_filter('script_loader_tag', array($this, 'add_async_defer_attributes'), 10, 2);
    }

/**
 * لود فایل‌های بخش عمومی
 */
public function enqueue_frontend_assets() {
    // فقط در صفحه فرم گارانتی یا صفحه پیگیری گارانتی
    if ($this->is_warranty_page() || $this->is_guarantee_tracking_page()) {
        // حذف فایل‌های غیر ضروری در صفحه پیگیری
        if ($this->is_guarantee_tracking_page()) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wc-blocks-style');
        }

        // فایل CSS بهینه شده
        wp_enqueue_style(
            'asg-optimized-styles',
            ASG_PLUGIN_URL . 'assets/css/optimized.min.css',
            array(),
            $this->assets_version
        );

        // دیت پیکر فارسی
        wp_enqueue_style(
            'persian-datepicker',
            ASG_PLUGIN_URL . 'assets/css/persian-datepicker.min.css',
            array(),
            $this->assets_version
        );
        
        wp_enqueue_script(
            'persian-date',
            ASG_PLUGIN_URL . 'assets/js/persian-date.min.js',
            array(),
            $this->assets_version,
            true
        );
        
        wp_enqueue_script(
            'persian-datepicker',
            ASG_PLUGIN_URL . 'assets/js/persian-datepicker.min.js',
            array('persian-date'),
            $this->assets_version,
            true
        );

        // فایل JavaScript بهینه شده
        wp_enqueue_script(
            'asg-optimized-scripts',
            ASG_PLUGIN_URL . 'assets/js/dist/optimized.min.js',
            array('jquery'),
            $this->assets_version,
            true
        );

        // اضافه کردن متغیرهای مورد نیاز به JavaScript
        wp_localize_script('asg-optimized-scripts', 'asgConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asg-nonce'),
            'isRTL' => is_rtl(),
            'debugMode' => defined('WP_DEBUG') && WP_DEBUG,
            'currentUser' => 's-arman-m-j', // نام کاربری فعلی
            'currentTime' => '2025-02-09 08:21:00', // زمان فعلی
            'messages' => array(
                'error' => __('خطایی رخ داده است', 'warranty'),
                'success' => __('عملیات با موفقیت انجام شد', 'warranty'),
                'loading' => __('لطفا صبر کنید...', 'warranty')
            )
        ));
    }
}

    /**
     * لود فایل‌های بخش مدیریت
     */
    public function enqueue_admin_assets($hook) {
        // لود فقط در صفحات مربوط به افزونه
        if (strpos($hook, 'warranty-management') === false) {
            return;
        }

        // استایل‌های ادمین
        wp_enqueue_style(
            'asg-admin-styles',
            ASG_PLUGIN_URL . 'assets/css/asg-admin.css',
            array(),
            $this->assets_version
        );

        // فایل‌های مربوط به گزارشات
        if (strpos($hook, 'warranty-management-reports') !== false) {
            wp_enqueue_style(
                'asg-reports-styles',
                ASG_PLUGIN_URL . 'assets/css/asg-reports.css',
                array(),
                $this->assets_version
            );
            
            wp_enqueue_script(
                'asg-reports-scripts',
                ASG_PLUGIN_URL . 'assets/js/asg-reports.js',
                array('jquery'),
                $this->assets_version,
                true
            );
        }
    }

    /**
     * اضافه کردن تنظیمات به هدر
     */
    public function add_head_settings() {
        // Preload فایل‌های مهم
        echo '<link rel="preload" href="' . ASG_PLUGIN_URL . 'assets/css/optimized.min.css" as="style">';
        echo '<link rel="preload" href="' . ASG_PLUGIN_URL . 'assets/js/dist/optimized.min.js" as="script">';
        
        // اضافه کردن DNS Prefetch
        echo '<link rel="dns-prefetch" href="//ajax.googleapis.com">';
        
        // تنظیمات بهینه‌سازی
        echo '<meta http-equiv="x-dns-prefetch-control" content="on">';
    }

    /**
     * اضافه کردن async/defer به اسکریپت‌ها
     */
    public function add_async_defer_attributes($tag, $handle) {
        // اضافه کردن async به فایل‌های غیر ضروری
        if ('persian-datepicker' === $handle || 'asg-reports-scripts' === $handle) {
            return str_replace(' src', ' async src', $tag);
        }
        
        // اضافه کردن defer به فایل اصلی
        if ('asg-optimized-scripts' === $handle) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }

    /**
     * بررسی صفحه گارانتی
     */
    private function is_warranty_page() {
        global $post;
        
        if (is_singular()) {
            return has_shortcode($post->post_content, 'warranty_form') ||
                   has_shortcode($post->post_content, 'warranty_requests');
        }
        
        return false;
    }
    /**
 * بررسی صفحه پیگیری گارانتی
 */
private function is_guarantee_tracking_page() {
    global $wp;
    return home_url($wp->request) === home_url('my-account/guarantee-tracking');
}
}