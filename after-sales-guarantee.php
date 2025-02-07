<?php
/*
Plugin Name: After Sales Guarantee
Description: مدیریت گارانتی و خدمات پس از فروش برای ووکامرس.
Version: 1.8
Author: Your Name
*/

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های افزونه
define('ASG_VERSION', '1.8');
define('ASG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASG_PLUGIN_URL', plugin_dir_url(__FILE__));

// اتولود کلاس‌ها
spl_autoload_register(function ($class) {
    $prefix = 'ASG_';
    $base_dir = ASG_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// فراخوانی فایل‌های اصلی
require_once ASG_PLUGIN_DIR . 'includes/class-asg-security.php';
require_once ASG_PLUGIN_DIR . 'includes/class-asg-notifications.php';
require_once ASG_PLUGIN_DIR . 'includes/class-asg-api.php';
require_once ASG_PLUGIN_DIR . 'includes/class-asg-reports.php';
require_once ASG_PLUGIN_DIR . 'includes/class-asg-db.php';

// راه‌اندازی افزونه
function asg_init() {
    $security = new ASG_Security();
    // ASG_Public() را فعلاً حذف می‌کنیم
    ASG_Notifications::instance();
    ASG_API::instance();
    ASG_Reports::instance();
}
add_action('plugins_loaded', 'asg_init');

// فعال‌سازی افزونه و ایجاد جدول‌های دیتابیس
register_activation_hook(__FILE__, 'asg_create_tables');
function asg_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // جدول درخواست‌های گارانتی
    $table_name = $wpdb->prefix . 'asg_guarantee_requests';
    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        tamin_user_id INT,
        defect_description TEXT,
        expert_comment TEXT,
        status VARCHAR(50),
        receipt_day INT,
        receipt_month VARCHAR(20),
        receipt_year INT,
        image_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // جدول یادداشت‌ها
    $table_notes = $wpdb->prefix . 'asg_guarantee_notes';
    $sql_notes = "CREATE TABLE $table_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // جدول نوتیفیکیشن‌ها
    $sql_notifications = "CREATE TABLE {$wpdb->prefix}asg_notifications (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        request_id bigint(20) NOT NULL,
        type varchar(50) NOT NULL,
        message text NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY request_id (request_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_notes);
    dbDelta($sql_notifications);

    // وضعیت‌های پیش‌فرض
    add_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
}

// افزودن منو به پیشخوان وردپرس
add_action('admin_menu', 'asg_admin_menu');
// افزودن منو به پیشخوان وردپرس
function asg_admin_menu() {
    // افزودن منوی اصلی
    add_menu_page(
        'مدیریت گارانتی',
        'گارانتی',
        'manage_options',
        'warranty-management',
        'asg_admin_page',
        'dashicons-shield',
        6
    );

    // افزودن زیرمنوی لیست درخواست‌ها (همان صفحه اصلی)
    add_submenu_page(
        'warranty-management',
        'لیست درخواست‌ها',
        'لیست درخواست‌ها',
        'manage_options',
        'warranty-management',
        'asg_admin_page'
    );

    // افزودن زیرمنو برای ثبت گارانتی جدید
    add_submenu_page(
        'warranty-management',
        'ثبت گارانتی جدید',
        'ثبت گارانتی جدید',
        'manage_options',
        'warranty-management-add',
        'asg_add_guarantee_page'
    );

    // افزودن زیرمنو برای ثبت گارانتی دسته‌ای
    add_submenu_page(
        'warranty-management',
        'ثبت گارانتی دسته‌ای',
        'ثبت گارانتی دسته‌ای',
        'manage_options',
        'warranty-management-bulk',
        'asg_bulk_guarantee_page'
    );

    // افزودن زیرمنو برای گزارشات
    add_submenu_page(
        'warranty-management',
        'گزارشات',
        'گزارشات',
        'manage_options',
        'warranty-management-reports', // تغییر اسلاگ
        'asg_reports_main_page'
    );

    // افزودن زیرمنو برای نمودارها
    add_submenu_page(
        'warranty-management',
        'نمودارها',
        'نمودارها',
        'manage_options',
        'warranty-management-charts', // تغییر اسلاگ
        'asg_charts_page'
    );

    // افزودن زیرمنو برای تنظیمات
    add_submenu_page(
        'warranty-management',
        'تنظیمات',
        'تنظیمات',
        'manage_options',
        'warranty-management-settings',
        'asg_status_settings_page'
    );

    // افزودن زیرمنو برای دیباگ
    add_submenu_page(
        'warranty-management',
        'دیباگ',
        'دیباگ',
        'manage_options',
        'warranty-management-debug', // تغییر اسلاگ
        'asg_debug_page'
    );

    // افزودن صفحه مخفی برای ویرایش
    add_submenu_page(
        null,
        'ویرایش گارانتی',
        'ویرایش گارانتی',
        'manage_options',
        'warranty-management-edit',
        'asg_edit_guarantee_page'
    );
}

// صفحه دیباگ (اضافه شده)
function asg_debug_page() {
    global $wpdb;
    
    // اضافه کردن استایل‌های CSS
    echo '<style>
        .asg-debug-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .asg-status-ok {
            color: #46b450;
            font-weight: bold;
        }
        .asg-status-error {
            color: #dc3232;
            font-weight: bold;
        }
        .asg-status-warning {
            color: #ffb900;
            font-weight: bold;
        }
        .debug-actions {
            margin: 20px 0;
        }
        .refresh-debug {
            float: right;
        }
    </style>';

    echo '<div class="wrap">';
    echo '<h1>صفحه دیباگ گارانتی</h1>';
    
    echo '<div class="debug-actions">';
    echo '<button class="button button-primary refresh-debug" onclick="window.location.reload();">بروزرسانی اطلاعات</button>';
    echo '</div>';

    // بخش اطلاعات سیستم
    echo '<div class="asg-debug-section">';
    echo '<h2>اطلاعات سیستم</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    
    // اطلاعات سیستم پایه
    $system_info = array(
        'نسخه PHP' => array(
            'value' => phpversion(),
            'status' => version_compare(phpversion(), '7.4', '>=') ? 'ok' : 'error',
            'message' => version_compare(phpversion(), '7.4', '>=') ? '' : 'نسخه PHP باید 7.4 یا بالاتر باشد'
        ),
        'نسخه وردپرس' => array(
            'value' => get_bloginfo('version'),
            'status' => 'ok'
        ),
        'نسخه پلاگین' => array(
            'value' => ASG_VERSION,
            'status' => 'ok'
        ),
        'زمان سرور' => array(
            'value' => current_time('mysql'),
            'status' => 'ok'
        ),
        'محدودیت حافظه PHP' => array(
            'value' => ini_get('memory_limit'),
            'status' => (int)ini_get('memory_limit') >= 128 ? 'ok' : 'warning',
            'message' => (int)ini_get('memory_limit') >= 128 ? '' : 'پیشنهاد می‌شود حداقل 128MB باشد'
        ),
        'حداکثر زمان اجرا' => array(
            'value' => ini_get('max_execution_time') . ' ثانیه',
            'status' => (int)ini_get('max_execution_time') >= 30 ? 'ok' : 'warning',
            'message' => (int)ini_get('max_execution_time') >= 30 ? '' : 'پیشنهاد می‌شود حداقل 30 ثانیه باشد'
        )
    );

    foreach ($system_info as $label => $info) {
        $status_icon = $info['status'] === 'ok' ? '✅' : ($info['status'] === 'error' ? '❌' : '⚠️');
        echo "<tr><td>$label:</td><td>";
        echo $status_icon . ' ' . $info['value'];
        if (!empty($info['message'])) {
            echo " <span class='description'>(" . $info['message'] . ")</span>";
        }
        echo "</td></tr>";
    }
    
    echo '</table>';
    echo '</div>';

    // بخش دیتابیس
    echo '<div class="asg-debug-section">';
    echo '<h2>وضعیت دیتابیس</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    
    $tables = array(
        $wpdb->prefix . 'asg_guarantee_requests' => 'درخواست‌های گارانتی',
        $wpdb->prefix . 'asg_guarantee_notes' => 'یادداشت‌های گارانتی'
    );

    foreach ($tables as $table => $label) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
        $status_icon = $table_exists ? '✅' : '❌';
        
        echo "<tr><td>$label:</td><td>";
        echo $status_icon . ' ';
        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo "$count رکورد";
        } else {
            echo "جدول وجود ندارد!";
        }
        echo "</td></tr>";
    }
    
    echo '</table>';
    echo '</div>';

    // بخش تنظیمات
    echo '<div class="asg-debug-section">';
    echo '<h2>تنظیمات پلاگین</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    
    $settings = array(
        'asg_statuses' => array(
            'label' => 'وضعیت‌های تعریف شده',
            'default' => array()
        ),
        'asg_version' => array(
            'label' => 'نسخه نصب شده',
            'default' => ''
        ),
        'asg_notification_enabled' => array(
            'label' => 'وضعیت نوتیفیکیشن‌ها',
            'default' => '0'
        )
    );

    foreach ($settings as $option_name => $setting) {
        $value = get_option($option_name, $setting['default']);
        $status_icon = !empty($value) ? '✅' : '⚠️';
        
        echo "<tr><td>{$setting['label']}:</td><td>";
        echo $status_icon . ' ';
        if (is_array($value)) {
            echo implode(', ', array_map('esc_html', $value));
        } else {
            echo esc_html($value ?: 'تنظیم نشده');
        }
        echo "</td></tr>";
    }
    
    echo '</table>';
    echo '</div>';

    // بخش بررسی فایل‌ها
    echo '<div class="asg-debug-section">';
    echo '<h2>بررسی فایل‌های اصلی</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    $files_to_check = array(
        'includes/class-asg-notifications.php' => 'فایل نوتیفیکیشن‌ها',
        'includes/class-asg-db.php' => 'فایل دیتابیس',
        // فایل‌های دیگر را اینجا اضافه کنید
    );

    foreach ($files_to_check as $file => $label) {
        $file_exists = file_exists($plugin_dir . $file);
        $status_icon = $file_exists ? '✅' : '❌';
        
        echo "<tr><td>$label:</td><td>";
        echo $status_icon . ' ';
        echo $file_exists ? 'موجود است' : 'یافت نشد!';
        echo "</td></tr>";
    }
    
    echo '</table>';
    echo '</div>';

    // بخش بررسی دسترسی‌ها و مجوزها
    echo '<div class="asg-debug-section">';
    echo '<h2>بررسی دسترسی‌ها</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    
    // بررسی دسترسی‌های پوشه‌ها
    $upload_dir = wp_upload_dir();
    $directories = array(
        $upload_dir['basedir'] => 'پوشه آپلود',
        plugin_dir_path(dirname(__FILE__)) => 'پوشه افزونه'
    );

    foreach ($directories as $dir => $label) {
        $is_writable = wp_is_writable($dir);
        $status_icon = $is_writable ? '✅' : '❌';
        
        echo "<tr><td>$label:</td><td>";
        echo $status_icon . ' ';
        echo $is_writable ? 'قابل نوشتن' : 'غیر قابل نوشتن';
        echo "</td></tr>";
    }
    
    echo '</table>';
    echo '</div>';

    echo '</div>'; // پایان wrap
}

