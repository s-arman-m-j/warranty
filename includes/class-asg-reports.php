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
    
    // کلید کش برای آمار کلی
    $cache_key = 'asg_overall_stats';
    $stats = wp_cache_get($cache_key);
    
    if (false === $stats) {
        // اضافه کردن ایندکس برای فیلد status اگر وجود ندارد
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_status 
            ON {$wpdb->prefix}asg_guarantee_requests (status)
        ");
        
        // بهینه‌سازی کوئری با استفاده از ایندکس
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                MAX(created_at) as last_update
            FROM {$wpdb->prefix}asg_guarantee_requests
            /* استفاده از ایندکس status */
            FORCE INDEX (idx_status)
        ");
        
        // ذخیره در کش برای 5 دقیقه
        wp_cache_set($cache_key, $stats, '', 300);
    }

    // اضافه کردن کلاس‌های CSS برای نمایش بهتر اعداد
    $status_classes = array(
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected'
    );

    ?>
    <div class="asg-stats-grid">
        <div class="asg-stat-item">
            <span class="stat-label">کل درخواست‌ها</span>
            <span class="stat-value total-value">
                <?php echo number_format_i18n($stats->total); ?>
            </span>
        </div>
        <div class="asg-stat-item">
            <span class="stat-label">در انتظار بررسی</span>
            <span class="stat-value <?php echo $status_classes['pending']; ?>">
                <?php echo number_format_i18n($stats->pending); ?>
            </span>
        </div>
        <div class="asg-stat-item">
            <span class="stat-label">تایید شده</span>
            <span class="stat-value <?php echo $status_classes['approved']; ?>">
                <?php echo number_format_i18n($stats->approved); ?>
            </span>
        </div>
        <div class="asg-stat-item">
            <span class="stat-label">رد شده</span>
            <span class="stat-value <?php echo $status_classes['rejected']; ?>">
                <?php echo number_format_i18n($stats->rejected); ?>
            </span>
        </div>
    </div>

    <?php if (isset($stats->last_update)): ?>
    <div class="asg-last-update">
        <small>
            آخرین به‌روزرسانی: 
            <?php echo date_i18n('Y/m/d H:i', strtotime($stats->last_update)); ?>
        </small>
    </div>
    <?php endif; ?>

    <!-- اضافه کردن استایل‌های لازم -->
    <style>
        .asg-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .asg-stat-item {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-label {
            display: block;
            margin-bottom: 10px;
            color: #666;
            font-size: 0.9em;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .total-value {
            color: #2271b1;
        }
        .status-pending {
            color: #dba617;
        }
        .status-approved {
            color: #00a32a;
        }
        .status-rejected {
            color: #d63638;
        }
        .asg-last-update {
            text-align: left;
            color: #666;
            margin-top: 10px;
        }
    </style>
    <?php
}
}