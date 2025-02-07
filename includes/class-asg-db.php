<?php
if (!defined('ABSPATH')) {
    exit;
}

class ASG_DB {
    private $wpdb;
    private $cache;
    private $table_requests;
    private $table_notes;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->cache = new ASG_Cache();
        $this->table_requests = $wpdb->prefix . 'asg_guarantee_requests';
        $this->table_notes = $wpdb->prefix . 'asg_guarantee_notes';
    }

    /**
     * ایجاد جداول مورد نیاز
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_requests = "CREATE TABLE IF NOT EXISTS $this->table_requests (
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY user_id_index (user_id),
            KEY product_id_index (product_id),
            KEY status_index (status),
            KEY created_at_index (created_at)
        ) $charset_collate;";

        $sql_notes = "CREATE TABLE IF NOT EXISTS $this->table_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            note TEXT,
            created_by INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY request_id_index (request_id),
            KEY created_by_index (created_by)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_requests);
        dbDelta($sql_notes);
    }

    /**
     * دریافت درخواست‌های گارانتی با پیجینیشن
     */
    public function get_requests($args = array(), $page = 1, $per_page = 20) {
        $cache_key = 'requests_' . md5(serialize($args) . $page . $per_page);
        $results = $this->cache->get($cache_key);

        if ($results === false) {
            $defaults = array(
                'user_id' => '',
                'product_id' => '',
                'status' => '',
                'date_from' => '',
                'date_to' => '',
                'search' => '',
                'orderby' => 'created_at',
                'order' => 'DESC'
            );

            $args = wp_parse_args($args, $defaults);
            $where = array('1=1');
            $prepare_values = array();

            // اعمال فیلترها
            if (!empty($args['user_id'])) {
                $where[] = 'r.user_id = %d';
                $prepare_values[] = $args['user_id'];
            }

            if (!empty($args['product_id'])) {
                $where[] = 'r.product_id = %d';
                $prepare_values[] = $args['product_id'];
            }

            if (!empty($args['status'])) {
                $where[] = 'r.status = %s';
                $prepare_values[] = $args['status'];
            }

            if (!empty($args['date_from'])) {
                $where[] = 'r.created_at >= %s';
                $prepare_values[] = $args['date_from'];
            }

            if (!empty($args['date_to'])) {
                $where[] = 'r.created_at <= %s';
                $prepare_values[] = $args['date_to'];
            }

            if (!empty($args['search'])) {
                $where[] = '(p.post_title LIKE %s OR u.display_name LIKE %s OR r.defect_description LIKE %s)';
                $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
                $prepare_values[] = $search_term;
                $prepare_values[] = $search_term;
                $prepare_values[] = $search_term;
            }

            // محاسبه offset برای پیجینیشن
            $offset = ($page - 1) * $per_page;

            // ساخت کوئری
            $query = "SELECT r.*, 
                      p.post_title as product_name,
                      u.display_name as user_name,
                      tu.display_name as tamin_name,
                      (SELECT COUNT(*) FROM {$this->table_notes} n WHERE n.request_id = r.id) as notes_count
                      FROM {$this->table_requests} r
                      INNER JOIN {$this->wpdb->posts} p ON r.product_id = p.ID
                      INNER JOIN {$this->wpdb->users} u ON r.user_id = u.ID
                      LEFT JOIN {$this->wpdb->users} tu ON r.tamin_user_id = tu.ID
                      WHERE " . implode(' AND ', $where) . "
                      ORDER BY r.{$args['orderby']} {$args['order']}
                      LIMIT %d OFFSET %d";

            $prepare_values[] = $per_page;
            $prepare_values[] = $offset;

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $prepare_values)
            );

            $this->cache->set($cache_key, $results);
        }

        return $results;
    }

    /**
     * شمارش کل رکوردها برای پیجینیشن
     */
    public function count_total_requests($args = array()) {
        $cache_key = 'total_requests_' . md5(serialize($args));
        $total = $this->cache->get($cache_key);

        if ($total === false) {
            $defaults = array(
                'user_id' => '',
                'product_id' => '',
                'status' => '',
                'date_from' => '',
                'date_to' => '',
                'search' => ''
            );

            $args = wp_parse_args($args, $defaults);
            $where = array('1=1');
            $prepare_values = array();

            // اعمال فیلترها مشابه get_requests
            if (!empty($args['user_id'])) {
                $where[] = 'r.user_id = %d';
                $prepare_values[] = $args['user_id'];
            }

            // ... سایر فیلترها

            $query = "SELECT COUNT(*) FROM {$this->table_requests} r
                     INNER JOIN {$this->wpdb->posts} p ON r.product_id = p.ID
                     INNER JOIN {$this->wpdb->users} u ON r.user_id = u.ID
                     WHERE " . implode(' AND ', $where);

            $total = $this->wpdb->get_var(
                $this->wpdb->prepare($query, $prepare_values)
            );

            $this->cache->set($cache_key, $total);
        }

        return $total;
    }

    /**
     * درج درخواست جدید
     */
    public function insert_request($data) {
        $result = $this->wpdb->insert($this->table_requests, $data);
        if ($result) {
            $id = $this->wpdb->insert_id;
            do_action('asg_after_guarantee_update', $id);
            return $id;
        }
        return false;
    }

    /**
     * به‌روزرسانی درخواست
     */
    public function update_request($id, $data) {
        $result = $this->wpdb->update(
            $this->table_requests,
            $data,
            array('id' => $id)
        );
        if ($result !== false) {
            do_action('asg_after_guarantee_update', $id);
        }
        return $result;
    }

    /**
     * حذف درخواست
     */
    public function delete_request($id) {
        // حذف یادداشت‌های مرتبط
        $this->wpdb->delete($this->table_notes, array('request_id' => $id));
        
        $result = $this->wpdb->delete(
            $this->table_requests, 
            array('id' => $id)
        );
        
        if ($result) {
            do_action('asg_after_guarantee_delete', $id);
        }
        
        return $result;
    }

    /**
     * دریافت یک درخواست
     */
    public function get_request($id) {
        $cache_key = 'guarantee_' . $id;
        $request = $this->cache->get($cache_key);

        if ($request === false) {
            $request = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT r.*, 
                 p.post_title as product_name,
                 u.display_name as user_name,
                 tu.display_name as tamin_name
                 FROM {$this->table_requests} r
                 INNER JOIN {$this->wpdb->posts} p ON r.product_id = p.ID
                 INNER JOIN {$this->wpdb->users} u ON r.user_id = u.ID
                 LEFT JOIN {$this->wpdb->users} tu ON r.tamin_user_id = tu.ID
                 WHERE r.id = %d",
                $id
            ));

            if ($request) {
                $this->cache->set($cache_key, $request);
            }
        }

        return $request;
    }

    /**
     * افزودن یادداشت
     */
    public function add_note($data) {
        $result = $this->wpdb->insert($this->table_notes, $data);
        if ($result) {
            do_action('asg_after_note_add', $data['request_id']);
            return $this->wpdb->insert_id;
        }
        return false;
    }

    /**
     * دریافت یادداشت‌های یک درخواست
     */
    public function get_notes($request_id) {
        $cache_key = 'guarantee_notes_' . $request_id;
        $notes = $this->cache->get($cache_key);

        if ($notes === false) {
            $notes = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT n.*, u.display_name as user_name
                 FROM {$this->table_notes} n
                 LEFT JOIN {$this->wpdb->users} u ON n.created_by = u.ID
                 WHERE n.request_id = %d
                 ORDER BY n.created_at DESC",
                $request_id
            ));

            if ($notes) {
                $this->cache->set($cache_key, $notes);
            }
        }

        return $notes;
    }

    /**
     * دریافت آمار گارانتی‌ها
     */
    public function get_guarantee_stats() {
        $cache_key = 'guarantee_stats';
        $stats = $this->cache->get($cache_key);

        if ($stats === false) {
            $stats = array(
                'total' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_requests}"),
                'by_status' => $this->wpdb->get_results(
                    "SELECT status, COUNT(*) as count 
                     FROM {$this->table_requests} 
                     GROUP BY status",
                    OBJECT_K
                ),
                'recent' => $this->wpdb->get_var(
                    "SELECT COUNT(*) 
                     FROM {$this->table_requests} 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
                )
            );

            $this->cache->set($cache_key, $stats, 3600); // کش برای یک ساعت
        }

        return $stats;
    }

    /**
     * دریافت گزارش‌های پیشرفته
     */
    public function get_advanced_reports($args = array()) {
        $defaults = array(
            'date_from' => '',
            'date_to' => '',
            'group_by' => 'day', // day, month, year
            'status' => ''
        );

        $args = wp_parse_args($args, $defaults);
        $cache_key = 'advanced_reports_' . md5(serialize($args));
        $reports = $this->cache->get($cache_key);

        if ($reports === false) {
            $where = array('1=1');
            $prepare_values = array();

            if (!empty($args['date_from'])) {
                $where[] = 'created_at >= %s';
                $prepare_values[] = $args['date_from'];
            }

            if (!empty($args['date_to'])) {
                $where[] = 'created_at <= %s';
                $prepare_values[] = $args['date_to'];
            }

            if (!empty($args['status'])) {
                $where[] = 'status = %s';
                $prepare_values[] = $args['status'];
            }

            $group_format = '%Y-%m-%d';
            switch ($args['group_by']) {
                case 'month':
                    $group_format = '%Y-%m';
                    break;
                case 'year':
                    $group_format = '%Y';
                    break;
            }

            $query = $this->wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '$group_format') as date,
                        COUNT(*) as count,
                        status
                 FROM {$this->table_requests}
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY date, status
                 ORDER BY date DESC",
                $prepare_values
            );

            $reports = $this->wpdb->get_results($query);
            $this->cache->set($cache_key, $reports);
        }

        return $reports;
    }

    /**
     * به‌روزرسانی دسته‌ای وضعیت‌ها
     */
    public function bulk_update_status($ids, $status) {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        $query = $this->wpdb->prepare(
            "UPDATE {$this->table_requests} 
             SET status = %s 
             WHERE id IN ($placeholders)",
            array_merge(array($status), $ids)
        );

        $result = $this->wpdb->query($query);

        if ($result !== false) {
            foreach ($ids as $id) {
                do_action('asg_after_guarantee_update', $id);
            }
        }

        return $result;
    }

    /**
     * پاکسازی داده‌های قدیمی از کش
     */
    public function cleanup_old_data() {
        // پاک کردن کش‌های قدیمی‌تر از یک ماه
        $this->wpdb->query(
            "DELETE FROM {$this->wpdb->options} 
             WHERE option_name LIKE '_transient_asg_cache_%' 
             AND option_value < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    /**
     * اعتبارسنجی داده‌های ورودی
     */
    public function validate_request_data($data) {
        $errors = array();

        // بررسی محصول
        if (empty($data['product_id']) || !wc_get_product($data['product_id'])) {
            $errors[] = 'محصول نامعتبر است';
        }

        // بررسی کاربر
        if (empty($data['user_id']) || !get_user_by('id', $data['user_id'])) {
            $errors[] = 'کاربر نامعتبر است';
        }

        // بررسی تاریخ
        if (!empty($data['receipt_day']) && !empty($data['receipt_month']) && !empty($data['receipt_year'])) {
            if (!checkdate($data['receipt_month'], $data['receipt_day'], $data['receipt_year'])) {
                $errors[] = 'تاریخ نامعتبر است';
            }
        }

        return $errors;
    }

    /**
     * پاکسازی داده‌های ورودی
     */
    public function sanitize_request_data($data) {
        return array(
            'product_id' => isset($data['product_id']) ? intval($data['product_id']) : 0,
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : 0,
            'tamin_user_id' => isset($data['tamin_user_id']) ? intval($data['tamin_user_id']) : null,
            'defect_description' => isset($data['defect_description']) ? sanitize_textarea_field($data['defect_description']) : '',
            'expert_comment' => isset($data['expert_comment']) ? sanitize_textarea_field($data['expert_comment']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : '',
            'receipt_day' => isset($data['receipt_day']) ? intval($data['receipt_day']) : 0,
            'receipt_month' => isset($data['receipt_month']) ? sanitize_text_field($data['receipt_month']) : '',
            'receipt_year' => isset($data['receipt_year']) ? intval($data['receipt_year']) : 0,
            'image_id' => isset($data['image_id']) ? intval($data['image_id']) : null
        );
    }
}