// صفحه مدیریت درخواست‌ها
function asg_admin_page() {
    global $wpdb;

    echo '<div class="wrap">';
    echo '<h1>مدیریت گارانتی</h1>';
    echo '<a href="' . admin_url('admin.php?page=asg-add-guarantee') . '" class="button button-primary">ثبت گارانتی جدید</a>';
    echo '<h2>لیست درخواست‌های گارانتی</h2>';
    asg_show_requests_table();
    echo '</div>';
}

// صفحه ثبت گارانتی جدید
// صفحه ثبت گارانتی جدید
// صفحه ثبت گارانتی جدید
function asg_add_guarantee_page() {
    global $wpdb;

    // پردازش ارسال فرم
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_guarantee_request'])) {
        // اعتبارسنجی و سانیتیزیشن داده‌ها
        $product_id = intval($_POST['product_id']);
        $user_id = intval($_POST['user_id']);
        $tamin_user_id = intval($_POST['tamin_user_id']);
        $defect_description = sanitize_textarea_field($_POST['defect_description']);
        $expert_comment = sanitize_textarea_field($_POST['expert_comment']);
        $status = sanitize_text_field($_POST['status']);
        $receipt_day = intval($_POST['receipt_day']);
        $receipt_month = sanitize_text_field($_POST['receipt_month']);
        $receipt_year = intval($_POST['receipt_year']);
        $image_id = intval($_POST['image_id']);

        // درج داده در دیتابیس
        $result = $wpdb->insert(
            $wpdb->prefix . 'asg_guarantee_requests',
            array(
                'product_id' => $product_id,
                'user_id' => $user_id,
                'tamin_user_id' => $tamin_user_id,
                'defect_description' => $defect_description,
                'expert_comment' => $expert_comment,
                'status' => $status,
                'receipt_day' => $receipt_day,
                'receipt_month' => $receipt_month,
                'receipt_year' => $receipt_year,
                'image_id' => $image_id,
                'created_at' => current_time('mysql')
            ),
            array(
                '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s'
            )
        );

        // نمایش پیام وضعیت
        if ($result) {
            echo '<div class="notice notice-success"><p>درخواست گارانتی با موفقیت ثبت شد.</p></div>';
        } else {
            $error_message = $wpdb->last_error ? $wpdb->last_error : __('خطای ناشناخته رخ داده است', 'textdomain');
            echo '<div class="notice notice-error"><p>خطا در ثبت درخواست: ' . esc_html($error_message) . '</p></div>';
        }
    }

    // شروع ساختار HTML
    echo '<div class="wrap">';
    echo '<h1>ثبت گارانتی جدید</h1>';
    echo '<form method="post" action="" class="asg-form-container">';

    // سطر 1: اطلاعات پایه
    echo '<div class="asg-row">';
    
    // کارت انتخاب محصول
    echo '<div class="asg-col">';
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">انتخاب محصول</div>';
    echo '<div class="asg-card-body">';
    echo '<div class="asg-form-group">';
    echo '<label for="product_id">محصول:</label>';
    echo '<select name="product_id" id="product_id" class="asg-select2" required>';
    echo '<option value="">جستجو و انتخاب محصول...</option>';
    echo '</select>';
    echo '</div>';
    echo '</div></div></div>';

    // کارت انتخاب مشتری
    echo '<div class="asg-col">';
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">مشتری</div>';
    echo '<div class="asg-card-body">';
    echo '<div class="asg-form-group">';
    echo '<label for="user_id">مشتری:</label>';
    echo '<select name="user_id" id="user_id" class="asg-select2" required></select>';
    echo '</div>';
    echo '</div></div></div>';

    // کارت تامین کننده
    echo '<div class="asg-col">';
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">تامین کننده</div>';
    echo '<div class="asg-card-body">';
    echo '<div class="asg-form-group">';
    echo '<label for="tamin_user_id">تامین کننده:</label>';
    echo '<select name="tamin_user_id" id="tamin_user_id" class="asg-select2">';
    $tamin_users = get_users(array('role' => 'tamin', 'fields' => array('ID', 'display_name')));
    foreach ($tamin_users as $user) {
        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '</div></div></div>';
    echo '</div>'; // پایان سطر 1

    // سطر 2: اطلاعات زمانی
    echo '<div class="asg-row">';
    echo '<div class="asg-col">';
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">تاریخ دریافت (شمسی)</div>';
    echo '<div class="asg-card-body">';
    echo '<div class="asg-date-fields">';
    
    // فیلدهای تاریخ
    echo '<div class="asg-form-group">';
    echo '<label for="receipt_day">روز:</label>';
    echo '<select name="receipt_day" id="receipt_day" class="asg-date-select" required>';
    echo generate_day_options();
    echo '</select>';
    echo '</div>';
    
    echo '<div class="asg-form-group">';
    echo '<label for="receipt_month">ماه:</label>';
    echo '<select name="receipt_month" id="receipt_month" class="asg-date-select" required>';
    echo generate_month_options();
    echo '</select>';
    echo '</div>';
    
    echo '<div class="asg-form-group">';
    echo '<label for="receipt_year">سال:</label>';
    echo '<select name="receipt_year" id="receipt_year" class="asg-date-select" required>';
    echo generate_year_options();
    echo '</select>';
    echo '</div>';
    
    echo '</div></div></div></div>';
    echo '</div>'; // پایان سطر 2

    // سطر 3: اطلاعات فنی
    echo '<div class="asg-row">';
    
    // کارت نقص فنی
    echo '<div class="asg-col">';
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">مشخصات فنی</div>';
    echo '<div class="asg-card-body">';
    echo '<div class="asg-form-group">';
    echo '<label for="defect_description">شرح کامل نقص:</label>';
    echo '<textarea name="defect_description" id="defect_description" rows="5" required class="asg-textarea"></textarea>';
    echo '</div>';
    echo '<div class="asg-form-group">';
    echo '<label for="expert_comment">نظر کارشناسی:</label>';
    echo '<textarea name="expert_comment" id="expert_comment" rows="5" class="asg-textarea"></textarea>';
    echo '</div>';
    echo '</div></div></div>';

    // کارت وضعیت و مستندات
    echo '<div class="asg-col">';
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">وضعیت و مستندات</div>';
    echo '<div class="asg-card-body">';
    
    // وضعیت
    echo '<div class="asg-form-group">';
    echo '<label for="status">وضعیت فعلی:</label>';
    echo '<select name="status" id="status" class="asg-select2" required>';
    $statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
    foreach ($statuses as $status) {
        echo '<option value="' . esc_attr($status) . '">' . esc_html($status) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // آپلود عکس
    echo '<div class="asg-form-group">';
    echo '<label>مستندات تصویری:</label>';
    echo '<div class="asg-upload-wrapper">';
    echo '<input type="hidden" name="image_id" id="image_id">';
    echo '<button type="button" class="button button-secondary" id="asg-upload-btn">انتخاب تصویر</button>';
    echo '<div id="asg-image-preview" class="asg-image-preview"></div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div></div></div>';
    echo '</div>'; // پایان سطر 3

    // دکمه ثبت نهایی
    echo '<div class="asg-row">';
    echo '<div class="asg-col">';
    echo '<div class="asg-submit-wrapper">';
    echo '<input type="submit" name="submit_guarantee_request" value="ثبت نهایی درخواست" class="button button-primary button-large">';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</form>';

    // اسکریپت‌های ضروری
    echo '<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Select2 برای محصولات
        $("#product_id").select2({
            ajax: {
                url: ajaxurl,
                dataType: "json",
                delay: 250,
                data: function(params) {
                    return {
                        action: "asg_search_products",
                        search: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data,
                        pagination: {
                            more: (params.page * 30) < data.total_count
                        }
                    };
                },
                cache: true
            },
            minimumInputLength: 3,
            placeholder: "جستجوی محصول...",
            language: {
                noResults: function() {
                    return "نتیجه‌ای یافت نشد";
                },
                searching: function() {
                    return "در حال جستجو...";
                }
            }
        });

        // Select2 برای کاربران
        $("#user_id").select2({
            ajax: {
                url: ajaxurl,
                dataType: "json",
                delay: 250,
                data: function(params) {
                    return {
                        action: "asg_search_users",
                        search: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data,
                        pagination: {
                            more: (params.page * 30) < data.total_count
                        }
                    };
                },
                cache: true
            },
            minimumInputLength: 3,
            placeholder: "جستجوی مشتری...",
            language: {
                noResults: function() {
                    return "مشتری یافت نشد";
                }
            }
        });

        // سیستم آپلود عکس
        $("#asg-upload-btn").click(function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: "انتخاب تصویر",
                button: { text: "انتخاب" },
                multiple: false,
                library: { type: "image" }
            });

            frame.on("select", function() {
                var attachment = frame.state().get("selection").first().toJSON();
                $("#image_id").val(attachment.id);
                $("#asg-image-preview").html(
                    \'<div class="asg-preview-item">\' +
                        \'<img src="\' + attachment.sizes.thumbnail.url + \'" alt="پیش‌نمایش">\' +
                        \'<button type="button" class="button button-small asg-remove-image">حذف</button>\' +
                    \'</div>\'
                );
            });

            frame.open();
        });

        // حذف عکس
        $(document).on("click", ".asg-remove-image", function() {
            $("#image_id").val("");
            $("#asg-image-preview").html("");
        });
    });
    </script>';

    echo '</div>'; // پایان wrap
}

