<?php
namespace ASG;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit('دسترسی مستقیم غیرمجاز است!');
}

/**
 * کلاس API برای مدیریت درخواست‌های REST
 */
class API {
    private static ?self $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('warranty/v1', '/requests', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_requests'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_request'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route('warranty/v1', '/requests/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_request'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_request'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);

        register_rest_route('warranty/v1', '/statistics', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_statistics'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
    }

    public function check_permission(): bool {
        // اینجا می‌توانید منطق بررسی دسترسی را پیاده‌سازی کنید
        return true;
    }

    public function get_requests(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $per_page = (int)($request->get_param('per_page') ?: 10);
        $page = (int)($request->get_param('page') ?: 1);
        $offset = ($page - 1) * $per_page;

        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}asg_guarantee_requests 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        return rest_ensure_response($requests);
    }

    public function get_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;

        $id = (int)$request->get_param('id');
        
        $warranty = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}asg_guarantee_requests WHERE id = %d",
            $id
        ));

        if (!$warranty) {
            return new WP_Error(
                'not_found', 
                'درخواست مورد نظر یافت نشد.', 
                ['status' => 404]
            );
        }

        return rest_ensure_response($warranty);
    }

    public function create_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;

        $params = $request->get_params();
        
        $data = [
            'customer_name' => sanitize_text_field($params['customer_name']),
            'customer_email' => sanitize_email($params['customer_email']),
            'customer_phone' => sanitize_text_field($params['customer_phone']),
            'serial_number' => sanitize_text_field($params['serial_number']),
            'purchase_date' => sanitize_text_field($params['purchase_date']),
            'description' => sanitize_textarea_field($params['description']),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'asg_guarantee_requests',
            $data
        );

        if (!$result) {
            return new WP_Error(
                'insert_failed', 
                'خطا در ثبت درخواست', 
                ['status' => 500]
            );
        }

        $request_id = $wpdb->insert_id;
        do_action('asg_warranty_created', $request_id);

        return rest_ensure_response([
            'id' => $request_id,
            'message' => 'درخواست با موفقیت ثبت شد.'
        ]);
    }

    public function update_request(WP_REST_Request $request): WP_REST_Response|WP_Error {
        global $wpdb;

        $id = (int)$request->get_param('id');
        $params = $request->get_params();

        $old_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}asg_guarantee_requests WHERE id = %d",
            $id
        ));

        if (!$old_request) {
            return new WP_Error(
                'not_found', 
                'درخواست مورد نظر یافت نشد.', 
                ['status' => 404]
            );
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'asg_guarantee_requests',
            [
                'status' => sanitize_text_field($params['status']),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $id]
        );

        if ($result === false) {
            return new WP_Error(
                'update_failed', 
                'خطا در بروزرسانی درخواست', 
                ['status' => 500]
            );
        }

        do_action('asg_status_changed', $id, $old_request->status, $params['status']);

        return rest_ensure_response([
            'message' => 'درخواست با موفقیت بروزرسانی شد.'
        ]);
    }

    public function get_statistics(): WP_REST_Response {
        global $wpdb;

        // آمار وضعیت‌ها
        $status_stats = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM {$wpdb->prefix}asg_guarantee_requests 
            GROUP BY status
        ");

        // آمار ماهانه
        $monthly_stats = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM {$wpdb->prefix}asg_guarantee_requests 
            GROUP BY month 
            ORDER BY month DESC 
            LIMIT 12
        ");

        return rest_ensure_response([
            'status_stats' => $status_stats,
            'monthly_stats' => $monthly_stats
        ]);
    }
}