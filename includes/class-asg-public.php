<?php
if (!defined('ABSPATH')) {
    exit;
}

class ASG_Public {
    private $db;
    private $security;

    public function __construct() {
        $this->db = new ASG_DB();
        $this->security = new ASG_Security();

        // تغییر اولویت اضافه کردن endpoint ها به بعد از Digits
        add_action('init', array($this, 'add_endpoints'), 99);
        
        // اضافه کردن چک برای Digits
        add_action('template_redirect', array($this, 'check_digits_compatibility'), 1);
        
        // اکشن‌های مربوط به حساب کاربری
        add_filter('woocommerce_account_menu_items', array($this, 'add_guarantee_menu_items'));
        add_action('woocommerce_account_guarantee-requests_endpoint', array($this, 'guarantee_requests_content'));
        add_action('woocommerce_account_view-guarantee_endpoint', array($this, 'view_guarantee_content'));
        
        // اکشن‌های Ajax
        add_action('wp_ajax_asg_add_note', array($this, 'handle_add_note'));
        
        // اضافه کردن assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        
        // فیلترهای URL
        $this->init_url_filters();
    }

    /**
     * بررسی سازگاری با Digits
     */
    public function check_digits_compatibility() {
        if (class_exists('Digits')) {
            if (function_exists('digits_is_login_page') && digits_is_login_page()) {
                return;
            }
        }
    }

    /**
     * اضافه کردن endpoint ها به حساب کاربری
     */
    public function add_endpoints() {
        if (class_exists('Digits')) {
            if (!is_user_logged_in() && function_exists('digits_is_login_page') && digits_is_login_page()) {
                return;
            }
        }

        if (is_user_logged_in()) {
            add_rewrite_endpoint('guarantee-requests', EP_ROOT | EP_PAGES);
            add_rewrite_endpoint('view-guarantee', EP_ROOT | EP_PAGES);
        }
    }

    /**
     * اضافه کردن منوی گارانتی به حساب کاربری
     */
    public function add_guarantee_menu_items($items) {
        if (!is_user_logged_in()) {
            return $items;
        }

        $new_items = array();
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'dashboard') {
                $new_items['guarantee-requests'] = 'پیگیری گارانتی';
            }
        }
        return $new_items;
    }

    /**
     * نمایش لیست درخواست‌های گارانتی
     */
    public function guarantee_requests_content() {
        $user_id = get_current_user_id();
        $page = (get_query_var('paged')) ? get_query_var('paged') : 1;
        $per_page = 10;

        $requests = $this->db->get_requests(
            array('user_id' => $user_id),
            $page,
            $per_page
        );

        $total_requests = $this->db->count_total_requests(array('user_id' => $user_id));
        $total_pages = ceil($total_requests / $per_page);

        require_once ASG_PLUGIN_DIR . 'templates/public/guarantee-requests.php';
    }

    /**
     * نمایش جزئیات یک درخواست گارانتی
     */
    public function view_guarantee_content() {
        $user_id = get_current_user_id();
        $request_id = get_query_var('view-guarantee');

        if (!$request_id) {
            wc_add_notice('درخواست مورد نظر یافت نشد.', 'error');
            wp_redirect(wc_get_account_endpoint_url('guarantee-requests'));
            exit;
        }

        $request = $this->db->get_request($request_id);

        if (!$request || $request->user_id != $user_id) {
            wc_add_notice('شما اجازه دسترسی به این درخواست را ندارید.', 'error');
            wp_redirect(wc_get_account_endpoint_url('guarantee-requests'));
            exit;
        }

        $notes = $this->db->get_notes($request_id);
        require_once ASG_PLUGIN_DIR . 'templates/public/view-guarantee.php';
    }

    /**
     * افزودن یادداشت با Ajax
     */
    public function handle_add_note() {
        check_ajax_referer('asg_add_note', 'nonce');

        $request_id = intval($_POST['request_id']);
        $note = sanitize_textarea_field($_POST['note']);
        $user_id = get_current_user_id();

        $request = $this->db->get_request($request_id);
        if (!$request || $request->user_id != $user_id) {
            wp_send_json_error('دسترسی غیرمجاز');
        }

        $note_id = $this->db->add_note(array(
            'request_id' => $request_id,
            'note' => $note,
            'created_by' => $user_id
        ));

        if ($note_id) {
            wp_send_json_success(array(
                'message' => 'یادداشت با موفقیت اضافه شد.',
                'note_id' => $note_id,
                'date' => current_time('mysql')
            ));
        } else {
            wp_send_json_error('خطا در ثبت یادداشت');
        }
    }

    /**
     * اضافه کردن فایل‌های CSS و JS
     */
    public function enqueue_public_scripts() {
        if (!is_account_page()) {
            return;
        }

        wp_enqueue_style(
            'asg-public-style',
            ASG_PLUGIN_URL . 'assets/css/asg-public.css',
            array(),
            ASG_VERSION
        );

        wp_enqueue_script(
            'asg-public-script',
            ASG_PLUGIN_URL . 'assets/js/asg-public.js',
            array('jquery'),
            ASG_VERSION,
            true
        );

        wp_localize_script('asg-public-script', 'asgPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asg_add_note'),
            'i18n' => array(
                'addNoteSuccess' => 'یادداشت با موفقیت اضافه شد.',
                'addNoteError' => 'خطا در ثبت یادداشت',
                'confirmDelete' => 'آیا از حذف این یادداشت اطمینان دارید؟'
            )
        ));
    }

    /**
     * اضافه کردن فیلتر برای URL های حساب کاربری
     */
    public function init_url_filters() {
        add_filter('woocommerce_get_endpoint_url', array($this, 'modify_endpoint_url'), 10, 4);
    }

    /**
     * تغییر URL های endpoint برای سازگاری با Digits
     */
    public function modify_endpoint_url($url, $endpoint, $value, $permalink) {
        if (class_exists('Digits') && !is_user_logged_in()) {
            if (in_array($endpoint, array('guarantee-requests', 'view-guarantee'))) {
                return digits_get_login_url();
            }
        }
        return $url;
    }

    /**
     * دریافت برچسب وضعیت
     */
    public function get_status_label($status) {
        $statuses = array(
            'pending' => 'در انتظار بررسی',
            'processing' => 'در حال بررسی',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            'completed' => 'تکمیل شده'
        );

        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
}