// توابع کمکی برای تولید options
function generate_day_options() {
    $html = '';
    for ($day = 1; $day <= 31; $day++) {
        $html .= "<option value='$day'>$day</option>";
    }
    return $html;
}

function generate_month_options() {
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $html = '';
    foreach ($months as $month) {
        $html .= "<option value='$month'>$month</option>";
    }
    return $html;
}

function generate_year_options() {
    $html = '';
    for ($year = 1403; $year <= 1410; $year++) {
        $html .= "<option value='$year'>$year</option>";
    }
    return $html;
}

function generate_status_options() {
    $statuses = get_option('asg_statuses', ['آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی']);
    $html = '';
    foreach ($statuses as $status) {
        $html .= "<option value='$status'>$status</option>";
    }
    return $html;
}

// صفحه ویرایش گارانتی
function asg_edit_guarantee_page() {
    global $wpdb;

    // دریافت شناسه درخواست از URL
    $request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // بررسی وجود درخواست
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}asg_guarantee_requests WHERE id = %d", $request_id));

    if (!$request) {
        echo '<div class="notice notice-error"><p>درخواست گارانتی یافت نشد.</p></div>';
        return;
    }

    // بررسی ارسال فرم
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['submit_guarantee_request'])) {
            // دریافت وضعیت جدید
            $status = sanitize_text_field($_POST['status']);

            // بررسی اگر وضعیت تغییر کرده است
            if ($status != $request->status) {
                // به‌روزرسانی وضعیت درخواست
                $result = $wpdb->update(
                    $wpdb->prefix . 'asg_guarantee_requests',
                    array('status' => $status), // فیلدهای به‌روزرسانی
                    array('id' => $request_id) // شرط به‌روزرسانی
                );

                if ($result !== false) {
                    // ثبت تغییرات وضعیت در یادداشت‌ها
                    $wpdb->insert(
                        $wpdb->prefix . 'asg_guarantee_notes',
                        array(
                            'request_id' => $request_id,
                            'note' => '[تغییر وضعیت] وضعیت تغییر کرد به: ' . $status,
                            'created_at' => current_time('mysql') // زمان فعلی
                        )
                    );
                    echo '<div class="notice notice-success"><p>وضعیت درخواست با موفقیت به‌روزرسانی شد.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>خطا در به‌روزرسانی وضعیت درخواست: ' . $wpdb->last_error . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>وضعیت تغییر نکرده است.</p></div>';
            }
        } elseif (isset($_POST['submit_new_note'])) {
            // افزودن یادداشت جدید
            $new_note = sanitize_textarea_field($_POST['new_note']);
            $wpdb->insert(
                $wpdb->prefix . 'asg_guarantee_notes',
                array(
                    'request_id' => $request_id,
                    'note' => $new_note,
                    'created_at' => current_time('mysql') // زمان فعلی
                )
            );
            echo '<div class="notice notice-success"><p>یادداشت با موفقیت افزوده شد.</p></div>';
        }
    }

    // نمایش فرم ویرایش
    echo '<div class="wrap">';
    echo '<h1>ویرایش گارانتی</h1>';

    // نمایش اطلاعات درخواست
    echo '<h2>اطلاعات درخواست:</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<tbody>';
    echo '<tr><th>محصول</th><td>' . get_the_title($request->product_id) . '</td></tr>';
    echo '<tr><th>مشتری</th><td>' . get_userdata($request->user_id)->display_name . '</td></tr>';
    echo '<tr><th>تامین‌کننده</th><td>' . ($request->tamin_user_id ? get_userdata($request->tamin_user_id)->display_name : '-') . '</td></tr>';
    echo '<tr><th>نقص کالا</th><td>' . esc_html($request->defect_description) . '</td></tr>';
    echo '<tr><th>نظر کارشناس</th><td>' . esc_html($request->expert_comment) . '</td></tr>';
    echo '<tr><th>تاریخ دریافت</th><td>' . $request->receipt_day . ' ' . $request->receipt_month . ' ' . $request->receipt_year . '</td></tr>';
    echo '<tr><th>عکس</th><td>';
    if ($request->image_id) {
        $image_url = wp_get_attachment_url($request->image_id);
        if ($image_url) {
            echo '<img src="' . esc_url($image_url) . '" style="max-width: 200px; height: auto;">';
        }
    } else {
        echo '-';
    }
    echo '</td></tr>';
    echo '</tbody>';
    echo '</table>';

    // فرم تغییر وضعیت
    echo '<h2>تغییر وضعیت:</h2>';
    echo '<form method="post" action="">';
    echo '<label for="status">وضعیت:</label>';
    echo '<select name="status" id="status" required>';
    $statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
    foreach ($statuses as $status) {
        $selected = $status == $request->status ? 'selected' : '';
        echo '<option value="' . $status . '" ' . $selected . '>' . $status . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" name="submit_guarantee_request" value="به‌روزرسانی وضعیت" class="button button-primary">';
    echo '</form>';

    // نمایش یادداشت‌ها (شامل تغییرات وضعیت)
    echo '<h2>یادداشت‌ها و تغییرات وضعیت:</h2>';
    $notes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}asg_guarantee_notes WHERE request_id = %d ORDER BY created_at DESC", $request_id));
    if ($notes) {
        echo '<ul>';
        foreach ($notes as $note) {
            echo '<li>' . esc_html($note->note) . ' - <small>' . esc_html($note->created_at) . '</small></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>هیچ یادداشتی ثبت نشده است.</p>';
    }

    // فرم افزودن یادداشت جدید
    echo '<h2>افزودن یادداشت جدید:</h2>';
    echo '<form method="post" action="">';
    echo '<label for="new_note">یادداشت جدید:</label><br>';
    echo '<textarea name="new_note" id="new_note" rows="5" cols="40" required></textarea><br>';
    echo '<input type="submit" name="submit_new_note" value="افزودن یادداشت" class="button">';
    echo '</form>';

    echo '</div>'; // پایان wrap
}

