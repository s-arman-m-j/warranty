<?php
namespace ASG;

use Exception;
use wpdb;

if (!defined('ABSPATH')) {
    exit('دسترسی مستقیم غیرمجاز است!');
}

/**
 * کلاس مدیریت پایگاه داده
 */
class DB {
    private wpdb $wpdb;
    private array $tables;
    private Cache $cache;

    /**
     * تنظیمات پایگاه داده
     */
    private const CACHE_GROUP = 'asg_db_cache';
    private const CACHE_TIME = 3600;
    private const MAX_QUERY_TIME = 5;

    /**
     * ساختارهای جداول
     */
    private const TABLE_SCHEMAS = [
        'requests' => [
            'id' => ['BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT', 'PRIMARY KEY'],
            'product_id' => ['BIGINT(20) UNSIGNED NOT NULL', 'idx_user_product'],
            'user_id' => ['BIGINT(20) UNSIGNED NOT NULL', 'idx_user_product'],
            'tamin_user_id' => ['BIGINT(20) UNSIGNED DEFAULT NULL', 'idx_tamin'],
            'defect_description' => ['TEXT NOT NULL', 'FULLTEXT idx_search'],
            'expert_comment' => ['TEXT', 'FULLTEXT idx_search'],
            'status' => ['VARCHAR(50) NOT NULL DEFAULT "pending"', 'idx_status_date'],
            'receipt_day' => ['TINYINT UNSIGNED NOT NULL', null],
            'receipt_month' => ['VARCHAR(20) NOT NULL', null],
            'receipt_year' => ['SMALLINT UNSIGNED NOT NULL', null],
            'image_id' => ['BIGINT(20) UNSIGNED DEFAULT NULL', null],
            'created_at' => ['DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'idx_status_date'],
            'updated_at' => ['DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', null]
        ],
        'notes' => [
            'id' => ['BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT', 'PRIMARY KEY'],
            'request_id' => ['BIGINT(20) UNSIGNED NOT NULL', 'idx_request'],
            'note' => ['TEXT NOT NULL', null],
            'created_by' => ['BIGINT(20) UNSIGNED NOT NULL', 'idx_user_date'],
            'created_at' => ['DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', 'idx_user_date']
        ],
        'meta' => [
            'meta_id' => ['BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT', 'PRIMARY KEY'],
            'request_id' => ['BIGINT(20) UNSIGNED NOT NULL', 'idx_request_key'],
            'meta_key' => ['VARCHAR(255) NOT NULL', 'idx_request_key'],
            'meta_value' => ['LONGTEXT', null]
        ]
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->cache = new Cache();
        
        $this->tables = array_combine(
            array_keys(self::TABLE_SCHEMAS),
            array_map(
                fn($table) => $wpdb->prefix . "asg_guarantee_$table",
                array_keys(self::TABLE_SCHEMAS)
            )
        );

        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_action('after_switch_theme', [$this, 'create_tables']);
        add_action('plugins_loaded', [$this, 'check_db_version']);
        add_action('asg_after_request_update', [$this, 'clear_request_cache']);
        add_action('asg_after_note_add', [$this, 'clear_notes_cache']);
    }

    public function create_tables(): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();

        try {
            $this->wpdb->query('START TRANSACTION');

            foreach (self::TABLE_SCHEMAS as $table_name => $columns) {
                $sql = "CREATE TABLE IF NOT EXISTS {$this->tables[$table_name]} (\n";
                
                // ساخت ستون‌ها
                foreach ($columns as $column => $def) {
                    $sql .= "    $column {$def[0]},\n";
                }

                // ساخت ایندکس‌ها
                $indexes = array_filter(array_column($columns, 1));
                if (!empty($indexes)) {
                    $sql .= "    " . implode(",\n    ", array_filter($indexes)) . "\n";
                }

                $sql .= ") $charset_collate;";
                
                dbDelta($sql);
            }

            $this->wpdb->query('COMMIT');
            update_option('asg_db_version', ASG_VERSION);

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('ASG DB Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function get_requests(array $args = [], int $page = 1, int $per_page = 20): array {
        $cache_key = sprintf('requests_%s', md5(serialize(func_get_args())));
        $cached_results = $this->cache->get($cache_key, self::CACHE_GROUP);

        if ($cached_results !== false) {
            return $cached_results;
        }

        $args = $this->parse_request_args($args);
        [$where_clause, $prepare_values] = $this->build_where_clause($args);

        $this->set_query_timeout();

        $query = $this->build_requests_query(
            $where_clause,
            $args['orderby'],
            $args['order'],
            $per_page,
            ($page - 1) * $per_page,
            $prepare_values
        );

        $results = $this->wpdb->get_results($query);
        $total = (int) $this->wpdb->get_var('SELECT FOUND_ROWS()');

        $response = [
            'items' => $results,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ];

        $this->cache->set($cache_key, $response, self::CACHE_GROUP, self::CACHE_TIME);

        return $response;
    }

    public function add_request(array $data): int {
        try {
            $this->wpdb->query('START TRANSACTION');

            $data = $this->sanitize_request_data($data);
            $meta = $data['meta'] ?? [];
            unset($data['meta']);

            $result = $this->wpdb->insert(
                $this->tables['requests'],
                $data,
                $this->get_request_formats($data)
            );

            if (!$result) {
                throw new Exception('Error inserting request: ' . $this->wpdb->last_error);
            }

            $request_id = (int) $this->wpdb->insert_id;

            if (!empty($meta)) {
                $this->add_request_meta($request_id, $meta);
            }

            $this->wpdb->query('COMMIT');
            $this->clear_request_cache();

            do_action('asg_after_request_add', $request_id, $data);

            return $request_id;

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('ASG DB Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function parse_request_args(array $args): array {
        return wp_parse_args($args, [
            'user_id' => '',
            'product_id' => '',
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        ]);
    }

    private function build_where_clause(array $args): array {
        $where = ['1=1'];
        $prepare_values = [];

        if (!empty($args['user_id'])) {
            $where[] = 'r.user_id = %d';
            $prepare_values[] = (int) $args['user_id'];
        }

        if (!empty($args['search'])) {
            $where[] = 'MATCH(r.defect_description, r.expert_comment) AGAINST (%s IN BOOLEAN MODE)';
            $prepare_values[] = $args['search'] . '*';
        }

        return [
            implode(' AND ', $where),
            $prepare_values
        ];
    }

    private function set_query_timeout(): void {
        $this->wpdb->query(sprintf(
            'SET SESSION MAX_EXECUTION_TIME=%d',
            self::MAX_QUERY_TIME * 1000
        ));
    }

    private function build_requests_query(
        string $where_clause,
        string $orderby,
        string $order,
        int $limit,
        int $offset,
        array $prepare_values
    ): string {
        return $this->wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS 
                r.*, 
                GROUP_CONCAT(n.note ORDER BY n.created_at DESC) as notes,
                COUNT(DISTINCT n.id) as notes_count
            FROM {$this->tables['requests']} r
            LEFT JOIN {$this->tables['notes']} n ON r.id = n.request_id
            WHERE {$where_clause}
            GROUP BY r.id
            ORDER BY r.%s %s
            LIMIT %d OFFSET %d",
            array_merge(
                $prepare_values,
                [$orderby, $order, $limit, $offset]
            )
        );
    }

    private function sanitize_request_data(array $data): array {
        return array_map(function($value) {
            if (is_string($value)) {
                return sanitize_text_field($value);
            }
            return $value;
        }, $data);
    }

    private function get_request_formats(array $data): array {
        $formats = [
            'product_id' => '%d',
            'user_id' => '%d',
            'tamin_user_id' => '%d',
            'defect_description' => '%s',
            'expert_comment' => '%s',
            'status' => '%s',
            'receipt_day' => '%d',
            'receipt_month' => '%s',
            'receipt_year' => '%d',
            'image_id' => '%d'
        ];

        return array_intersect_key($formats, $data);
    }

    public function clear_request_cache(?int $request_id = null): void {
        if ($request_id) {
            $this->cache->delete("request_$request_id", self::CACHE_GROUP);
        } else {
            $this->cache->delete_group(self::CACHE_GROUP);
        }
    }

    private function add_request_meta(int $request_id, array $meta): void {
        foreach ($meta as $key => $value) {
            $this->wpdb->insert(
                $this->tables['meta'],
                [
                    'request_id' => $request_id,
                    'meta_key' => sanitize_key($key),
                    'meta_value' => maybe_serialize($value)
                ],
                ['%d', '%s', '%s']
            );
        }
    }
}