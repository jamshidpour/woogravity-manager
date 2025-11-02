<?php
/**
 * فایل مدیریت هوک‌ها و فیلترهای افزونه WooGravity Manager
 */

if (!defined('ABSPATH')) exit;

// ==================== فیلتر محتوای آزمون ====================
add_filter('the_content', function($content) {
    if (has_shortcode($content, 'woogravity_test_form')) {
        $only_logged_in = get_option('wgm_only_logged_in', 1);
        $only_purchased = get_option('wgm_only_purchased', 1);
        
        if ($only_logged_in && !is_user_logged_in()) {
            return '<div class="wgm-login-required">' . __('جهت مشاهده آزمون وارد حساب کاربری خود شوید.', 'woogravity-manager') . '</div>';
        }
        
        if ($only_purchased && is_user_logged_in()) {
            $slug = isset($_GET['test']) ? sanitize_text_field($_GET['test']) : '';
            if ($slug) {
                $user_id = get_current_user_id();
                $has_bought = false;
                
                if (function_exists('wc_customer_bought_product')) {
                    $product_id = wgm_get_product_id_by_slug($slug);
                    if ($product_id) {
                        $has_bought = wc_customer_bought_product('', $user_id, $product_id);
                    }
                }
                
                if (!$has_bought) {
                    return '<div class="wgm-login-required">' . __('برای مشاهده این آزمون باید محصول مربوطه را خریداری کنید.', 'woogravity-manager') . '</div>';
                }
            }
        }
    }
    return $content;
});

// ==================== هوک‌های Gravity Forms ====================
// پردازش فیلدهای خودکار انتخاب شده
add_action('gform_pre_submission', function($form) {
    foreach ($_POST as $key => $value) {
        if (strpos($key, '_auto_selected') !== false) {
            $field_id = str_replace('input_', '', str_replace('_auto_selected', '', $key));
            $_POST["input_{$field_id}"] = '0';
            wgm_log("Field {$field_id} was auto-selected and set to 0");
        }
    }
}, 9);

// پردازش ارسال اتوماتیک فرم
add_action('gform_pre_submission', function($form) {
    if (isset($_POST['wgm_auto_submit']) && $_POST['wgm_auto_submit'] === '1') {
        wgm_log("Auto-submit detected for form ID: " . $form['id']);
        
        add_filter('gform_field_validation', function($result, $value, $form, $field) {
            if (isset($_POST['wgm_auto_submit']) && $_POST['wgm_auto_submit'] === '1') {
                $result['is_valid'] = true;
                $result['message'] = '';
                return $result;
            }
            return $result;
        }, 10, 4);
    }
}, 5);

// جلوگیری از ریدایرکت اتوماتیک
add_filter('gform_confirmation', function($confirmation, $form, $entry, $ajax) {
    if (isset($_POST['wgm_auto_submit']) && $_POST['wgm_auto_submit'] === '1') {
        wgm_log("Auto-submit confirmation for form ID: " . $form['id']);
        return array(
            'redirect' => '',
            'message' => '<div id="wgm-auto-submit-success" style="display:none;">فرم با موفقیت ارسال شد</div>'
        );
    }
    return $confirmation;
}, 10, 4);

// ==================== هوک‌های ووکامرس ====================
// دکمه شروع آزمون در صفحه تشکر
add_action('woocommerce_thankyou', function($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    $tests = wgm_get_tests(true);
    $slugs = array_keys($tests);
    
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;
        
        $product_slug = $product->get_slug();
        if (in_array($product_slug, $slugs)) {
            $exam_url = home_url('/exam/?test=' . $product_slug);
            echo '<a href="' . esc_url($exam_url) . '" class="button wgm-exam-btn" style="display:inline-block;margin:20px 0;padding:12px 28px;background:#0073aa;color:#fff;border-radius:6px;font-size:18px;text-decoration:none;">شروع آزمون</a>';
        }
    }
}, 20);

// دکمه شروع آزمون در آیتم‌های سفارش
add_action('woocommerce_order_item_meta_end', function($item_id, $item, $order) {
    $product = $item->get_product();
    if (!$product) return;
    
    $tests = wgm_get_tests(true);
    $slugs = array_keys($tests);
    $product_slug = $product->get_slug();
    
    if (in_array($product_slug, $slugs)) {
        $exam_url = home_url('/exam/?test=' . $product_slug);
        echo '<br><a href="' . esc_url($exam_url) . '" class="button wgm-exam-btn" style="display:inline-block;margin:10px 0 0 0;padding:8px 20px;background:#0073aa;color:#fff;border-radius:6px;font-size:15px;text-decoration:none;">شروع آزمون</a>';
    }
}, 10, 3);

// پاک کردن کش محصولات هنگام به‌روزرسانی
add_action('save_post_product', function($post_id) {
    if (get_post_type($post_id) === 'product') {
        $product = get_post($post_id);
        if ($product && $product->post_name) {
            $cache_key = 'wgm_product_id_' . md5($product->post_name);
            wp_cache_delete($cache_key, 'wgm_plugin');
        }
    }
});