<?php
/**
 * تنظیمات افزونه WooGravity Manager
 */

if (!defined('ABSPATH')) exit;

class WGM_Settings {
    public static function settings_page() {
        if (isset($_POST['action']) && $_POST['action'] === 'wgm_save_settings') {
            if (!isset($_POST['wgm_settings_nonce']) || !wp_verify_nonce($_POST['wgm_settings_nonce'], 'wgm_save_settings')) {
                wp_die(__('خطای امنیتی. لطفاً دوباره تلاش کنید.', 'woogravity-manager'));
            }
            
            // ذخیره تنظیمات
            update_option('wgm_only_logged_in', isset($_POST['wgm_only_logged_in']) ? 1 : 0);
            update_option('wgm_only_purchased', isset($_POST['wgm_only_purchased']) ? 1 : 0);
            update_option('wgm_redirect_url', sanitize_url($_POST['wgm_redirect_url'] ?? ''));
            update_option('wgm_redirect_query', sanitize_text_field($_POST['wgm_redirect_query'] ?? ''));
            
            // نمایش پیام موفقیت
            add_settings_error(
                'wgm_settings', 
                'wgm_settings_saved', 
                __('تنظیمات با موفقیت ذخیره شد.', 'woogravity-manager'), 
                'updated'
            );
        }
        
        // دریافت مقادیر ذخیره شده
        $settings = [
            'only_logged_in' => get_option('wgm_only_logged_in', 1),
            'only_purchased' => get_option('wgm_only_purchased', 1),
            'redirect_url' => get_option('wgm_redirect_url', ''),
            'redirect_query' => get_option('wgm_redirect_query', '')
        ];
        ?>
        <div class="wrap">
            <h1><?php _e('تنظیمات آزمون‌ها', 'woogravity-manager'); ?></h1>
            <?php settings_errors('wgm_settings'); ?>
            <form method="post" action="" novalidate="novalidate">
                <input type="hidden" name="action" value="wgm_save_settings">
                <?php wp_nonce_field('wgm_save_settings', 'wgm_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('نمایش آزمون تنها پس از ورود به حساب کاربری', 'woogravity-manager'); ?></th>
                        <td>
                            <input type="checkbox" name="wgm_only_logged_in" id="wgm_only_logged_in" 
                                   value="1" <?php checked($settings['only_logged_in'], 1); ?> />
                            <label for="wgm_only_logged_in">
                                <?php _e('در صورت فعال بودن، فقط کاربران وارد شده می‌توانند آزمون را مشاهده کنند.', 'woogravity-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('نمایش آزمون تنها در صورت خرید محصول', 'woogravity-manager'); ?></th>
                        <td>
                            <input type="checkbox" name="wgm_only_purchased" id="wgm_only_purchased" 
                                   value="1" <?php checked($settings['only_purchased'], 1); ?> />
                            <label for="wgm_only_purchased">
                                <?php _e('در صورت فعال بودن، فقط کاربرانی که محصول آزمون را خریده‌اند می‌توانند آزمون را مشاهده کنند.', 'woogravity-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('هدایت به صفحه پس از اتمام آزمون', 'woogravity-manager'); ?></th>
                        <td>
                            <input type="url" name="wgm_redirect_url" id="wgm_redirect_url" 
                                value="<?php echo esc_attr(!empty($settings['redirect_url']) ? $settings['redirect_url'] : '/client-info/'); ?>" 
                                class="regular-text" 
                                placeholder="/client-info/" />
                            <p class="description">
                                <?php _e('لینک صفحه‌ای که کاربر پس از اتمام آزمون به آن هدایت شود. اگر خالی باشد، به حساب کاربری هدایت می‌شود.', 'woogravity-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('پارامترهای دلخواه برای هدایت', 'woogravity-manager'); ?></th>
                        <td>
                            <input type="text" name="wgm_redirect_query" id="wgm_redirect_query" 
                                value="<?php echo esc_attr(!empty($settings['redirect_query']) ? $settings['redirect_query'] : 'form={form_id}'); ?>" 
                                class="regular-text" 
                                placeholder="form={form_id}" />
                            <p class="description">
                                <?php _e('پارامترهای اضافی که باید به URL بازگشت اضافه شوند (به فرمت Query String).', 'woogravity-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('ذخیره تنظیمات', 'woogravity-manager'), 'primary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
}
