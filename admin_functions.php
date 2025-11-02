<?php
/**
 * توابع مدیریتی افزونه WooGravity Manager
 */

if (!defined('ABSPATH')) exit;

// تابع نمایش صفحه مدیریت آزمون‌ها
function wgm_render_test_manager_page() {
    include WGM_PLUGIN_DIR . 'admin/test_manager.php';
}

// سایر توابع مدیریتی می‌توانند اینجا اضافه شوند
