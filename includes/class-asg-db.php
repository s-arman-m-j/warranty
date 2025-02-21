<?php
if (!defined('ABSPATH')) {
    exit;
}

class ASG_DB {
    private $wpdb;
    private $table_requests;
    private $table_notes;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
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
            $where[] = '(r.defect_description LIKE %s OR r.expert_comment LIKE %s)';
            $prepare_values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }

        $offset = ($page - 1) * $per_page;
        $where_clause = implode(' AND ', $where);
        
        $query = $this->wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS r.*
            FROM {$this->table_requests} r
            WHERE {$where_clause}
            ORDER BY r.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d",
            array_merge($prepare_values, array($per_page, $offset))
        );

        $results = $this->wpdb->get_results($query);
        $total = $this->wpdb->get_var('SELECT FOUND_ROWS()');

        return array(
            'items' => $results,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        );
    }

    /**
     * افزودن درخواست جدید
     */
    public function add_request($data) {
        $result = $this->wpdb->insert($this->table_requests, $data);
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * به‌روزرسانی درخواست
     */
    public function update_request($id, $data) {
        return $this->wpdb->update(
            $this->table_requests,
            $data,
            array('id' => $id)
        );
    }

    /**
     * حذف درخواست
     */
    public function delete_request($id) {
        return $this->wpdb->delete(
            $this->table_requests,
            array('id' => $id)
        );
    }

    /**
     * افزودن یادداشت
     */
    public function add_note($data) {
        $result = $this->wpdb->insert($this->table_notes, $data);
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * دریافت یادداشت‌های یک درخواست
     */
    public function get_notes($request_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_notes}
                WHERE request_id = %d
                ORDER BY created_at DESC",
                $request_id
            )
        );
    }
}
?>