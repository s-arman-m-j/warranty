<?php
if (!defined('ABSPATH')) {
    exit;
}

class ASG_Admin {
    private $db;
    private $security;
    private $current_user;

    public function __construct() {
        $this->db = new ASG_DB();
        $this->security = new ASG_Security();
        $this->current_user = wp_get_current_user();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_asg_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_asg_search_users', array($this, 'ajax_search_users'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'گارانتی پس از فروش',
            'گارانتی',
            'manage_options',
            'asg-guarantee',
            array($this, 'render_admin_page'),
            'dashicons-shield',
            6
        );

        $submenu_pages = array(
            array(
                'parent_slug' => 'asg-guarantee',
                'page_title' => 'ثبت گارانتی جدید',
                'menu_title' => 'ثبت گارانتی جدید',
                'capability' => 'manage_options',
                'menu_slug' => 'asg-add-guarantee',
                'callback' => array($this, 'render_add_guarantee_page')
            ),
            array(
                'parent_slug' => 'asg-guarantee',
                'page_title' => 'ثبت گارانتی دسته‌ای',
                'menu_title' => 'ثبت گارانتی دسته‌ای',
                'capability' => 'manage_options',
                'menu_slug' => 'asg-bulk-guarantee',
                'callback' => array($this, 'render_bulk_guarantee_page')
            ),
            array(
                'parent_slug' => 'asg-guarantee',
                'page_title' => 'گزارشات',
                'menu_title' => 'گزارشات',
                'capability' => 'manage_options',
                'menu_slug' => 'asg-reports',
                'callback' => array($this, 'render_reports_page')
            ),
            array(
                'parent_slug' => 'asg-guarantee',
                'page_title' => 'تنظیمات',
                'menu_title' => 'تنظیمات',
                'capability' => 'manage_options',
                'menu_slug' => 'asg-settings',
                'callback' => array($this, 'render_settings_page')
            )
        );

        foreach ($submenu_pages as $page) {
            add_submenu_page(
                $page['parent_slug'],
                $page['page_title'],
                $page['menu_title'],
                $page['capability'],
                $page['menu_slug'],
                $page['callback']
            );
        }
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'asg-') === false) {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Select2
        wp_enqueue_style(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
            array(),
            '4.0.13'
        );
        wp_enqueue_script(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
            array('jquery'),
            '4.0.13',
            true
        );

        // Custom styles and scripts
        wp_enqueue_style(
            'asg-style',
            ASG_PLUGIN_URL . 'assets/css/asg-style.css',
            array(),
            ASG_VERSION
        );
        
        wp_enqueue_style(
            'asg-admin-style',
            ASG_PLUGIN_URL . 'assets/css/asg-admin.css',
            array('asg-style'),
            ASG_VERSION
        );

        wp_enqueue_script(
            'asg-admin-script',
            ASG_PLUGIN_URL . 'assets/js/asg-admin.js',
            array('jquery', 'select2'),
            ASG_VERSION,
            true
        );

        wp_localize_script('asg-admin-script', 'asgAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asg-admin-nonce'),
            'user' => array(
                'login' => $this->current_user->user_login,
                'id' => $this->current_user->ID
            ),
            'currentDateTime' => current_time('Y-m-d H:i:s'),
            'i18n' => array(
                'confirmDelete' => 'آیا از حذف این مورد اطمینان دارید؟',
                'selectProduct' => 'محصول را انتخاب کنید...',
                'selectUser' => 'کاربر را انتخاب کنید...',
                'uploadImage' => 'آپلود تصویر',
                'removeImage' => 'حذف تصویر'
            )
        ));
    }

    public function handle_admin_actions() {
        if (!isset($_REQUEST['action']) || !isset($_REQUEST['page']) || strpos($_REQUEST['page'], 'asg-') === false) {
            return;
        }

        $action = sanitize_text_field($_REQUEST['action']);
        
        switch ($action) {
            case 'delete_guarantee':
                $this->handle_delete_guarantee();
                break;
            case 'update_status':
                $this->handle_update_status();
                break;
            case 'export_report':
                $this->handle_export_report();
                break;
        }
    }

    private function handle_delete_guarantee() {
        if (!isset($_GET['id']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_guarantee')) {
            wp_die('نشست شما منقضی شده است.');
        }

        $guarantee_id = intval($_GET['id']);
        if ($this->db->delete_request($guarantee_id)) {
            $this->security->log_activity('delete_guarantee', array(
                'guarantee_id' => $guarantee_id,
                'user_id' => get_current_user_id()
            ));
            wp_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=asg-guarantee')));
            exit;
        }
    }

    private function handle_update_status() {
        if (!isset($_POST['guarantee_id']) || !wp_verify_nonce($_POST['_wpnonce'], 'update_status')) {
            wp_die('نشست شما منقضی شده است.');
        }

        $guarantee_id = intval($_POST['guarantee_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        if ($this->db->update_request($guarantee_id, array('status' => $new_status))) {
            $this->security->log_activity('update_status', array(
                'guarantee_id' => $guarantee_id,
                'new_status' => $new_status,
                'user_id' => get_current_user_id()
            ));
            wp_redirect(add_query_arg('message', 'updated', wp_get_referer()));
            exit;
        }