// صفحه تنظیمات وضعیت‌ها
function asg_status_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_statuses'])) {
        $statuses = explode("\n", sanitize_textarea_field($_POST['statuses']));
        $statuses = array_map('trim', $statuses);
        update_option('asg_statuses', $statuses);
        echo '<div class="notice notice-success"><p>وضعیت‌ها با موفقیت به‌روزرسانی شدند.</p></div>';
    }

    $current_statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));

    echo '<div class="wrap">';
    echo '<h1>تنظیمات وضعیت‌ها</h1>';
    echo '<form method="post" action="">';
    echo '<label for="statuses">وضعیت‌ها (هر خط یک وضعیت):</label><br>';
    echo '<textarea name="statuses" id="statuses" rows="10" cols="50">' . implode("\n", $current_statuses) . '</textarea><br>';
    echo '<input type="submit" name="submit_statuses" value="ذخیره وضعیت‌ها" class="button button-primary">';
    echo '</form>';
    echo '</div>';
}

// نمایش جدول درخواست‌ها
function asg_show_requests_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'asg_guarantee_requests';

    // Initialize variables for filter conditions
    $where = array();
    $query_vars = array();

    // Get filter values from the URL
    $filter_id = isset($_GET['filter_id']) ? intval($_GET['filter_id']) : '';
    $filter_product = isset($_GET['filter_product']) ? intval($_GET['filter_product']) : '';
    $filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : '';
    $filter_tamin = isset($_GET['filter_tamin']) ? intval($_GET['filter_tamin']) : '';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_receipt_date = isset($_GET['filter_receipt_date']) ? sanitize_text_field($_GET['filter_receipt_date']) : '';

    // Build WHERE conditions based on filter values
    if (!empty($filter_id)) {
        $where[] = "id = %d";
        $query_vars[] = $filter_id;
    }
    if (!empty($filter_product)) {
        $where[] = "product_id = %d";
        $query_vars[] = $filter_product;
    }
    if (!empty($filter_user)) {
        $where[] = "user_id = %d";
        $query_vars[] = $filter_user;
    }
    if (!empty($filter_tamin)) {
        $where[] = "tamin_user_id = %d";
        $query_vars[] = $filter_tamin;
    }
    if (!empty($filter_status)) {
        $where[] = "status = %s";
        $query_vars[] = $filter_status;
    }
    if (!empty($filter_receipt_date)) {
        // Assuming receipt_date is stored in receipt_day, receipt_month, receipt_year
        // For simplicity, filter by receipt_year
        $where[] = "receipt_year = %d";
        $query_vars[] = intval($filter_receipt_date);
    }

    // Build the final WHERE clause
    if (!empty($where)) {
        $where_clause = " WHERE " . implode(" AND ", $where);
    } else {
        $where_clause = "";
    }

    // Prepare the SQL query with filters
    $sql = $wpdb->prepare("SELECT * FROM $table_name" . $where_clause, $query_vars);
    $requests = $wpdb->get_results($sql);

    // Start the table HTML
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="asg-guarantee">';
    echo '<div class="alignleft actions">';
    echo '<label for="filter_id">ID:</label>';
    echo '<input type="number" name="filter_id" id="filter_id" value="' . esc_attr($filter_id) . '">';
    echo '<label for="filter_product">محصول:</label>';
    echo '<select name="filter_product" id="filter_product">';
    echo '<option value="">تمامی محصولات</option>';
    $products = wc_get_products(array('status' => 'publish', 'limit' => -1));
    foreach ($products as $product) {
        $selected = ($product->get_id() == $filter_product) ? 'selected' : '';
        echo '<option value="' . $product->get_id() . '" ' . $selected . '>' . $product->get_name() . '</option>';
    }
    echo '</select>';
    echo '<label for="filter_user">مشتری:</label>';
    echo '<select name="filter_user" id="filter_user">';
    echo '<option value="">تمامی مشتریان</option>';
    $users = get_users();
    foreach ($users as $user) {
        $selected = ($user->ID == $filter_user) ? 'selected' : '';
        echo '<option value="' . $user->ID . '" ' . $selected . '>' . $user->display_name . '</option>';
    }
    echo '</select>';
    echo '<label for="filter_tamin">تامین‌کننده:</label>';
    echo '<select name="filter_tamin" id="filter_tamin">';
    echo '<option value="">تمامی تامین‌کنندگان</option>';
    $tamin_users = get_users(array('role' => 'tamin'));
    foreach ($tamin_users as $user) {
        $selected = ($user->ID == $filter_tamin) ? 'selected' : '';
        echo '<option value="' . $user->ID . '" ' . $selected . '>' . $user->display_name . '</option>';
    }
    echo '</select>';
    echo '<label for="filter_status">وضعیت:</label>';
    echo '<select name="filter_status" id="filter_status">';
    echo '<option value="">تمامی وضعیت‌ها</option>';
    $statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
    foreach ($statuses as $status) {
        $selected = ($status == $filter_status) ? 'selected' : '';
        echo '<option value="' . $status . '" ' . $selected . '>' . $status . '</option>';
    }
    echo '</select>';
    echo '<label for="filter_receipt_date">تاریخ دریافت:</label>';
    echo '<input type="text" name="filter_receipt_date" id="filter_receipt_date" value="' . esc_attr($filter_receipt_date) . '">';
    echo '<input type="submit" value="فیلتر" class="button">';
    echo '</div>';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>محصول</th><th>مشتری</th><th>تامین‌کننده</th><th>وضعیت</th><th>تاریخ دریافت</th><th>عکس</th><th>یادداشت‌ها</th><th>عملیات</th></tr></thead>';
    echo '<tbody>';
    foreach ($requests as $request) {
        echo '<tr>';
        echo '<td>' . $request->id . '</td>';
        echo '<td>' . get_the_title($request->product_id) . '</td>';
        echo '<td>' . get_userdata($request->user_id)->display_name . '</td>';
        echo '<td>' . ($request->tamin_user_id ? get_userdata($request->tamin_user_id)->display_name : '-') . '</td>';
        echo '<td>' . $request->status . '</td>';
        echo '<td>' . $request->receipt_day . ' ' . $request->receipt_month . ' ' . $request->receipt_year . '</td>';
        // Display image
        echo '<td>';
        if ($request->image_id) {
            $image_url = wp_get_attachment_url($request->image_id);
            if ($image_url) {
                echo '<img src="' . esc_url($image_url) . '" style="max-width: 50px; height: auto;">';
            }
        } else {
            echo '-';
        }
        echo '</td>';
        // Display notes
        echo '<td>';
        $notes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}asg_guarantee_notes WHERE request_id = %d ORDER BY created_at DESC", $request->id));
        if ($notes) {
            echo '<ul>';
            foreach ($notes as $note) {
                echo '<li>' . esc_html($note->note) . ' - <small>' . esc_html($note->created_at) . '</small></li>';
            }
            echo '</ul>';
        } else {
            echo '-';
        }
        echo '</td>';
        // Edit link
        echo '<td><a href="' . admin_url('admin.php?page=asg-edit-guarantee&id=' . $request->id) . '" class="button">ویرایش</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

