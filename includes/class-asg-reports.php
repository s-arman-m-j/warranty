<?php
if (!defined('ABSPATH')) {
    exit('دسترسی مستقیم غیرمجاز است!');
}

class ASG_Reports {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook) {
    if ($hook !== 'warranty-management_page_warranty-management-reports') {
        return;
    }

    // افزودن Chart.js
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        array(),
        '3.7.0',
        true
    );

    // افزودن استایل‌های گزارش
    wp_enqueue_style(
        'asg-reports-style',
        ASG_PLUGIN_URL . 'assets/css/asg-reports.css',
        array(),
        ASG_VERSION
    );

    // افزودن فایل JavaScript گزارش‌ها
    wp_enqueue_script(
        'asg-reports',
        ASG_PLUGIN_URL . 'assets/js/asg-reports.js',
        array('jquery', 'chartjs'),
        ASG_VERSION,
        true
    );

    // افزودن داده‌های مورد نیاز به JavaScript
    wp_localize_script('asg-reports', 'asg_params', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('asg-reports-nonce')
    ));
}

    public function render_reports_page() {
        global $wpdb;
        ?>
        <div class="wrap">
            <h1>گزارش‌های سیستم گارانتی</h1>

            <div class="asg-reports-container">
                <div class="asg-report-card">
                    <h2>آمار کلی</h2>
                    <?php $this->render_overall_stats(); ?>
                </div>

                <div class="asg-report-card">
                    <h2>نمودار وضعیت‌ها</h2>
                    <canvas id="asgStatusChart"></canvas>
                </div>

                <div class="asg-report-card full-width">
                    <h2>نمودار ماهانه</h2>
                    <canvas id="asgMonthlyChart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_overall_stats() {
        global $wpdb;
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM {$wpdb->prefix}asg_guarantee_requests
        ");

        ?>
        <div class="asg-stats-grid">
            <div class="asg-stat-item">
                <span class="stat-label">کل درخواست‌ها</span>
                <span class="stat-value"><?php echo number_format_i18n($stats->total); ?></span>
            </div>
            <div class="asg-stat-item">
                <span class="stat-label">در انتظار بررسی</span>
                <span class="stat-value"><?php echo number_format_i18n($stats->pending); ?></span>
            </div>
            <div class="asg-stat-item">
                <span class="stat-label">تایید شده</span>
                <span class="stat-value"><?php echo number_format_i18n($stats->approved); ?></span>
            </div>
            <div class="asg-stat-item">
                <span class="stat-label">رد شده</span>
                <span class="stat-value"><?php echo number_format_i18n($stats->rejected); ?></span>
            </div>
        </div>
        <?php
    }
}