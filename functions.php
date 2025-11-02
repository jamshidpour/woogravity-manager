<?php
/**
 * توابع اصلی و کمکی افزونه WooGravity Manager
 */

if (!defined('ABSPATH')) exit;

/**
 * گرفتن اطلاعات آزمون‌ها از دیتابیس
 */
function wgm_get_tests($only_active = false) {
    global $wpdb;
    $table = WGM_TESTS_TABLE;
    
    try {
        if ($only_active) {
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE is_active = %d", 1), ARRAY_A);
        } else {
            $results = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
        }
        
        if ($wpdb->last_error) {
            error_log('WGM Plugin DB Error: ' . $wpdb->last_error);
            return [];
        }
        
        $tests = [];
        foreach ($results as $row) {
            $tests[$row['form_slug']] = [
                'form_id' => (int) $row['form_id'],
                'slug' => sanitize_text_field($row['form_slug']),
                'title' => sanitize_text_field($row['title']),
                'duration' => (int) $row['duration_minutes'],
                'time_limit_enabled' => (bool) $row['time_limit_enabled'],
                'active' => (bool) $row['is_active'],
            ];
        }
        
        return $tests;
        
    } catch (Exception $e) {
        error_log('WGM Plugin Error in wgm_get_tests: ' . $e->getMessage());
        return [];
    }
}

/**
 * پیدا کردن product_id بر اساس slug با استفاده از کش
 */
function wgm_get_product_id_by_slug($slug) {
    if (empty($slug)) {
        return 0;
    }
    
    $cache_key = 'wgm_product_id_' . md5($slug);
    $product_id = wp_cache_get($cache_key, 'wgm_plugin');
    
    if (false === $product_id) {
        $product = get_page_by_path(sanitize_text_field($slug), OBJECT, 'product');
        $product_id = $product ? $product->ID : 0;
        wp_cache_set($cache_key, $product_id, 'wgm_plugin', HOUR_IN_SECONDS);
    }
    
    return (int) $product_id;
}

/**
 * تابع لاگ برای اشکال‌زدایی
 */
function wgm_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_message = sprintf('[WGM Plugin] [%s] %s', strtoupper($level), $message);
        error_log($log_message);
    }
}

/**
 * فعال‌سازی افزونه و ایجاد جدول مورد نیاز
 */
function wgm_activate_plugin() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS " . WGM_TESTS_TABLE . " (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        form_id BIGINT UNSIGNED NOT NULL,
        form_slug VARCHAR(191) NOT NULL UNIQUE,
        title VARCHAR(255) DEFAULT NULL,
        time_limit_enabled TINYINT(1) NOT NULL DEFAULT 0,
        duration_minutes INT UNSIGNED DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // بروزرسانی version در دیتابیس
    update_option('wgm_version', WGM_VERSION);
    
    wgm_log('Plugin activated successfully. Version: ' . WGM_VERSION);
}
