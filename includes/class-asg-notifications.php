<?php
if (!defined('ABSPATH')) {
    exit('دسترسی مستقیم غیرمجاز است!');
}

class ASG_Notifications {
    private static $instance = null;
    private $table_name;
    private $db;
    private $logger;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . 'asg_notifications';
        
        // اضافه کردن اکشن‌ها
        add_action('asg_status_changed', array($this, 'send_status_notification'), 10, 3);
        add_action('asg_warranty_created', array($this, 'send_warranty_created_notification'), 10, 1);
        add_action('asg_note_added', array($this, 'send_note_notification'), 10, 2);
        
        // کرون جاب برای پاکسازی نوتیفیکیشن‌های قدیمی
        if (!wp_next_scheduled('asg_cleanup_notifications')) {
            wp_schedule_event(time(), 'daily', 'asg_cleanup_notifications');
        }
        add_action('asg_cleanup_notifications', array($this, 'cleanup_old_notifications'));
    }

    /**
     * ارسال نوتیفیکیشن تغییر وضعیت
     */
    public function send_status_notification($request_id, $old_status, $new_status) {
        $request = $this->get_request($request_id);
        if (!$request) {
            $this->log_error("درخواست با شناسه {$request_id} یافت نشد");
            return false;
        }

        $message = sprintf(
            'وضعیت درخواست گارانتی #%d از %s به %s تغییر کرد.',
            $request_id,
            $this->get_status_label($old_status),
            $this->get_status_label($new_status)
        );

        // اعمال فیلتر روی پیام
        $message = apply_filters('asg_status_notification_message', $message, $request_id, $old_status, $new_status);

        // ارسال ایمیل
        $sent = $this->send_email($request->customer_email, 'تغییر وضعیت درخواست گارانتی', $message);

        // ذخیره نوتیفیکیشن
        return $this->save_notification($request_id, $request->user_id, 'status_change', $message);
    }

    /**
     * ارسال نوتیفیکیشن ایجاد گارانتی
     */
    public function send_warranty_created_notification($request_id) {
        $request = $this->get_request($request_id);
        if (!$request) {
            $this->log_error("درخواست با شناسه {$request_id} یافت نشد");
            return false;
        }

        $message = sprintf(
            'درخواست گارانتی شما با شماره پیگیری %d با موفقیت ثبت شد.',
            $request_id
        );

        $message = apply_filters('asg_warranty_created_message', $message, $request_id);

        // ارسال ایمیل
        $sent = $this->send_email($request->customer_email, 'ثبت درخواست گارانتی', $message);

        // ذخیره نوتیفیکیشن
        return $this->save_notification($request_id, $request->user_id, 'warranty_created', $message);
    }

    /**
     * ارسال نوتیفیکیشن یادداشت جدید
     */
    public function send_note_notification($note_id, $request_id) {
        $request = $this->get_request($request_id);
        if (!$request) {
            return false;
        }

        $note = $this->get_note($note_id);
        if (!$note) {
            return false;
        }

        $message = sprintf(
            'یک یادداشت جدید برای درخواست گارانتی #%d ثبت شد.',
            $request_id
        );

        $message = apply_filters('asg_note_notification_message', $message, $note_id, $request_id);

        // ارسال ایمیل
        $sent = $this->send_email($request->customer_email, 'یادداشت جدید در درخواست گارانتی', $message);

        // ذخیره نوتیفیکیشن
        return $this->save_notification($request_id, $request->user_id, 'new_note', $message);
    }

    /**
     * دریافت نوتیفیکیشن‌های کاربر
     */
    public function get_user_notifications($user_id, $limit = 10, $offset = 0, $unread_only = false) {
        $where = array(
            'user_id' => $user_id
        );

        if ($unread_only) {
            $where['is_read'] = 0;
        }

        $notifications = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE user_id = %d " . ($unread_only ? "AND is_read = 0 " : "") . "
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                $user_id, $limit, $offset
            )
        );

        return array_map(function($notification) {
            $notification->created_at_formatted = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($notification->created_at)
            );
            return $notification;
        }, $notifications);
    }

    /**
     * علامت‌گذاری نوتیفیکیشن به عنوان خوانده شده
     */
    public function mark_as_read($notification_id) {
        return $this->db->update(
            $this->table_name,
            array('is_read' => 1),
            array('id' => $notification_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * علامت‌گذاری همه نوتیفیکیشن‌های کاربر به عنوان خوانده شده
     */
    public function mark_all_as_read($user_id) {
        return $this->db->update(
            $this->table_name,
            array('is_read' => 1),
            array('user_id' => $user_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * پاکسازی نوتیفیکیشن‌های قدیمی
     */
    public function cleanup_old_notifications($days = 30) {
        return $this->db->query($this->db->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    /**
     * شمارش نوتیفیکیشن‌های خوانده نشده
     */
    public function count_unread($user_id) {
        return (int) $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }

    /**
     * ارسال ایمیل
     */
    private function send_email($to, $subject, $message) {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($to, $subject, $this->get_email_template($message), $headers);
        
        if (!$sent) {
            $this->log_error(sprintf('خطا در ارسال ایمیل به %s: %s', $to, $subject));
        }
        
        return $sent;
    }

    /**
     * ذخیره نوتیفیکیشن
     */
    private function save_notification($request_id, $user_id, $type, $message) {
        return $this->db->insert(
            $this->table_name,
            array(
                'request_id' => $request_id,
                'user_id' => $user_id,
                'type' => $type,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * دریافت اطلاعات درخواست
     */
    private function get_request($request_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}asg_guarantee_requests WHERE id = %d",
            $request_id
        ));
    }

    /**
     * دریافت اطلاعات یادداشت
     */
    private function get_note($note_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}asg_notes WHERE id = %d",
            $note_id
        ));
    }

    /**
     * قالب ایمیل
     */
    private function get_email_template($content) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>سیستم گارانتی</title>
        </head>
        <body style="font-family: Tahoma, Arial, sans-serif; background-color: #f6f6f6; margin: 0; padding: 0;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #333; margin: 0;">سیستم گارانتی</h1>
                </div>
                <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo wpautop($content); ?>
                </div>
                <div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
                    <p>این ایمیل به صورت خودکار ارسال شده است. لطفا به آن پاسخ ندهید.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * دریافت برچسب وضعیت
     */
    private function get_status_label($status) {
        $statuses = array(
            'pending' => 'در انتظار بررسی',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            'processing' => 'در حال پردازش',
            'completed' => 'تکمیل شده'
        );

        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * ثبت خطا
     */
    private function log_error($message) {
        error_log(sprintf('[ASG Notifications] %s', $message));
    }

    /**
     * ایجاد جدول نوتیفیکیشن‌ها
     */
    public static function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'asg_notifications';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY request_id (request_id),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// هنگام فعال‌سازی افزونه
register_activation_hook(__FILE__, array('ASG_Notifications', 'create_table'));