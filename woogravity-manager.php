<?php
/*
Plugin Name: WooGravity Manager
Description: The Ultimate Bridge Between WooCommerce & Gravity Forms - Sell and manage timed psychological tests with advanced features
Version: 2.0
Author: وب شیک
Text Domain: woogravity-manager
*/

if (!defined('ABSPATH')) exit;

// تعریف ثابت‌ها
define('WGM_TESTS_TABLE', $GLOBALS['wpdb']->prefix . 'wgm_forms');
define('WGM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WGM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WGM_VERSION', '2.0');

// بارگذاری فایل‌های مورد نیاز
require_once WGM_PLUGIN_DIR . 'admin/settings.php';
require_once WGM_PLUGIN_DIR . 'includes/functions.php';
require_once WGM_PLUGIN_DIR . 'includes/hooks.php';
require_once WGM_PLUGIN_DIR . 'includes/shortcodes.php';
require_once WGM_PLUGIN_DIR . 'admin/admin-functions.php';
require_once WGM_PLUGIN_DIR . 'includes/woocommerce.php';

// فعال‌سازی افزونه
register_activation_hook(__FILE__, 'wgm_activate_plugin');

// بارگذاری CSS و JS
add_action('wp_enqueue_scripts', function() {
    // بارگذاری CSS
    wp_enqueue_style(
        'woogravity-manager-style', 
        WGM_PLUGIN_URL . 'assets/css/style.css', 
        [], 
        filemtime(WGM_PLUGIN_DIR . 'assets/css/style.css')
    );
    
    // بارگذاری JavaScript
    wp_enqueue_script(
        'wgm-script', 
        WGM_PLUGIN_URL . 'assets/js/script.js', 
        ['jquery'], 
        filemtime(WGM_PLUGIN_DIR . 'assets/js/script.js'), 
        true
    );
    
    // ارسال متغیرهای لازم به JavaScript
    wp_localize_script('wgm-script', 'wgm_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wgm_ajax_nonce'),
        'strings' => [
            'confirm_exit' => __('آیا مطمئن هستید که می‌خواهید صفحه را ترک کنید؟ پیشرفت آزمون شما ذخیره نخواهد شد.', 'woogravity-manager'),
            'time_up' => __('زمان به پایان رسید!', 'woogravity-manager')
        ]
    ]);
});