// انتقال اسکریپت‌ها و استایل‌ها
function asg_enqueue_scripts() {
    // بارگذاری کتابخانه رسانه وردپرس
    wp_enqueue_media();

    // بارگذاری اسکریپت سفارشی
    wp_enqueue_script(
        'asg-script',
        plugins_url('asg-script.js', __FILE__),
        array('jquery'),
        filemtime(plugin_dir_path(__FILE__) . 'asg-script.js'),
        true
    );

    // بارگذاری Select2 CSS از CDN
    wp_enqueue_style(
        'select2',
        'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
        array(),
        '4.0.13'
    );

    // بارگذاری Select2 JS از CDN
    wp_enqueue_script(
        'select2',
        'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
        array('jquery'),
        '4.0.13',
        true
    );

    // بارگذاری فایل CSS سفارشی
    wp_enqueue_style(
        'asg-custom-style',
        plugins_url('asg-custom-style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'asg-custom-style.css')
    );

    // افزودن داده‌های لازم برای اسکریپت (اختیاری)
    wp_localize_script('asg-script', 'asg_params', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('asg-nonce')
    ));
}
add_action('admin_enqueue_scripts', 'asg_enqueue_scripts');

// اکشن Ajax برای جستجوی محصولات
add_action('wp_ajax_asg_search_products', 'asg_search_products');
function asg_search_products() {
    $search = sanitize_text_field($_GET['search']);
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
        's' => $search
    ));

    $results = array();
    foreach ($products as $product) {
        $results[] = array(
            'id' => $product->get_id(),
            'text' => $product->get_name()
        );
    }

    wp_send_json($results);
}

