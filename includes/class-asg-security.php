<?php
namespace ASG;

/**
 * کلاس امنیتی افزونه
 */
class Security {
    /**
     * نام nonce
     */
    const NONCE_NAME = 'asg_nonce';
    const NONCE_ACTION = 'asg_security';

    /**
     * سازنده کلاس
     */
    public function __construct() {
        add_filter('asg_validate_input', [$this, 'validate_input'], 10, 2);
    }

    /**
     * ایجاد nonce برای فرم‌ها
     */
    public static function create_nonce_field(): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
    }

    /**
     * بررسی nonce در درخواست‌ها
     */
    public static function verify_nonce(?string $nonce = null): bool {
        if (is_null($nonce)) {
            $nonce = $_REQUEST[self::NONCE_NAME] ?? '';
        }
        return wp_verify_nonce($nonce, self::NONCE_ACTION);
    }

    /**
     * اعتبارسنجی داده‌های ورودی
     */
    public function validate_input($input, string $type = 'text') {
        return match ($type) {
            'textarea' => $this->sanitize_textarea($input),
            'email' => $this->sanitize_email($input),
            'number' => $this->sanitize_number($input),
            'url' => $this->sanitize_url($input),
            'file' => $this->validate_file($input),
            default => $this->sanitize_text($input),
        };
    }

    /**
     * پاکسازی متن ساده
     */
    private function sanitize_text($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize_text'], $input);
        }
        return sanitize_text_field($input);
    }

    /**
     * پاکسازی متن چند خطی
     */
    private function sanitize_textarea(string $input): string {
        return sanitize_textarea_field($input);
    }

    /**
     * پاکسازی ایمیل
     */
    private function sanitize_email(string $input): string {
        $email = sanitize_email($input);
        return is_email($email) ? $email : '';
    }

    /**
     * پاکسازی اعداد
     */
    private function sanitize_number($input): int {
        return is_numeric($input) ? (int)$input : 0;
    }

    /**
     * پاکسازی URL
     */
    private function sanitize_url(string $input): string {
        return esc_url_raw($input);
    }

    /**
     * اعتبارسنجی فایل
     */
    private function validate_file(array $file): bool {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return false;
        }

        // بررسی پسوند فایل
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_type, $allowed_types)) {
            return false;
        }

        // بررسی حجم فایل (حداکثر 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return false;
        }

        // بررسی نوع واقعی فایل
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return in_array($mime_type, [
            'image/jpeg',
            'image/png',
            'application/pdf'
        ]);
    }

    /**
     * تولید توکن امن با استفاده از کش
     */
    public static function generate_token(int $length = 32): string {
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
    public static function encrypt_data(string $data, ?string $key = null): string {
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
    public static function decrypt_data(string $encrypted_data, ?string $key = null): string {
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
    public static function rate_limit(string $key, int $limit = 60, int $period = 3600): bool {
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