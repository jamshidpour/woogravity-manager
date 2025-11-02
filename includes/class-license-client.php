<?php

if (!defined('ABSPATH')) {
    exit;
}

class Shik_License_Client {
    
    private $server_url;
    private $plugin_slug;
    private $plugin_file;
    private $version;
    private $plugin_name;
    private $option_key;
    
    /**
     * Constructor
     */
    public function __construct($args = array()) {
        $defaults = array(
            'server_url' => '',
            'plugin_slug' => '',
            'plugin_file' => '',
            'version' => '1.0.0',
            'plugin_name' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $this->server_url = rtrim($args['server_url'], '/');
        $this->plugin_slug = $args['plugin_slug'];
        $this->plugin_file = $args['plugin_file'];
        $this->version = $args['version'];
        $this->plugin_name = $args['plugin_name'] ?: $args['plugin_slug'];
        $this->option_key = 'shik_license_' . md5($this->plugin_slug);
        
        // هوک‌ها
        add_action('admin_init', array($this, 'handle_license_actions'));
        add_action('admin_notices', array($this, 'license_notices'));
        
        // آپدیت خودکار
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        
        // بررسی دوره‌ای لایسنس (هر 24 ساعت)
        add_action('init', array($this, 'schedule_license_check'));
        add_action('shik_license_check_' . $this->plugin_slug, array($this, 'verify_license_periodic'));
        
        // ارسال Heartbeat (حتی بدون لایسنس)
        add_action('shik_heartbeat_' . $this->plugin_slug, array($this, 'send_heartbeat'));
        add_action('init', array($this, 'schedule_heartbeat'));
    }
    
    
    /**
     * صفحه فعال‌سازی لایسنس
     */
    public function render_license_page() {
        $license_data = $this->get_license_data();
        $is_active = $license_data && isset($license_data['status']) && $license_data['status'] === 'active';
        
        ?>
        <div class="wrap">
            <h1><?php printf(__('فعال‌سازی لایسنس افزونه %s', 'shik-license-client'), $this->plugin_name); ?></h1>
            
            <?php if ($is_active): ?>
                <div class="notice notice-success">
                    <p>
                        <strong><?php _e('✓ لایسنس فعال است', 'shik-license-client'); ?></strong><br>
                        <?php _e('کلید لایسنس:', 'shik-license-client'); ?> 
                        <code><?php echo esc_html($license_data['license_key']); ?></code>
                    </p>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field('shik_deactivate_license', 'shik_license_nonce'); ?>
                    <input type="hidden" name="action" value="deactivate">
                    <p>
                        <input type="submit" class="button button-secondary" value="<?php _e('غیرفعال‌سازی لایسنس', 'shik-license-client'); ?>">
                    </p>
                </form>
                
                <hr>
                
                <h2><?php _e('بررسی آپدیت', 'shik-license-client'); ?></h2>
                <p>
                    <?php _e('نسخه فعلی:', 'shik-license-client'); ?> <strong><?php echo esc_html($this->version); ?></strong>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('shik_check_update', 'shik_update_nonce'); ?>
                    <input type="hidden" name="action" value="check_update">
                    <p>
                        <input type="submit" class="button" value="<?php _e('بررسی آپدیت جدید', 'shik-license-client'); ?>">
                    </p>
                </form>
                
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('⚠ لایسنس فعال نشده است. برای استفاده از این افزونه باید لایسنس خود را فعال کنید.', 'shik-license-client'); ?></p>
                </div>
                
                <form method="post" action="" style="max-width: 600px;">
                    <?php wp_nonce_field('shik_activate_license', 'shik_license_nonce'); ?>
                    <input type="hidden" name="action" value="activate">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="license_key"><?php _e('کلید لایسنس', 'shik-license-client'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="license_key" 
                                    name="license_key" 
                                    class="regular-text" 
                                    placeholder="SHIK-XXXX-XXXX-XXXX-XXXX"
                                    required>
                                <p class="description">
                                    <?php _e('کلید لایسنسی که هنگام خرید دریافت کرده‌اید را وارد کنید.', 'shik-license-client'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="domain"><?php _e('دامنه سایت', 'shik-license-client'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="domain" 
                                    name="domain" 
                                    class="regular-text" 
                                    value="<?php echo esc_attr($this->get_current_domain()); ?>"
                                    readonly>
                                <p class="description">
                                    <?php _e('دامنه فعلی سایت شما (خودکار تشخیص داده شده)', 'shik-license-client'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php _e('فعال‌سازی لایسنس', 'shik-license-client'); ?>">
                    </p>
                </form>
            <?php endif; ?>
            
            <hr>
            
            <h2><?php _e('راهنما', 'shik-license-client'); ?></h2>
            <ul>
                <li><?php _e('کلید لایسنس را از ایمیل خرید یا حساب کاربری خود کپی کنید', 'shik-license-client'); ?></li>
                <li><?php _e('هر لایسنس فقط برای یک دامنه قابل استفاده است', 'shik-license-client'); ?></li>
                <li><?php _e('در صورت تغییر دامنه، ابتدا لایسنس را غیرفعال کرده و سپس در دامنه جدید فعال کنید', 'shik-license-client'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * مدیریت اکشن‌های لایسنس
     */
    public function handle_license_actions() {
        if (!isset($_POST['action'])) {
            return;
        }
        
        if ($_POST['action'] === 'activate' && isset($_POST['shik_license_nonce'])) {
            if (!wp_verify_nonce($_POST['shik_license_nonce'], 'shik_activate_license')) {
                return;
            }
            
            $license_key = sanitize_text_field($_POST['license_key']);
            $domain = sanitize_text_field($_POST['domain']);
            
            $result = $this->activate_license($license_key, $domain);
            
            if ($result['success']) {
                add_settings_error('shik_license', 'license_activated', $result['message'], 'success');
            } else {
                add_settings_error('shik_license', 'license_error', $result['message'], 'error');
            }
        }
        
        if ($_POST['action'] === 'deactivate' && isset($_POST['shik_license_nonce'])) {
            if (!wp_verify_nonce($_POST['shik_license_nonce'], 'shik_deactivate_license')) {
                return;
            }
            
            $this->deactivate_license();
            add_settings_error('shik_license', 'license_deactivated', __('لایسنس با موفقیت غیرفعال شد', 'shik-license-client'), 'success');
        }
        
        if ($_POST['action'] === 'check_update' && isset($_POST['shik_update_nonce'])) {
            if (!wp_verify_nonce($_POST['shik_update_nonce'], 'shik_check_update')) {
                return;
            }
            
            delete_site_transient('update_plugins');
            add_settings_error('shik_license', 'update_checked', __('بررسی آپدیت انجام شد', 'shik-license-client'), 'success');
        }
    }
    
    /**
     * نمایش نوتیفیکیشن‌ها
     */
    public function license_notices() {
        settings_errors('shik_license');
        
        // هشدار عدم فعال‌سازی
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'plugins') !== false) {
            $license_data = $this->get_license_data();
            if (!$license_data || $license_data['status'] !== 'active') {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php echo esc_html($this->plugin_name); ?>:</strong>
                        <?php _e('لایسنس فعال نشده است.', 'shik-license-client'); ?>
                        <a href="<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '-license'); ?>">
                            <?php _e('فعال‌سازی', 'shik-license-client'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * فعال‌سازی لایسنس
     */
    private function activate_license($license_key, $domain) {
        $response = wp_remote_post($this->server_url . '/wp-json/shik-license/v1/activate', array(
            'body' => array(
                'license_key' => $license_key,
                'domain' => $domain
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('خطا در اتصال به سرور: ', 'shik-license-client') . $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body && $body['success']) {
            update_option($this->option_key, array(
                'license_key' => $license_key,
                'domain' => $domain,
                'status' => 'active',
                'activated_at' => time()
            ));
            
            // 🆕 ارسال فوری heartbeat برای حذف از لیست غیرمجازها
            $this->send_heartbeat();
            
            return array(
                'success' => true,
                'message' => __('✓ لایسنس با موفقیت فعال شد', 'shik-license-client')
            );
        }
        
        return array(
            'success' => false,
            'message' => isset($body['message']) ? $body['message'] : __('خطای نامشخص', 'shik-license-client')
        );
    }
    
    /**
     * غیرفعال‌سازی لایسنس
     */
    private function deactivate_license() {
        $license_data = $this->get_license_data();
        
        if ($license_data) {
            wp_remote_post($this->server_url . '/wp-json/shik-license/v1/deactivate', array(
                'body' => array(
                    'license_key' => $license_data['license_key'],
                    'domain' => $license_data['domain']
                ),
                'timeout' => 15
            ));
        }
        
        delete_option($this->option_key);
        
        // 🆕 ارسال فوری heartbeat برای اضافه شدن به لیست غیرمجازها
        $this->send_heartbeat();
    }
    
    /**
     * بررسی دوره‌ای لایسنس
     */
    public function schedule_license_check() {
        if (!wp_next_scheduled('shik_license_check_' . $this->plugin_slug)) {
            wp_schedule_event(time(), 'daily', 'shik_license_check_' . $this->plugin_slug);
        }
    }
    
    /**
     * زمان‌بندی Heartbeat
     */
    public function schedule_heartbeat() {
        if (!wp_next_scheduled('shik_heartbeat_' . $this->plugin_slug)) {
            wp_schedule_event(time(), 'daily', 'shik_heartbeat_' . $this->plugin_slug);
        }
    }
    
    /**
     * ارسال Heartbeat به سرور
     */
    public function send_heartbeat() {
        $license_data = $this->get_license_data();
        
        // جمع‌آوری اطلاعات سایت
        $site_info = array(
            'domain' => $this->get_current_domain(),
            'plugin_slug' => $this->plugin_slug,
            'version' => $this->version,
            'license_key' => $license_data ? $license_data['license_key'] : '',
            'site_url' => home_url(),
            'site_name' => get_bloginfo('name'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        );
        
        // ارسال به سرور
        wp_remote_post($this->server_url . '/wp-json/shik-license/v1/heartbeat', array(
            'body' => $site_info,
            'timeout' => 10,
            'blocking' => false // Non-blocking برای عدم کند شدن سایت
        ));
    }
    
    /**
     * تایید اعتبار لایسنس (دوره‌ای)
     */
    public function verify_license_periodic() {
        $license_data = $this->get_license_data();
        
        if (!$license_data) {
            return;
        }
        
        $response = wp_remote_post($this->server_url . '/wp-json/shik-license/v1/verify', array(
            'body' => array(
                'license_key' => $license_data['license_key'],
                'domain' => $license_data['domain']
            ),
            'timeout' => 15
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!$body || !$body['valid']) {
                // لایسنس نامعتبر شده
                $license_data['status'] = 'inactive';
                update_option($this->option_key, $license_data);
                
                // غیرفعال کردن افزونه
                deactivate_plugins($this->plugin_file);
            }
        }
    }
    
    /**
     * بررسی آپدیت
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $license_data = $this->get_license_data();
        
        if (!$license_data || $license_data['status'] !== 'active') {
            return $transient;
        }
        
        $response = wp_remote_post($this->server_url . '/wp-json/shik-license/v1/check-update', array(
            'body' => array(
                'license_key' => $license_data['license_key'],
                'domain' => $license_data['domain'],
                'plugin_slug' => $this->plugin_slug,
                'version' => $this->version
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $transient;
        }
        
        $update_info = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($update_info && isset($update_info['new_version'])) {
            $plugin_data = array(
                'slug' => $this->plugin_slug,
                'plugin' => plugin_basename($this->plugin_file),
                'new_version' => $update_info['new_version'],
                'url' => $update_info['url'],
                'package' => $this->get_download_url($license_data['license_key'])
            );
            
            $transient->response[plugin_basename($this->plugin_file)] = (object) $plugin_data;
        }
        
        return $transient;
    }
    
    /**
     * دریافت URL دانلود
     */
    private function get_download_url($license_key) {
        return add_query_arg(array(
            'license_key' => $license_key,
            'domain' => $this->get_current_domain(),
            'plugin_slug' => $this->plugin_slug
        ), $this->server_url . '/wp-json/shik-license/v1/download');
    }
    
    /**
     * اطلاعات افزونه
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $license_data = $this->get_license_data();
        
        if (!$license_data) {
            return $result;
        }
        
        $response = wp_remote_post($this->server_url . '/wp-json/shik-license/v1/check-update', array(
            'body' => array(
                'license_key' => $license_data['license_key'],
                'domain' => $license_data['domain'],
                'plugin_slug' => $this->plugin_slug,
                'version' => $this->version
            )
        ));
        
        if (!is_wp_error($response)) {
            $info = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($info) {
                return (object) $info;
            }
        }
        
        return $result;
    }
    
    /**
     * دریافت اطلاعات لایسنس
     */
    private function get_license_data() {
        return get_option($this->option_key, false);
    }
    
    /**
     * دریافت دامنه فعلی
     */
    private function get_current_domain() {
        $url = home_url();
        $domain = preg_replace('#^https?://#', '', $url);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');
        
        return $domain;
    }
    
    /**
     * تست دستی Heartbeat (برای دیباگ)
     */
    public function test_heartbeat() {
        $this->send_heartbeat();
        return array('success' => true, 'message' => 'Heartbeat ارسال شد');
    }
}
