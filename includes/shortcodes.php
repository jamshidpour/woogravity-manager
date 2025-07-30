<?php
/**
 * مدیریت شورتکدهای افزونه WooGravity Manager
 */

if (!defined('ABSPATH')) exit;

// شورتکد نمایش فرم آزمون
add_shortcode('woogravity_test_form', function($atts) {
    
    // دریافت پارامترهای شورتکد
    $atts = shortcode_atts([
        'preview' => false // امکان نمایش پیش‌نمایش
    ], $atts);
    
    // اگر در حالت پیش‌نمایش باشیم
    if ($atts['preview']) {
        return '<div class="wgm-preview">' . __('پیش‌نمایش فرم آزمون', 'woogravity-manager') . '</div>';
    }

    $slug = isset($_GET['test']) ? sanitize_text_field($_GET['test']) : null;
    $tests = wgm_get_tests(true);

    // بررسی وجود آزمون
    if (!$slug || !isset($tests[$slug])) {
        return '<p class="wgm-error">' . __('آزمون مورد نظر یافت نشد.', 'woogravity-manager') . '</p>';
    }

    $test = $tests[$slug];
    
    // بررسی فعال بودن آزمون
    if (!$test['active']) {
        return '<p class="wgm-error">' . __('این آزمون در حال حاضر غیرفعال است.', 'woogravity-manager') . '</p>';
    }
    
    // بررسی وجود فرم
    if (!class_exists('GFAPI') || !GFAPI::form_id_exists($test['form_id'])) {
        return '<p class="wgm-error">' . __('فرم مربوطه در سیستم یافت نشد.', 'woogravity-manager') . '</p>';
    }
    
    // آماده‌سازی URL ریدایرکت
    $redirect_url = get_option('wgm_redirect_url', '');
    $redirect_query = get_option('wgm_redirect_query', '');

    if (empty($redirect_url)) {
        $redirect_url = function_exists('wc_get_account_endpoint_url') ? 
            wc_get_account_endpoint_url('dashboard') : 
            home_url('/my-account');
    }

    // پردازش پارامترهای ریدایرکت
    if (!empty($redirect_query)) {
        // جایگزینی form_id
        if (strpos($redirect_query, '{form_id}') !== false) {
            $redirect_query = str_replace('{form_id}', $test['form_id'], $redirect_query);
        }
        
        // جایگزینی اطلاعات کاربر
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            
            if (strpos($redirect_query, '{user:user_login}') !== false) {
                $redirect_query = str_replace(
                    '{user:user_login}', 
                    urlencode($current_user->user_login), 
                    $redirect_query
                );
            }
        } else {
            // حذف پارامترهای کاربری اگر کاربر لاگین نباشد
            $redirect_query = preg_replace('/&?[^=]*=\{user:[^\}]+\}/', '', $redirect_query);
        }
        
        // اضافه کردن query string به URL
        $separator = strpos($redirect_url, '?') === false ? '?' : '&';
        $redirect_url .= $separator . str_replace('&amp;', '&', $redirect_query);
    }
    
    ob_start(); ?>
    
    <div class="dynamic-test-box">
        <!-- هدر آزمون -->
        <h1><?php echo esc_html($test['title']); ?></h1>
        
        <?php if ($test['time_limit_enabled'] && !empty($test['duration'])): ?>
            <!-- تایمر آزمون -->
            <div class="wgm-timer-container">
                <div class="wgm-timer-display">
                    <span class="wgm-timer-label"><?php _e('زمان باقی‌مانده:', 'woogravity-manager'); ?></span>
                    <span class="wgm-timer" id="wgm-countdown" 
                          data-duration="<?php echo (int)$test['duration']; ?>"
                          data-redirect-url="<?php echo esc_attr($redirect_url); ?>">
                        <?php echo sprintf('%02d:%02d', $test['duration'], 0); ?>
                    </span>
                </div>
                <div class="wgm-timer-bar">
                    <div class="wgm-timer-progress" id="wgm-progress"></div>
                </div>
            </div>
        <?php elseif (!empty($test['duration'])): ?>
            <!-- نمایش مدت زمان بدون تایمر -->
            <p class="test-duration">
                <?php printf(__('مدت زمان آزمون: %d دقیقه', 'woogravity-manager'), $test['duration']); ?>
            </p>
        <?php endif; ?>
        
        <!-- فرم آزمون -->
        <div class="test-form">
            <?php 
            // نمایش فرم گرویتی فرم
            echo do_shortcode('[gravityform id="' . (int)$test['form_id'] . '" title="false" description="false" ajax="true"]'); 
            ?>
        </div>
    </div>

    <!-- مودال اتمام زمان -->
    <div id="wgm-timeout-modal" class="wgm-modal" style="display: none;" data-redirect-url="<?php echo esc_attr($redirect_url); ?>">
        <div class="wgm-modal-content">
            <div class="wgm-modal-header">
                <h3><?php _e('اتمام زمان آزمون', 'woogravity-manager'); ?></h3>
            </div>
            <div class="wgm-modal-body">
                <p><?php _e('زمان انجام این آزمون به پایان رسید. پاسخ شما به سوالات ثبت شد.', 'woogravity-manager'); ?></p>
                
                <div id="wgm-countdown-redirect">
                    <p>
                        <?php _e('شما تا', 'woogravity-manager'); ?>
                        <span id="wgm-redirect-timer">10</span>
                        <?php _e('ثانیه دیگر به صفحه نتایج هدایت می‌شوید.', 'woogravity-manager'); ?>
                    </p>
                    <div class="wgm-redirect-progress-bar" style="width: 100%; height: 6px; background: #e9ecef; border-radius: 3px; margin: 10px 0;">
                        <div id="wgm-redirect-progress" style="width: 0%; height: 100%; background: #0073aa; border-radius: 3px; transition: width 1s linear;"></div>
                    </div>
                </div>
            </div>
            <div class="wgm-modal-footer">
                <button id="wgm-redirect-now-btn" class="wgm-modal-btn">
                    <?php _e('انتقال فوری', 'woogravity-manager'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
});


/**
 * هوک برای پردازش پارامترهای URL و اضافه کردن عنوان آزمون
 * فقط بر اساس شناسه فرم، بدون نیاز به پارامتر اضافی
 */
add_filter('gform_field_value_test_title', function($value) {
    // دریافت شناسه فرم از URL
    $form_id = isset($_GET['form']) ? intval($_GET['form']) : 0;
    
    if ($form_id > 0) {
        // دریافت اطلاعات آزمون از دیتابیس
        global $wpdb;
        $table = WGM_TESTS_TABLE;
        
        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT title FROM {$table} WHERE form_id = %d AND is_active = 1",
            $form_id
        ));
        
        if ($test && !empty($test->title)) {
            return sanitize_text_field($test->title);
        }
        
        // اگر عنوان خاصی تعیین نشده، از عنوان اصلی فرم گرویتی استفاده کن
        if (class_exists('GFAPI')) {
            $gf_form = GFAPI::get_form($form_id);
            if ($gf_form && !empty($gf_form['title'])) {
                return sanitize_text_field($gf_form['title']);
            }
        }
    }
    
    return $value; // اگر هیچی پیدا نشد، مقدار اصلی رو برگردون
});
