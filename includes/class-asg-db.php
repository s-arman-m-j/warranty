<?php
/**
 * کلاس مدیریت دیتابیس
 * 
 * این کلاس برای مدیریت عملیات دیتابیس پلاگین گارانتی استفاده می‌شود
 */

if (!defined('ABSPATH')) {
    exit;
}

class ASG_DB {
    /**
     * نمونه یکتای کلاس
     *
     * @var ASG_DB
     */
    private static $instance = null;

    /**
     * پیشوند جداول پلاگین
     *
     * @var string
     */
    private $table_prefix;

    /**
     * سازنده کلاس
     */
    private function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'asg_';
    }

    /**
     * دریافت نمونه یکتا از کلاس
     *
     * @return ASG_DB
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ایجاد جداول مورد نیاز پلاگین
     *
     * @return void
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // جدول درخواست‌های گارانتی
        $table_requests = $this->table_prefix . 'guarantee_requests';
        $sql_requests = "CREATE TABLE IF NOT EXISTS $table_requests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            tamin_user_id bigint(20),
            defect_description text,
            expert_comment text,
            status varchar(50),
            receipt_day int(2),
            receipt_month varchar(20),
            receipt_year int(4),
            image_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY tamin_user_id (tamin_user_id)
        ) $charset_collate;";

        // جدول یادداشت‌ها
        $table_notes = $this->table_prefix . 'guarantee_notes';
        $sql_notes = "CREATE TABLE IF NOT EXISTS $table_notes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            note text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY request_id (request_id)
        ) $charset_collate;";

        // جدول نوتیفیکیشن‌ها
        $table_notifications = $this->table_prefix . 'notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY request_id (request_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_requests);
        dbDelta($sql_notes);
        dbDelta($sql_notifications);
    }

    /**
     * افزودن درخواست گارانتی جدید
     *
     * @param array $data داده‌های درخواست
     * @return int|false شناسه درخواست یا false در صورت خطا
     */
    public function insert_request($data) {
        global $wpdb;
        $table = $this->table_prefix . 'guarantee_requests';
        
        $result = $wpdb->insert($table, array(
            'product_id' => $data['product_id'],
            'user_id' => $data['user_id'],
            'tamin_user_id' => $data['tamin_user_id'],
            'defect_description' => $data['defect_description'],
            'expert_comment' => $data['expert_comment'],
            'status' => $data['status'],
            'receipt_day' => $data['receipt_day'],
            'receipt_month' => $data['receipt_month'],
            'receipt_year' => $data['receipt_year'],
            'image_id' => $data['image_id']
        ));

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * به‌روزرسانی درخواست گارانتی
     *
     * @param int $request_id شناسه درخواست
     * @param array $data داده‌های جدید
     * @return bool نتیجه به‌روزرسانی
     */
    public function update_request($request_id, $data) {
        global $wpdb;
        $table = $this->table_prefix . 'guarantee_requests';
        
        return $wpdb->update($table, $data, array('id' => $request_id));
    }

    /**
     * دریافت یک درخواست گارانتی با شناسه
     *
     * @param int $request_id شناسه درخواست
     * @return object|null اطلاعات درخواست
     */
    public function get_request($request_id) {
        global $wpdb;
        $table = $this->table_prefix . 'guarantee_requests';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $request_id
        ));
    }

    /**
     * افزودن یادداشت به درخواست
     *
     * @param int $request_id شناسه درخواست
     * @param string $note متن یادداشت
     * @return int|false شناسه یادداشت یا false در صورت خطا
     */
    public function add_note($request_id, $note) {
        global $wpdb;
        $table = $this->table_prefix . 'guarantee_notes';
        
        $result = $wpdb->insert($table, array(
            'request_id' => $request_id,
            'note' => $note
        ));

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * دریافت یادداشت‌های یک درخواست
     *
     * @param int $request_id شناسه درخواست
     * @return array لیست یادداشت‌ها
     */
    public function get_notes($request_id) {
        global $wpdb;
        $table = $this->table_prefix . 'guarantee_notes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE request_id = %d ORDER BY created_at DESC",
            $request_id
        ));
    }

    /**
     * افزودن نوتیفیکیشن
     *
     * @param int $request_id شناسه درخواست
     * @param string $type نوع نوتیفیکیشن
     * @param string $message متن پیام
     * @return int|false شناسه نوتیفیکیشن یا false در صورت خطا
     */
    public function add_notification($request_id, $type, $message) {
        global $wpdb;
        $table = $this->table_prefix . 'notifications';
        
        $result = $wpdb->insert($table, array(
            'request_id' => $request_id,
            'type' => $type,
            'message' => $message
        ));

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * دریافت نوتیفیکیشن‌های خوانده نشده
     *
     * @return array لیست نوتیفیکیشن‌ها
     */
    public function get_unread_notifications() {
        global $wpdb;
        $table = $this->table_prefix . 'notifications';
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_read = 0 ORDER BY created_at DESC"
        );
    }

    /**
     * علامت‌گذاری نوتیفیکیشن به عنوان خوانده شده
     *
     * @param int $notification_id شناسه نوتیفیکیشن
     * @return bool نتیجه به‌روزرسانی
     */
    public function mark_notification_as_read($notification_id) {
        global $wpdb;
        $table = $this->table_prefix . 'notifications';
        
        return $wpdb->update(
            $table,
            array('is_read' => 1),
            array('id' => $notification_id)
        );
    }
}
