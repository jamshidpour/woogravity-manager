<?php
/*
Plugin Name: WooGravity Manager
Plugin URI: https://webshik.com/product/woogravity-manager/
Description: ووگرویتی پلی است میان دو افزونه محبوب ووکامرس و گرویتی فرم که قابلیت فروش فرم‌های گرویتی با ووکامرس را به سایت شما اضافه می‌کند به همراه کلی ویژگی‌های حرفه‌ای برای مدیریت فرم‌های گرویتی
Version: 1.3.1
Author: وب شیک
Author URI: https://webshik.com
Text Domain: woogravity-manager
*/

if (!defined('ABSPATH')) exit;

define('WGM_VERSION', '1.3.1');

define('WGM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WGM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WGM_TESTS_TABLE', $GLOBALS['wpdb']->prefix . 'wgm_forms');

// بارگذاری کلاس لایسنس
$license_file = WGM_PLUGIN_DIR . 'includes/class-license-client.php';

require_once $license_file;


// راه‌اندازی سیستم لایسنس
global $wgm_license;
$wgm_license = new Shik_License_Client(array(
    'server_url' => 'https://webshik.com',
    'plugin_slug' => 'woogravity-manager',
    'plugin_file' => __FILE__,
    'version' => WGM_VERSION,
    'plugin_name' => 'ووگرویتی'
));

// =================================================================
// تابع بررسی فعال بودن لایسنس
// =================================================================
function wgm_is_license_active() {
    $license_data = get_option('shik_license_' . md5('woogravity-manager'), false);
    return $license_data && isset($license_data['status']) && $license_data['status'] === 'active';
}

// =================================================================
// ثبت منوی اختصاصی افزونه
// =================================================================
add_action('admin_menu', function() {
    // منوی اصلی
    add_menu_page(
        'مدیریت آزمون‌ها',
        'مدیریت آزمون‌ها',
        'manage_options',
        'woogravity-manager',
        function() {
            if (!wgm_is_license_active()) {
                echo '<div class="wrap">';
                echo '<h1>مدیریت آزمون‌ها</h1>';
                echo '<div class="notice notice-error inline">';
                echo '<p><strong>لایسنس فعال نشده است!</strong></p>';
                echo '<p>برای استفاده از این بخش، ابتدا لایسنس خود را فعال کنید.</p>';
                echo '<a href="' . admin_url('admin.php?page=wgm-license') . '" class="button button-primary">فعال‌سازی لایسنس</a>';
                echo '</div>';
                echo '</div>';
                return;
            }
            include WGM_PLUGIN_DIR . 'admin/test_manager.php';
        },
        'dashicons-welcome-write-blog',
        25
    );

    // سایر زیرمنوهای افزونه
    if (wgm_is_license_active()) {
        add_submenu_page(
            'woogravity-manager',
            'تنظیمات آزمون‌ها',
            'تنظیمات',
            'manage_options',
            'wgm-settings',
            ['WGM_Settings', 'settings_page']
        );
    }
    
    // زیرمنوی لایسنس (همیشه در دسترس)
    add_submenu_page(
        'woogravity-manager',
        'فعال‌سازی لایسنس',
        ' لایسنس / آپدیت',
        'manage_options',
        'wgm-license',
        function() {
            global $wgm_license;
            if (isset($wgm_license) && method_exists($wgm_license, 'render_license_page')) {
                $wgm_license->render_license_page();
            } else {
                echo '<div class="wrap">';
                echo '<h1>خطا</h1>';
                echo '<p>سیستم لایسنس بارگذاری نشده است.</p>';
                echo '</div>';
            }
        }
    );
});

