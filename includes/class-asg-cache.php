<?php
namespace ASG;

if (!defined('ABSPATH')) {
    exit;
}

class Cache {
    private $cache_group = 'asg_cache';
    private $cache_time = 3600; // یک ساعت
    private $user_cache_group;
    
    // برای نگهداری آخرین زمان بروزرسانی کش
    private $cache_version_key = 'asg_cache_version';
    
    // حداکثر تعداد نتایج در کش
    private const MAX_CACHE_ITEMS = 1000;
    
    public function __construct() {
        $this->user_cache_group = $this->cache_group . '_' . get_current_user_id();
        
        // استفاده از Object Cache اگر موجود باشد
        if (!wp_using_ext_object_cache()) {
            $this->setup_fallback_cache();
        }
        
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // هوک‌های پاکسازی کش
        add_action('asg_after_guarantee_update', [$this, 'clear_guarantee_cache']);
        add_action('asg_after_guarantee_delete', [$this, 'clear_guarantee_cache']);
        add_action('asg_after_note_add', [$this, 'clear_notes_cache']);
        
        // پاکسازی خودکار کش‌های قدیمی
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_caches']);
        
        // بروزرسانی نسخه کش در هنگام تغییرات
        add_action('save_post', [$this, 'bump_cache_version']);
        add_action('deleted_post', [$this, 'bump_cache_version']);
    }

    private function setup_fallback_cache(): void {
        // استفاده از دیتابیس برای کش اگر Object Cache موجود نباشد
        global $wpdb;
        
        if (!isset($wpdb->asg_cache)) {
            $wpdb->asg_cache = $wpdb->prefix . 'asg_cache';
            
            // ایجاد جدول کش اگر وجود نداشته باشد
            $this->maybe_create_cache_table();
        }
    }

    private function maybe_create_cache_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'asg_cache';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                cache_key varchar(255) NOT NULL,
                cache_group varchar(255) NOT NULL,
                cache_value longtext NOT NULL,
                cache_expires bigint(20) NOT NULL,
                PRIMARY KEY  (cache_key, cache_group),
                KEY cache_expires (cache_expires)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function get($key, $user_specific = false) {
        $group = $user_specific ? $this->user_cache_group : $this->cache_group;
        $version = get_option($this->cache_version_key, '1.0');
        $cache_key = $this->build_cache_key($key, $version);
        
        $data = wp_cache_get($cache_key, $group);
        
        if ($data === false && !wp_using_ext_object_cache()) {
            $data = $this->get_from_db_cache($cache_key, $group);
        }
        
        return $data;
    }

    public function set($key, $value, $user_specific = false): bool {
        if ($this->get_cache_size() >= self::MAX_CACHE_ITEMS) {
            $this->cleanup_old_caches();
        }
        
        $group = $user_specific ? $this->user_cache_group : $this->cache_group;
        $version = get_option($this->cache_version_key, '1.0');
        $cache_key = $this->build_cache_key($key, $version);
        
        $result = wp_cache_set($cache_key, $value, $group, $this->cache_time);
        
        if (!wp_using_ext_object_cache()) {
            $result = $this->set_db_cache($cache_key, $value, $group);
        }
        
        return $result;
    }

    private function build_cache_key(string $key, string $version): string {
        return md5($key . $version);
    }

    private function get_cache_size(): int {
        global $wpdb;
        
        if (wp_using_ext_object_cache()) {
            return wp_cache_get('cache_size', $this->cache_group) ?: 0;
        }
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->asg_cache}");
    }

    public function cleanup_old_caches(): void {
        global $wpdb;
        
        if (!wp_using_ext_object_cache()) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->asg_cache} WHERE cache_expires < %d",
                time()
            ));
        }
        
        wp_cache_set('cache_size', 0, $this->cache_group);
    }

    public function bump_cache_version(): void {
        $version = get_option($this->cache_version_key, '1.0');
        update_option($this->cache_version_key, microtime(true));
    }

    private function get_from_db_cache(string $key, string $group) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_value FROM {$wpdb->asg_cache} 
             WHERE cache_key = %s 
             AND cache_group = %s 
             AND cache_expires > %d",
            $key,
            $group,
            time()
        ));
        
        return $row ? maybe_unserialize($row->cache_value) : false;
    }

    private function set_db_cache(string $key, $value, string $group): bool {
        global $wpdb;
        
        $expires = time() + $this->cache_time;
        
        return (bool) $wpdb->replace(
            $wpdb->asg_cache,
            [
                'cache_key' => $key,
                'cache_group' => $group,
                'cache_value' => maybe_serialize($value),
                'cache_expires' => $expires
            ],
            ['%s', '%s', '%s', '%d']
        );
    }
}