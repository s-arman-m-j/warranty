<?php
/**
 * کلاس امنیتی افزونه
 */
class ASG_Security {
    /**
     * نام nonce
     */
    const NONCE_NAME = 'asg_nonce';
    const NONCE_ACTION = 'asg_security';

    /**
     * سازنده کلاس
     */
    public function __construct() {
        // اضافه کردن فیلتر برای اعتبارسنجی داده‌ها
        add_filter('asg_validate_input', array($this, 'validate_input'), 10, 2);
    }

    /**
     * ایجاد nonce برای فرم‌ها
     */
    public static function create_nonce_field() {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
    }

    /**
     * بررسی nonce در درخواست‌ها
     */
    public static function verify_nonce($nonce = null) {
        if (is_null($nonce)) {
            $nonce = isset($_REQUEST[self::NONCE_NAME]) ? $_REQUEST[self::NONCE_NAME] : '';
        }
        return wp_verify_nonce($nonce, self::NONCE_ACTION);
    }

    /**
     * اعتبارسنجی داده‌های ورودی
     */
    public function validate_input($input, $type = 'text') {
        switch ($type) {
            case 'text':
                return $this->sanitize_text($input);
            
            case 'textarea':
                return $this->sanitize_textarea($input);
            
            case 'email':
                return $this->sanitize_email($input);
            
            case 'number':
                return $this->sanitize_number($input);
            
            case 'url':
                return $this->sanitize_url($input);
            
            case 'file':
                return $this->validate_file($input);
            
            default:
                return $this->sanitize_text($input);
        }
    }

    /**
     * پاکسازی متن ساده
     */
    private function sanitize_text($input) {
        if (is_array($input)) {
            return array_map(array($this, 'sanitize_text'), $input);
        }
        return sanitize_text_field($input);
    }

    /**
     * پاکسازی متن چند خطی
     */
    private function sanitize_textarea($input) {
        return sanitize_textarea_field($input);
    }

    /**
     * پاکسازی ایمیل
     */
    private function sanitize_email($input) {
        $email = sanitize_email($input);
        return is_email($email) ? $email : '';
    }

    /**
     * پاکسازی اعداد
     */
    private function sanitize_number($input) {
        if (is_numeric($input)) {
            return intval($input);
        }
        return 0;
    }

    /**
     * پاکسازی URL
     */
    private function sanitize_url($input) {
        return esc_url_raw($input);
    }

    /**
     * اعتبارسنجی فایل
     */
    private function validate_file($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return false;
        }

        // بررسی پسوند فایل
        $allowed_types = array('jpg', 'jpeg', 'png', 'pdf');
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_type, $allowed_types)) {
            return false;
        }

        // بررسی حجم فایل (حداکثر 2MB)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) {
            return false;
        }

        // بررسی نوع واقعی فایل
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = array(
            'image/jpeg',
            'image/png',
            'application/pdf'
        );

        if (!in_array($mime_type, $allowed_mimes)) {
            return false;
        }

        return true;
    }

   /**
 * تولید توکن امن با استفاده از کش
 */
public static function generate_token($length = 32) {
    $cache_key = 'asg_security_token_' . $length;
    $token = wp_cache_get($cache_key);
    
    if (false === $token) {
        $token = bin2hex(random_bytes($length));
        wp_cache_set($cache_key, $token, '', 3600); // کش برای یک ساعت
    }
    
    return $token;
}


    /**
     * رمزنگاری داده
     */
    public static function encrypt_data($data, $key = null) {
        if (is_null($key)) {
            $key = wp_salt('auth');
        }
        
        $method = "AES-256-CBC";
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * رمزگشایی داده
     */
    public static function decrypt_data($encrypted_data, $key = null) {
        if (is_null($key)) {
            $key = wp_salt('auth');
        }

        $data = base64_decode($encrypted_data);
        $method = "AES-256-CBC";
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }

    /**
 * محدودسازی درخواست‌ها با استفاده از کش object
 */
public static function rate_limit($key, $limit = 60, $period = 3600) {
    $cache_key = 'asg_rate_' . $key;
    $current = wp_cache_get($cache_key);
    
    if (false === $current) {
        wp_cache_add($cache_key, 1, '', $period);
        return true;
    }
    
    if ($current >= $limit) {
        return false;
    }
    
    wp_cache_incr($cache_key);
    return true;
}
}

// نمونه استفاده در فرم‌ها
/*
<form method="post" action="">
    <?php ASG_Security::create_nonce_field(); ?>
    <!-- فیلدهای فرم -->
</form>
*/

// نمونه استفاده در AJAX
/*
add_action('wp_ajax_asg_action', 'handle_ajax_request');
function handle_ajax_request() {
    // بررسی nonce
    if (!ASG_Security::verify_nonce()) {
        wp_send_json_error('درخواست نامعتبر است.');
    }

    // اعتبارسنجی داده‌ها
    $security = new ASG_Security();
    $name = $security->validate_input($_POST['name']);
    $email = $security->validate_input($_POST['email'], 'email');
    
    // پردازش درخواست
    // ...
}
*/

// نمونه استفاده از رمزنگاری
/*
$sensitive_data = 'داده حساس';
$encrypted = ASG_Security::encrypt_data($sensitive_data);
$decrypted = ASG_Security::decrypt_data($encrypted);
*/