// =================================================================
// تست Heartbeat (قبل از بررسی لایسنس)
// =================================================================
add_action('admin_init', function() {
    if (!isset($_GET['wgm_test_heartbeat'])) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم ندارید.');
    }
    
    global $wgm_license;
    
    if (!isset($wgm_license) || !$wgm_license) {
        wp_die('❌ خطا: $wgm_license تعریف نشده است!');
    }
    
    if (!method_exists($wgm_license, 'test_heartbeat')) {
        wp_die('❌ خطا: متد test_heartbeat وجود ندارد!');
    }
    
    try {
        $result = $wgm_license->test_heartbeat();
        $license_data = get_option('shik_license_' . md5('woogravity-manager'), false);
        $has_license = $license_data && isset($license_data['status']) && $license_data['status'] === 'active';
        
        $html = '<div style="max-width: 900px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">';
        $html .= '<h1 style="color: #0073aa; margin-bottom: 20px;">🧪 نتیجه تست Heartbeat</h1>';
        
        if ($has_license) {
            $html .= '<div style="background: #ecf7ed; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #46b450;">';
            $html .= '<h3 style="margin: 0; color: #46b450;">✅ لایسنس فعال است</h3>';
            $html .= '<p style="margin: 10px 0 0 0; color: #666;">این سایت دارای لایسنس معتبر است.</p>';
            $html .= '</div>';
        } else {
            $html .= '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffb900;">';
            $html .= '<h3 style="margin: 0; color: #856404;">⚠ لایسنس فعال نیست</h3>';
            $html .= '<p style="margin: 10px 0 0 0; color: #666;">این سایت در لیست دامنه‌های غیرمجاز ثبت می‌شود.</p>';
            $html .= '</div>';
        }
        
        $html .= '<div style="background: #f0f6fc; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #0073aa;">';
        $html .= '<h3 style="margin-top: 0;">اطلاعات افزونه:</h3>';
        $html .= '<ul style="margin: 0; padding-right: 20px;">';
        $html .= '<li><strong>نام:</strong> WooGravity Manager</li>';
        $html .= '<li><strong>نسخه:</strong> ' . WGM_VERSION . '</li>';
        $html .= '<li><strong>دامنه:</strong> ' . $_SERVER['HTTP_HOST'] . '</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        if ($result['success']) {
            $html .= '<div style="background: #ecf7ed; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #46b450;">';
            $html .= '<h3 style="color: #46b450;">✅ Heartbeat با موفقیت ارسال شد!</h3>';
        } else {
            $html .= '<div style="background: #ffeaea; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3232;">';
            $html .= '<h3 style="color: #dc3232;">❌ خطا در ارسال Heartbeat</h3>';
        }
        $html .= '<details style="margin-top: 15px;"><summary style="cursor: pointer; color: #0073aa; font-weight: bold;">جزئیات</summary>';
        $html .= '<pre style="margin: 10px 0 0 0; white-space: pre-wrap; background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px; font-size: 12px;">' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
        $html .= '</details></div>';
        
        $html .= '<p style="margin-top: 30px;">';
        $html .= '<a href="' . admin_url() . '" class="button button-primary">بازگشت به داشبورد</a>';
        if (!$has_license) {
            $html .= ' <a href="' . admin_url('admin.php?page=wgm-license') . '" class="button button-secondary">فعال‌سازی لایسنس</a>';
        }
        $html .= '</p></div>';
        
        wp_die($html, 'تست Heartbeat - WooGravity Manager');
        
    } catch (Exception $e) {
        wp_die('❌ خطا: ' . esc_html($e->getMessage()));
    }
});

// =================================================================
// هشدار عدم فعال‌سازی لایسنس
// =================================================================
if (!wgm_is_license_active()) {
    add_action('admin_notices', function() {
        $current_screen = get_current_screen();
        // فقط در صفحات افزونه نمایش بده
        if ($current_screen && strpos($current_screen->id, 'woogravity-manager') !== false) {
            return; // در صفحات خود افزونه نمایش نده
        }
        
        $license_page = admin_url('admin.php?page=wgm-license');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('WooGravity Manager:', 'woogravity-manager'); ?></strong>
                <?php _e('لایسنس فعال نشده است. برای استفاده از افزونه لطفاً لایسنس خود را فعال کنید.', 'woogravity-manager'); ?>
                <a href="<?php echo esc_url($license_page); ?>" class="button button-primary" style="margin-right: 10px;">
                    <?php _e('فعال‌سازی لایسنس', 'woogravity-manager'); ?>
                </a>
            </p>
        </div>
        <?php
    });
}

// =================================================================
// بارگذاری فایل‌های اصلی 
// =================================================================
if (wgm_is_license_active()) {
    require_once WGM_PLUGIN_DIR . 'admin/settings.php';
    require_once WGM_PLUGIN_DIR . 'includes/functions.php';
    require_once WGM_PLUGIN_DIR . 'includes/hooks.php';
    require_once WGM_PLUGIN_DIR . 'includes/shortcodes.php';
    require_once WGM_PLUGIN_DIR . 'admin/admin_functions.php';
    require_once WGM_PLUGIN_DIR . 'includes/woocommerce.php';
    
    // فعال‌سازی افزونه
    register_activation_hook(__FILE__, 'wgm_activate_plugin');
    
    // بارگذاری CSS و JS
    add_action('wp_enqueue_scripts', function() {
        wp_enqueue_style('woogravity-manager-style', WGM_PLUGIN_URL . 'assets/css/style.css', [], WGM_VERSION);
        wp_enqueue_script('wgm-script', WGM_PLUGIN_URL . 'assets/js/script.js', ['jquery'], WGM_VERSION, true);
        
        wp_localize_script('wgm-script', 'wgm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wgm_ajax_nonce'),
            'strings' => [
                'confirm_exit' => __('آیا مطمئن هستید که می‌خواهید صفحه را ترک کنید؟', 'woogravity-manager'),
                'time_up' => __('زمان به پایان رسید!', 'woogravity-manager')
            ]
        ]);
    });
}