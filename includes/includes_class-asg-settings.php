<?php
if (!defined('ABSPATH')) {
    exit;
}

class ASG_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_options_page(
            'ASG Settings',
            'ASG Settings',
            'manage_options',
            'asg-settings',
            array($this, 'settings_page_html')
        );
    }

    public function register_settings() {
        register_setting('asg_settings_group', 'asg_woocommerce_api_url');
        register_setting('asg_settings_group', 'asg_woocommerce_api_key');
        register_setting('asg_settings_group', 'asg_woocommerce_api_secret');

        add_settings_section(
            'asg_woocommerce_section',
            'اتصال به ووکامرس',
            null,
            'asg-settings'
        );

        add_settings_field(
            'asg_woocommerce_api_url',
            'API URL',
            array($this, 'woocommerce_api_url_callback'),
            'asg-settings',
            'asg_woocommerce_section'
        );

        add_settings_field(
            'asg_woocommerce_api_key',
            'API Key',
            array($this, 'woocommerce_api_key_callback'),
            'asg-settings',
            'asg_woocommerce_section'
        );

        add_settings_field(
            'asg_woocommerce_api_secret',
            'API Secret',
            array($this, 'woocommerce_api_secret_callback'),
            'asg-settings',
            'asg_woocommerce_section'
        );
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>ASG Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('asg_settings_group');
                do_settings_sections('asg-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function woocommerce_api_url_callback() {
        $value = get_option('asg_woocommerce_api_url', '');
        echo '<input type="text" name="asg_woocommerce_api_url" value="' . esc_attr($value) . '" />';
    }

    public function woocommerce_api_key_callback() {
        $value = get_option('asg_woocommerce_api_key', '');
        echo '<input type="text" name="asg_woocommerce_api_key" value="' . esc_attr($value) . '" />';
    }

    public function woocommerce_api_secret_callback() {
        $value = get_option('asg_woocommerce_api_secret', '');
        echo '<input type="text" name="asg_woocommerce_api_secret" value="' . esc_attr($value) . '" />';
    }
}

new ASG_Settings();
?>