// اکشن Ajax برای جستجوی کاربران
add_action('wp_ajax_asg_search_users', 'asg_search_users');
function asg_search_users() {
    $search = sanitize_text_field($_GET['search']);
    $users = get_users(array(
        'search' => '*' . $search . '*'
    ));

    $results = array();
    foreach ($users as $user) {
        $results[] = array(
            'id' => $user->ID,
            'text' => $user->display_name
        );
    }

    wp_send_json($results);
}
// صفحه ثبت گارانتی دسته‌ای
function asg_bulk_guarantee_page() {
    global $wpdb;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_bulk_guarantee'])) {
        $user_id = intval($_POST['user_id']);
        $receipt_day = intval($_POST['receipt_day']);
        $receipt_month = sanitize_text_field($_POST['receipt_month']);
        $receipt_year = intval($_POST['receipt_year']);

        // ثبت هر گارانتی به صورت جداگانه
        for ($i = 1; $i <= 10; $i++) {
            if (!empty($_POST['product_id_' . $i])) {
                $product_id = intval($_POST['product_id_' . $i]);
                $tamin_user_id = intval($_POST['tamin_user_id_' . $i]);
                $defect_description = sanitize_textarea_field($_POST['defect_description_' . $i]);
                $expert_comment = sanitize_textarea_field($_POST['expert_comment_' . $i]);
                $status = sanitize_text_field($_POST['status_' . $i]);
                $image_id = intval($_POST['image_id_' . $i]);

                $result = $wpdb->insert(
                    $wpdb->prefix . 'asg_guarantee_requests',
                    array(
                        'product_id' => $product_id,
                        'user_id' => $user_id,
                        'tamin_user_id' => $tamin_user_id,
                        'defect_description' => $defect_description,
                        'expert_comment' => $expert_comment,
                        'status' => $status,
                        'receipt_day' => $receipt_day,
                        'receipt_month' => $receipt_month,
                        'receipt_year' => $receipt_year,
                        'image_id' => $image_id
                    )
                );

                if ($result) {
                    echo '<div class="notice notice-success"><p>گارانتی شماره ' . $i . ' با موفقیت ثبت شد.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>خطا در ثبت گارانتی شماره ' . $i . '.</p></div>';
                }
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>ثبت گارانتی دسته‌ای</h1>';
    echo '<form method="post" action="" class="asg-form-container">';

    // بخش اطلاعات مشتری و تاریخ دریافت
    echo '<div class="asg-card">';
    echo '<div class="asg-card-header">اطلاعات مشتری و تاریخ دریافت</div>';
    echo '<div class="asg-card-body">';
    echo '<div class="asg-form-group"><label for="user_id">مشتری:</label><select name="user_id" id="user_id" required></select></div>';
    echo '<div class="asg-form-group"><label for="receipt_day">روز:</label>';
    echo '<select name="receipt_day" id="receipt_day" required>';
    for ($day = 1; $day <= 31; $day++) {
        echo '<option value="' . $day . '">' . $day . '</option>';
    }
    echo '</select></div>';
    echo '<div class="asg-form-group"><label for="receipt_month">ماه:</label><select name="receipt_month" id="receipt_month" required>';
    $months = array('فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
    foreach ($months as $month) {
        echo '<option value="' . $month . '">' . $month . '</option>';
    }
    echo '</select></div>';
    echo '<div class="asg-form-group"><label for="receipt_year">سال:</label>';
    echo '<select name="receipt_year" id="receipt_year" required>';
    for ($year = 1403; $year <= 1410; $year++) {
        $selected = ($year == 1403) ? 'selected' : '';
        echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
    }
    echo '</select></div>';
    echo '</div></div>';

    // گارانتی‌های پیش‌فرض (۱ و ۲)
    for ($i = 1; $i <= 2; $i++) {
        echo '<div class="asg-card asg-guarantee-card" id="asg-guarantee-' . $i . '">';
        echo '<div class="asg-card-header">';
        echo 'گارانتی شماره ' . $i;
        echo '<button type="button" class="asg-remove-guarantee" data-guarantee-id="' . $i . '">حذف</button>';
        echo '</div>';
        echo '<div class="asg-card-body">';
        echo '<div class="asg-form-group"><label for="product_id_' . $i . '">محصول:</label><select name="product_id_' . $i . '" id="product_id_' . $i . '"></select></div>';
        echo '<div class="asg-form-group"><label for="tamin_user_id_' . $i . '">تامین‌کننده (tamin):</label><select name="tamin_user_id_' . $i . '" id="tamin_user_id_' . $i . '">';
        $tamin_users = get_users(array('role' => 'tamin'));
        foreach ($tamin_users as $user) {
            echo '<option value="' . $user->ID . '">' . $user->display_name . '</option>';
        }
        echo '</select></div>';
        echo '<div class="asg-form-group"><label for="defect_description_' . $i . '">نقص کالا:</label><textarea name="defect_description_' . $i . '" id="defect_description_' . $i . '" rows="5" cols="40" required></textarea></div>';
        echo '<div class="asg-form-group"><label for="expert_comment_' . $i . '">نظر کارشناس:</label><textarea name="expert_comment_' . $i . '" id="expert_comment_' . $i . '" rows="5" cols="40"></textarea></div>';
        echo '<div class="asg-form-group"><label for="status_' . $i . '">وضعیت:</label><select name="status_' . $i . '" id="status_' . $i . '" required>';
        $statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
        foreach ($statuses as $status) {
            echo '<option value="' . $status . '">' . $status . '</option>';
        }
        echo '</select></div>';
        echo '<div class="asg-form-group"><label for="image_url_' . $i . '">عکس:</label><input type="text" name="image_id_' . $i . '" id="image_url_' . $i . '" class="regular-text"><input type="button" value="آپلود عکس" class="button" id="upload_image_' . $i . '"></div>';
        echo '</div></div>';
    }

    // دکمه + برای افزودن گارانتی‌های بیشتر
    echo '<button type="button" id="asg-add-guarantee" class="asg-button">+ افزودن گارانتی</button>';

    // دکمه ثبت
    echo '<input type="submit" name="submit_bulk_guarantee" value="ثبت گارانتی‌ها" class="asg-button">';
    echo '</form>';
    echo '</div>'; // پایان wrap
}
add_filter('woocommerce_account_menu_items', 'asg_add_guarantee_tracking_menu');
function asg_add_guarantee_tracking_menu($items) {
    $items['guarantee-tracking'] = 'پیگیری گارانتی';
    return $items;
}
add_action('woocommerce_account_guarantee-tracking_endpoint', 'asg_guarantee_tracking_page');
function asg_guarantee_tracking_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'asg_guarantee_requests';

    // دریافت لیست گارانتی‌های کاربر
    $requests = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d", $user_id
    ));

    if ($requests) {
        echo '<h2>گارانتی‌های من</h2>';
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead><tr><th>محصول</th><th>وضعیت</th><th>تاریخ دریافت</th><th>عملیات</th></tr></thead>';
        echo '<tbody>';
        foreach ($requests as $request) {
            $product_name = get_the_title($request->product_id);
            $status = $request->status;
            $receipt_date = $request->receipt_day . ' ' . $request->receipt_month . ' ' . $request->receipt_year;

            echo '<tr>';
            echo '<td>' . esc_html($product_name) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($receipt_date) . '</td>';
            echo '<td><a href="' . esc_url(add_query_arg('request_id', $request->id, wc_get_account_endpoint_url('add-guarantee-note'))) . '" class="button">افزودن یادداشت</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>هیچ گارانتی‌ای برای نمایش وجود ندارد.</p>';
    }
}
add_action('woocommerce_account_add-guarantee-note_endpoint', 'asg_add_guarantee_note_page');
add_action('woocommerce_account_add-guarantee-note_endpoint', 'asg_add_guarantee_note_page');
function asg_add_guarantee_note_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    $request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_note'])) {
        $note = sanitize_textarea_field($_POST['note']);
        $note = '**' . $note . '**'; // افزودن دو ستاره به ابتدا و انتهای یادداشت
        $wpdb->insert(
            $wpdb->prefix . 'asg_guarantee_notes',
            array(
                'request_id' => $request_id,
                'note' => $note,
                'created_at' => current_time('mysql')
            )
        );

        echo '<div class="woocommerce-message">یادداشت با موفقیت افزوده شد.</div>';
    }

    echo '<h2>افزودن یادداشت جدید</h2>';
    echo '<form method="post" action="">';
    echo '<div class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
    echo '<label for="note">یادداشت:</label>';
    echo '<textarea name="note" id="note" rows="5" cols="40" required></textarea>';
    echo '</div>';
    echo '<input type="submit" name="submit_note" value="افزودن یادداشت" class="button">';
    echo '</form>';
}
add_action('init', 'asg_add_custom_endpoints');
function asg_add_custom_endpoints() {
    add_rewrite_endpoint('guarantee-tracking', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('add-guarantee-note', EP_ROOT | EP_PAGES);
}
// در تابع asg_admin_menu زیرمنوهای موجود، این زیرمنو را اضافه کنید

function asg_reports_page() {
    global $wpdb;

    // استایل‌های درون صفحه
    echo '<style>
        .asg-reports-container {
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .asg-report-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .asg-report-card.full-width {
            grid-column: 1 / -1;
        }
        .asg-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .asg-stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007cba;
        }
    </style>';

    echo '<div class="wrap">';
    echo '<h1>گزارش‌های سیستم گارانتی</h1>';

    // آمار کلی
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'آماده ارسال' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'ارسال شده' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'تعویض شده' THEN 1 ELSE 0 END) as replaced,
            SUM(CASE WHEN status = 'خارج از گارانتی' THEN 1 ELSE 0 END) as expired
        FROM {$wpdb->prefix}asg_guarantee_requests
    ");

    echo '<div class="asg-reports-container">';
    
    // کارت آمار کلی
    echo '<div class="asg-report-card">';
    echo '<h2>آمار کلی</h2>';
    echo '<div class="asg-stats-grid">';
    echo '<div class="asg-stat-item"><div class="stat-label">کل درخواست‌ها</div><div class="stat-value">' . number_format_i18n($stats->total) . '</div></div>';
    echo '<div class="asg-stat-item"><div class="stat-label">آماده ارسال</div><div class="stat-value">' . number_format_i18n($stats->pending) . '</div></div>';
    echo '<div class="asg-stat-item"><div class="stat-label">ارسال شده</div><div class="stat-value">' . number_format_i18n($stats->sent) . '</div></div>';
    echo '<div class="asg-stat-item"><div class="stat-label">تعویض شده</div><div class="stat-value">' . number_format_i18n($stats->replaced) . '</div></div>';
    echo '</div>';
    echo '</div>';

    // کارت نمودار وضعیت‌ها
    echo '<div class="asg-report-card">';
    echo '<h2>نمودار وضعیت‌ها</h2>';
    echo '<canvas id="asgStatusChart"></canvas>';
    echo '</div>';

    // کارت نمودار ماهانه (تمام عرض)
    echo '<div class="asg-report-card full-width">';
    echo '<h2>نمودار ماهانه درخواست‌ها</h2>';
    echo '<canvas id="asgMonthlyChart"></canvas>';
    echo '</div>';

    echo '</div>'; // پایان asg-reports-container

    // اضافه کردن اسکریپت Chart.js
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    
    // اسکریپت مخصوص نمودارها
    echo '<script>
    jQuery(document).ready(function($) {
        // دریافت داده‌ها از سرور
        $.post(ajaxurl, {
            action: "asg_get_stats",
            nonce: "' . wp_create_nonce('asg-reports-nonce') . '"
        }, function(response) {
            if (response.success) {
                const data = response.data;

                // نمودار وضعیت‌ها
                new Chart($("#asgStatusChart"), {
                    type: "pie",
                    data: {
                        labels: data.status_labels,
                        datasets: [{
                            data: data.status_counts,
                            backgroundColor: [
                                "#FF6384",
                                "#36A2EB",
                                "#FFCE56",
                                "#4BC0C0"
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "right"
                            }
                        }
                    }
                });

                // نمودار ماهانه
                new Chart($("#asgMonthlyChart"), {
                    type: "line",
                    data: {
                        labels: data.monthly_labels,
                        datasets: [{
                            label: "تعداد درخواست‌ها",
                            data: data.monthly_counts,
                            borderColor: "#36A2EB",
                            tension: 0.1,
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: "top"
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        });
    });
    </script>';

    echo '</div>'; // پایان wrap
}
// اکشن Ajax برای دریافت آمار
// اکشن Ajax برای دریافت آمار
add_action('wp_ajax_asg_get_stats', 'asg_get_stats');
function asg_get_stats() {
    check_ajax_referer('asg-reports-nonce', 'nonce');

    global $wpdb;

    // آمار وضعیت‌ها
    $status_stats = $wpdb->get_results($wpdb->prepare("
        SELECT status, COUNT(*) as count 
        FROM {$wpdb->prefix}asg_guarantee_requests 
        GROUP BY status
    "));

    $status_labels = array();
    $status_counts = array();
    foreach ($status_stats as $stat) {
        $status_labels[] = $stat->status ?: 'نامشخص';
        $status_counts[] = (int)$stat->count;
    }

    // آمار ماهانه
    $monthly_stats = $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM {$wpdb->prefix}asg_guarantee_requests 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month 
        ORDER BY month DESC
        LIMIT 12
    "));

    $monthly_labels = array();
    $monthly_counts = array();
    foreach ($monthly_stats as $stat) {
        // تبدیل تاریخ میلادی به شمسی
        $date = explode('-', $stat->month);
        $jdate = gregorian_to_jalali($date[0], $date[1], '01');
        $monthly_labels[] = $jdate[0] . '/' . str_pad($jdate[1], 2, '0', STR_PAD_LEFT);
        $monthly_counts[] = (int)$stat->count;
    }

    wp_send_json_success(array(
        'status_labels' => $status_labels,
        'status_counts' => $status_counts,
        'monthly_labels' => array_reverse($monthly_labels),
        'monthly_counts' => array_reverse($monthly_counts)
    ));
}

// تابع کمکی برای تبدیل تاریخ میلادی به شمسی
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + floor(($gy2 + 3) / 4) - floor(($gy2 + 99) / 100) + 
            floor(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * floor($days / 12053);
    $days %= 12053;
    $jy += 4 * floor($days / 1461);
    $days %= 1461;
    $jy += floor(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $jm = ($days < 186) ? 1 + floor($days / 31) : 7 + floor(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

// افزودن استایل‌های ادمین
function asg_enqueue_admin_scripts($hook) {
    // لود کردن استایل‌های ادمین در تمام صفحات پلاگین
    if (strpos($hook, 'warranty-management') !== false) {
        wp_enqueue_style(
            'asg-admin-style',
            ASG_PLUGIN_URL . 'assets/css/asg-admin.css',
            array(),
            ASG_VERSION
        );
    }
}
add_action('admin_enqueue_scripts', 'asg_enqueue_admin_scripts');
function asg_reports_main_page() {
    global $wpdb;

    // دریافت پارامترهای فیلتر
    $filter_tamin = isset($_GET['filter_tamin']) ? intval($_GET['filter_tamin']) : '';
    $filter_customer = isset($_GET['filter_customer']) ? intval($_GET['filter_customer']) : '';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_product = isset($_GET['filter_product']) ? intval($_GET['filter_product']) : '';
    $selected_columns = isset($_GET['columns']) ? (array)$_GET['columns'] : array();
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

    // اضافه کردن استایل‌های پرینت
    echo '<style>
        @media print {
            .asg-filter-row,
            .asg-column-selector,
            .asg-export-buttons,
            #adminmenuwrap,
            #adminmenuback,
            #wpadminbar,
            #wpfooter {
                display: none !important;
            }
            
            .wrap {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .wp-list-table {
                border-collapse: collapse;
                width: 100%;
            }
            
            .wp-list-table th,
            .wp-list-table td {
                border: 1px solid #000;
                padding: 8px;
            }
            
            .wp-list-table img {
                max-width: 50px !important;
                height: auto !important;
            }
            
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .print-footer {
                display: block !important;
                text-align: left;
                margin-top: 20px;
                font-size: 12px;
            }
        }
        
        .print-header,
        .print-footer {
            display: none;
        }
    </style>';

    // ستون های قابل انتخاب
    $available_columns = array(
        'id' => 'ID درخواست',
        'product_name' => 'نام محصول',
        'customer_name' => 'نام مشتری',
        'tamin_name' => 'تامین کننده',
        'status' => 'وضعیت',
        'receipt_date' => 'تاریخ دریافت',
        'created_at' => 'تاریخ ایجاد',
        'defect_description' => 'توضیحات نقص',
        'expert_comment' => 'نظر کارشناسی',
        'image' => 'عکس',
        'notes_count' => 'تعداد یادداشت‌ها'
    );

    // اگر هیچ ستونی انتخاب نشده، پیش‌فرض‌ها را نمایش بده
    if (empty($selected_columns)) {
        $selected_columns = array('id', 'product_name', 'customer_name', 'status', 'receipt_date');
    }

    // ساخت بخش SELECT
    $select = array();
    foreach ($selected_columns as $col) {
        switch ($col) {
            case 'product_name':
                $select[] = 'p.post_title as product_name';
                break;
            case 'customer_name':
                $select[] = 'cu.display_name as customer_name';
                break;
            case 'tamin_name':
                $select[] = 'tu.display_name as tamin_name';
                break;
            case 'receipt_date':
                $select[] = 'CONCAT(r.receipt_day, " ", r.receipt_month, " ", r.receipt_year) as receipt_date';
                break;
            case 'image':
                $select[] = 'r.image_id';
                break;
            case 'notes_count':
                $select[] = '(SELECT COUNT(*) FROM ' . $wpdb->prefix . 'asg_guarantee_notes n WHERE n.request_id = r.id) as notes_count';
                break;
            default:
                $select[] = 'r.' . $col;
                break;
        }
    }
    $select_sql = implode(', ', $select);

    // ساخت بخش JOIN
    $join_sql = "
        LEFT JOIN {$wpdb->posts} p ON r.product_id = p.ID
        LEFT JOIN {$wpdb->users} cu ON r.user_id = cu.ID
        LEFT JOIN {$wpdb->users} tu ON r.tamin_user_id = tu.ID
    ";

    // ساخت شرط های WHERE
    $where = array();
    $params = array();

    if (!empty($filter_tamin)) {
        $where[] = 'r.tamin_user_id = %d';
        $params[] = $filter_tamin;
    }

    if (!empty($filter_customer)) {
        $where[] = 'r.user_id = %d';
        $params[] = $filter_customer;
    }

    if (!empty($filter_status)) {
        $where[] = 'r.status = %s';
        $params[] = $filter_status;
    }

    if (!empty($filter_product)) {
        $where[] = 'r.product_id = %d';
        $params[] = $filter_product;
    }

    if (!empty($date_from) && !empty($date_to)) {
        $where[] = 'r.created_at BETWEEN %s AND %s';
        $params[] = $date_from . ' 00:00:00';
        $params[] = $date_to . ' 23:59:59';
    }

    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // ساخت کوئری نهایی
    $sql = "
        SELECT $select_sql
        FROM {$wpdb->prefix}asg_guarantee_requests r
        $join_sql
        $where_sql
        ORDER BY r.created_at DESC
        LIMIT 500
    ";

    // اگر پارامتر وجود دارد، کوئری را prepare کنید
    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    // اجرای کوئری
    $results = $wpdb->get_results($sql);

    // شروع نمایش گزارشات
    echo '<div class="wrap">';
    echo '<h1>گزارشات پیشرفته گارانتی</h1>';
    
    // فرم فیلترها
    echo '<form method="get" action="" class="asg-report-filters">';
    echo '<input type="hidden" name="page" value="warranty-management-reports">';
    
    // ردیف اول فیلترها
    echo '<div class="asg-filter-row">';
    
    // فیلتر مشتری با جستجوی Ajax
    echo '<div class="asg-filter">';
    echo '<label>مشتری:</label>';
    echo '<select name="filter_customer" id="filter_customer" class="asg-select2-customer">';
    if ($filter_customer) {
        $user = get_user_by('id', $filter_customer);
        echo '<option value="' . $filter_customer . '" selected>' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // فیلتر تامین کننده با جستجوی Ajax
    echo '<div class="asg-filter">';
    echo '<label>تامین کننده:</label>';
    echo '<select name="filter_tamin" id="filter_tamin" class="asg-select2-tamin">';
    if ($filter_tamin) {
        $user = get_user_by('id', $filter_tamin);
        echo '<option value="' . $filter_tamin . '" selected>' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // فیلتر وضعیت
    echo '<div class="asg-filter">';
    echo '<label>وضعیت:</label>';
    echo '<select name="filter_status">';
    echo '<option value="">همه وضعیت‌ها</option>';
    $statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
    foreach ($statuses as $status) {
        $selected = ($status == $filter_status) ? 'selected' : '';
        echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($status) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    echo '</div>'; // پایان ردیف اول
    
    // ردیف دوم فیلترها
    echo '<div class="asg-filter-row">';
    
    // فیلتر محصول
    echo '<div class="asg-filter">';
    echo '<label>محصول:</label>';
    echo '<select name="filter_product" id="filter_product" class="asg-select2-product">';
    if ($filter_product) {
        $product = wc_get_product($filter_product);
        if ($product) {
            echo '<option value="' . $filter_product . '" selected>' . esc_html($product->get_name()) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    
    // فیلتر تاریخ
    echo '<div class="asg-filter">';
    echo '<label>تاریخ ایجاد:</label>';
    echo '<input type="date" name="date_from" value="' . esc_attr($date_from) . '" placeholder="از تاریخ">';
    echo '<input type="date" name="date_to" value="' . esc_attr($date_to) . '" placeholder="تا تاریخ">';
    echo '</div>';
    
    echo '</div>'; // پایان ردیف دوم
    
    // انتخاب ستون‌ها
    echo '<div class="asg-column-selector">';
    echo '<h3>انتخاب ستون‌های گزارش:</h3>';
    foreach ($available_columns as $key => $label) {
        $checked = in_array($key, $selected_columns) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="columns[]" value="' . $key . '" ' . $checked . '>';
        echo esc_html($label);
        echo '</label>';
    }
    echo '</div>';
    
    // دکمه‌های اکسل و پرینت
    echo '<div class="asg-export-buttons">';
    echo '<button type="submit" class="button button-primary">اعمال فیلترها</button>';
    echo '<a href="' . admin_url('admin.php?page=warranty-management-reports&export=excel' . http_build_query($_GET)) . '" class="button button-secondary"><span class="dashicons dashicons-media-spreadsheet"></span> خروجی اکسل</a>';
    echo '<button type="button" class="button button-secondary print-preview"><span class="dashicons dashicons-printer"></span> پیش‌نمایش چاپ</button>';
    echo '<a href="' . admin_url('admin.php?page=warranty-management-reports') . '" class="button">پاکسازی فیلترها</a>';
    echo '</div>';
    
    echo '</form>';

    // نمایش نتایج
    if (!empty($results)) {
        // هدر پرینت
        echo '<div class="print-header">';
        echo '<h2>گزارش درخواست‌های گارانتی</h2>';
        echo '<p>فیلترهای اعمال شده:</p>';
        echo '<ul>';
        if ($filter_customer) {
            $user = get_user_by('id', $filter_customer);
            echo '<li>مشتری: ' . esc_html($user->display_name) . '</li>';
        }
        if ($filter_tamin) {
            $user = get_user_by('id', $filter_tamin);
            echo '<li>تامین کننده: ' . esc_html($user->display_name) . '</li>';
        }
        if ($filter_status) {
            echo '<li>وضعیت: ' . esc_html($filter_status) . '</li>';
        }
        if ($filter_product) {
            $product = wc_get_product($filter_product);
            if ($product) {
                echo '<li>محصول: ' . esc_html($product->get_name()) . '</li>';
            }
        }
        if ($date_from && $date_to) {
            echo '<li>بازه زمانی: از ' . esc_html($date_from) . ' تا ' . esc_html($date_to) . '</li>';
        }
        echo '</ul>';
        echo '</div>';

        // نمایش جدول
        echo '<div class="asg-report-results">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach ($selected_columns as $col) {
            echo '<th>' . esc_html($available_columns[$col]) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($results as $row) {
            echo '<tr>';
            foreach ($selected_columns as $col) {
                echo '<td>';
                switch ($col) {
                    case 'image':
                        if (!empty($row->image_id)) {
                            $image_url = wp_get_attachment_url($row->image_id);
                            echo '<a href="' . esc_url($image_url) . '" target="_blank">';
                            echo '<img src="' . esc_url($image_url) . '" style="max-width: 100px; height: auto;">';
                            echo '</a>';
                        } else {
                            echo '-';
                        }
                        break;
                    case 'notes_count':
                        echo '<a href="' . admin_url('admin.php?page=warranty-management-edit&id=' . $row->id) . '">';
                        echo esc_html($row->notes_count);
                        echo '</a>';
                        break;
                    default:
                        $value = isset($row->$col) ? $row->$col : '-';
                        echo esc_html($value);
                        break;
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
// فوتر پرینت
        echo '<div class="print-footer">';
        echo '<p>تاریخ گزارش: ' . date_i18n('Y/m/d H:i:s') . '</p>';
        echo '<p>کاربر: ' . wp_get_current_user()->display_name . '</p>';
        echo '</div>';
        
        echo '</div>'; // پایان asg-report-results
    } else {
        echo '<p class="asg-no-results">هیچ موردی با فیلترهای انتخاب شده یافت نشد.</p>';
    }

    echo '</div>'; // پایان wrap
    
    // افزودن اسکریپت‌های لازم
    add_action('admin_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Select2 برای مشتری
            $('.asg-select2-customer').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'asg_search_users',
                            search: params.term,
                            role: 'customer'
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    }
                },
                minimumInputLength: 2,
                placeholder: 'جستجوی مشتری...',
                width: '100%'
            });

            // Select2 برای تامین کننده
            $('.asg-select2-tamin').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'asg_search_users',
                            search: params.term,
                            role: 'tamin'
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    }
                },
                minimumInputLength: 2,
                placeholder: 'جستجوی تامین کننده...',
                width: '100%'
            });

            // Select2 برای محصولات
            $('.asg-select2-product').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'asg_search_products',
                            search: params.term
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    }
                },
                minimumInputLength: 2,
                placeholder: 'جستجوی محصول...',
                width: '100%'
            });

            // اضافه کردن عملکرد پرینت
            $('.print-preview').click(function(e) {
                e.preventDefault();
                window.print();
            });
        });
        </script>
        <?php
    });
}
function asg_charts_page() {
    global $wpdb;

    // دریافت وضعیت‌ها از تنظیمات
    $statuses = get_option('asg_statuses', array('آماده ارسال', 'ارسال شده', 'تعویض شده', 'خارج از گارانتی'));
    $status_counts = array_fill_keys($statuses, 0);

    // دریافت تعداد درخواست‌ها برای هر وضعیت
    $status_data = $wpdb->get_results("
        SELECT status, COUNT(*) as count
        FROM {$wpdb->prefix}asg_guarantee_requests
        GROUP BY status
    ");

    // پر کردن آرایه تعداد با داده‌های دریافتی
    foreach ($status_data as $data) {
        if (isset($status_counts[$data->status])) {
            $status_counts[$data->status] = $data->count;
        }
    }

    // دریافت داده‌های نمودار ماهانه
    $monthly_data = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM {$wpdb->prefix}asg_guarantee_requests
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12
    ");

    // استایل‌های درون صفحه
    echo '<style>
        .asg-reports-container {
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .asg-report-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .asg-report-card.full-width {
            grid-column: 1 / -1;
        }
    </style>';

    echo '<div class="wrap">';
    echo '<h1>نمودارهای گارانتی</h1>';

    echo '<div class="asg-reports-container">';
    
    // کارت نمودار وضعیت‌ها
    echo '<div class="asg-report-card">';
    echo '<h2>نمودار وضعیت‌ها</h2>';
    echo '<canvas id="statusChart"></canvas>';
    echo '</div>';

    // کارت نمودار ماهانه
    echo '<div class="asg-report-card">';
    echo '<h2>نمودار ماهانه درخواست‌ها</h2>';
    echo '<canvas id="monthlyChart"></canvas>';
    echo '</div>';

    echo '</div>'; // پایان container

    // اضافه کردن Chart.js
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
    
    // اسکریپت نمودارها
    add_action('admin_footer', function() use ($statuses, $status_counts, $monthly_data) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // نمودار وضعیت‌ها
            new Chart(document.getElementById('statusChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($statuses); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($status_counts)); ?>,
                        backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545', '#6c757d', '#007bff']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // نمودار ماهانه
            new Chart(document.getElementById('monthlyChart'), {
                type: 'line',
                data: {
                    labels: [<?php 
                        $labels = array();
                        foreach(array_reverse($monthly_data) as $data) {
                            $labels[] = "'" . $data->month . "'";
                        }
                        echo implode(',', $labels);
                    ?>],
                    datasets: [{
                        label: 'تعداد درخواست‌ها',
                        data: [<?php 
                            $counts = array();
                            foreach(array_reverse($monthly_data) as $data) {
                                $counts[] = $data->count;
                            }
                            echo implode(',', $counts);
                        ?>],
                        borderColor: '#007bff',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        });
        </script>
        <?php
    });

    echo '</div>'; // پایان wrap
}
function asg_add_help_page() {
    add_menu_page(
        'راهنمای استفاده',
        'راهنما',
        'manage_options',
        'asg-help',
        'asg_help_page_callback',
        'dashicons-editor-help',
        100
    );
}
add_action('admin_menu', 'asg_add_help_page');

function asg_help_page_callback() {
    echo '<div class="wrap">';
    echo '<h1>راهنمای استفاده</h1>';
    echo '<p>در این بخش می‌توانید راهنمایی‌های مربوط به استفاده از سیستم را مشاهده کنید.</p>';
    // محتوای راهنما را اینجا اضافه کنید
    echo '</div>';
}
?>
