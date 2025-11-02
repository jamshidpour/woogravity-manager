<?php
/**
 * توابع یکپارچه‌سازی با ووکامرس - WooGravity Manager
 */

if (!defined('ABSPATH')) exit;

/**
 * تابع بررسی خرید محصول
 */
function wgm_has_user_purchased_test($user_id, $slug) {
    if (!function_exists('wc_customer_bought_product')) {
        return false;
    }
    
    $product_id = wgm_get_product_id_by_slug($slug);
    return $product_id ? wc_customer_bought_product('', $user_id, $product_id) : false;
}

/**
 * دریافت لیست آزمون‌های خریداری شده توسط کاربر
 */
function wgm_get_user_purchased_tests($user_id) {
    if (!function_exists('wc_get_orders')) {
        return [];
    }
    
    $purchased_tests = [];
    $tests = wgm_get_tests(true);
    
    // دریافت سفارشات کاربر
    $orders = wc_get_orders([
        'customer' => $user_id,
        'status' => ['completed', 'processing'],
        'limit' => -1
    ]);
    
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $product_slug = $product->get_slug();
            if (isset($tests[$product_slug])) {
                $purchased_tests[$product_slug] = $tests[$product_slug];
            }
        }
    }
    
    return $purchased_tests;
}

/**
 * اضافه کردن متا باکس برای محصولات آزمون
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'wgm-test-info',
        __('اطلاعات آزمون', 'woogravity-manager'),
        'wgm_test_info_meta_box',
        'product',
        'side',
        'default'
    );
});

/**
 * نمایش متا باکس اطلاعات آزمون
 */
function wgm_test_info_meta_box($post) {
    $product = wc_get_product($post->ID);
    if (!$product) return;
    
    $product_slug = $product->get_slug();
    $tests = wgm_get_tests(true);
    
    if (isset($tests[$product_slug])) {
        $test = $tests[$product_slug];
        echo '<div class="wgm-test-info">';
        echo '<p><strong>' . __('این محصول به آزمون زیر متصل است:', 'woogravity-manager') . '</strong></p>';
        echo '<p><strong>' . __('عنوان:', 'woogravity-manager') . '</strong> ' . esc_html($test['title']) . '</p>';
        echo '<p><strong>' . __('Slug:', 'woogravity-manager') . '</strong> ' . esc_html($test['slug']) . '</p>';
        
        if ($test['time_limit_enabled']) {
            echo '<p><strong>' . __('مدت زمان:', 'woogravity-manager') . '</strong> ' . $test['duration'] . ' ' . __('دقیقه', 'woogravity-manager') . '</p>';
        }
        
        $exam_url = home_url('/exam/?test=' . $test['slug']);
        echo '<p><strong>' . __('لینک آزمون:', 'woogravity-manager') . '</strong></p>';
        echo '<p><code>' . esc_url($exam_url) . '</code></p>';
        echo '</div>';
    } else {
        echo '<p>' . __('این محصول به هیچ آزمونی متصل نیست.', 'woogravity-manager') . '</p>';
        echo '<p><a href="' . admin_url('admin.php?page=woogravity-manager') . '">' . __('مدیریت آزمون‌ها', 'woogravity-manager') . '</a></p>';
    }
}

/**
 * اضافه کردن ستون آزمون به لیست محصولات
 */
add_filter('manage_edit-product_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'name') {
            $new_columns['wgm_test'] = __('آزمون', 'woogravity-manager');
        }
    }
    return $new_columns;
});

/**
 * نمایش محتوای ستون آزمون
 */
add_action('manage_product_posts_custom_column', function($column, $post_id) {
    if ($column === 'wgm_test') {
        $product = wc_get_product($post_id);
        if (!$product) return;
        
        $product_slug = $product->get_slug();
        $tests = wgm_get_tests(true);
        
        if (isset($tests[$product_slug])) {
            $test = $tests[$product_slug];
            echo '<span style="color: #0073aa; font-weight: bold;">✓ ' . esc_html($test['title']) . '</span>';
            if ($test['time_limit_enabled']) {
                echo '<br><small>' . $test['duration'] . ' ' . __('دقیقه', 'woogravity-manager') . '</small>';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}, 10, 2);

/**
 * اضافه کردن تب آزمون‌ها به حساب کاربری
 */
add_filter('woocommerce_account_menu_items', function($items) {
    $new_items = [];
    foreach ($items as $key => $item) {
        $new_items[$key] = $item;
        if ($key === 'orders') {
            $new_items['wgm-tests'] = __('آزمون‌های من', 'woogravity-manager');
        }
    }
    return $new_items;
});

/**
 * تعریف endpoint برای تب آزمون‌ها
 */
add_action('init', function() {
    add_rewrite_endpoint('wgm-tests', EP_ROOT | EP_PAGES);
});

/**
 * محتوای تب آزمون‌ها
 */
add_action('woocommerce_account_wgm-tests_endpoint', function() {
    $user_id = get_current_user_id();
    $purchased_tests = wgm_get_user_purchased_tests($user_id);
    
    echo '<div class="wgm-user-tests">';
    echo '<h3>' . __('آزمون‌های خریداری شده', 'woogravity-manager') . '</h3>';
    
    if (empty($purchased_tests)) {
        echo '<p>' . __('شما هنوز هیچ آزمونی خریداری نکرده‌اید.', 'woogravity-manager') . '</p>';
    } else {
        echo '<div class="wgm-tests-grid">';
        foreach ($purchased_tests as $test) {
            $exam_url = home_url('/exam/?test=' . $test['slug']);
            echo '<div class="wgm-test-card">';
            echo '<h4>' . esc_html($test['title']) . '</h4>';
            
            if ($test['time_limit_enabled']) {
                echo '<p><strong>' . __('مدت زمان:', 'woogravity-manager') . '</strong> ' . $test['duration'] . ' ' . __('دقیقه', 'woogravity-manager') . '</p>';
            }
            
            echo '<a href="' . esc_url($exam_url) . '" class="wgm-start-test">' . __('شروع آزمون', 'woogravity-manager') . '</a>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    
    // اضافه کردن استایل ساده
    echo '<style>
        .wgm-tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .wgm-test-card {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #f9f9f9;
            text-align: center;
        }
        .wgm-start-test {
            display: block;
            padding: 12px 20px;
            background: var(--wd-primary-color);
            color: #fff;
            border-radius: 8px;
            font-size: 14px;
        }
        .wgm-start-test:hover {
            color: #fff;
            box-shadow: inset 0 0 0 100vmax rgba(0, 0, 0, 0.2);
        }
    </style>';
});

/**
 * فلاش کردن rewrite rules هنگام فعال‌سازی
 */
register_activation_hook(WGM_PLUGIN_DIR . 'woogravity-manager.php', function() {
    add_rewrite_endpoint('wgm-tests', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
});

/**
 * پاک کردن rewrite rules هنگام غیرفعال‌سازی
 */
register_deactivation_hook(WGM_PLUGIN_DIR . 'woogravity-manager.php', function() {
    flush_rewrite_rules();
});
