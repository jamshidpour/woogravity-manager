<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// بررسی مجوز کاربر
if (!current_user_can('manage_options')) {
    wp_die(__('شما اجازه دسترسی به این صفحه را ندارید.', 'woogravity-manager'));
}

global $wpdb;
$table = WGM_TESTS_TABLE;

// ذخیره اطلاعات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wgm_tests'])) {
    // بررسی nonce برای امنیت
    if (!isset($_POST['wgm_test_manager_nonce']) || !wp_verify_nonce($_POST['wgm_test_manager_nonce'], 'wgm_save_test_settings')) {
        wp_die(__('خطای امنیتی. لطفاً دوباره تلاش کنید.', 'woogravity-manager'));
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($_POST['wgm_tests'] as $form_id => $data) {
        try {
            $form_id = intval($form_id);
            $slug = isset($data['slug']) ? sanitize_title($data['slug']) : '';
            $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
            $duration = isset($data['duration']) ? intval($data['duration']) : 0;
            $active = !empty($data['active']) ? 1 : 0;
            $time_limit_enabled = !empty($data['time_limit_enabled']) ? 1 : 0;
            
            // اعتبارسنجی
            if ($form_id <= 0) {
                throw new Exception(sprintf(__('شناسه فرم نامعتبر: %d', 'woogravity-manager'), $form_id));
            }
            
            // اگر slug خالی است، حذف از جدول
            if (empty($slug)) {
                $deleted = $wpdb->delete($table, ['form_id' => $form_id], ['%d']);
                if ($deleted !== false) {
                    $success_count++;
                    wgm_log("Test removed for form ID: {$form_id}");
                } else {
                    throw new Exception($wpdb->last_error ?: __('خطا در حذف رکورد', 'woogravity-manager'));
                }
                continue;
            }
            
            // بررسی تکراری نبودن slug
            $existing_slug = $wpdb->get_var($wpdb->prepare(
                "SELECT form_id FROM {$table} WHERE form_slug = %s AND form_id != %d",
                $slug, $form_id
            ));
            
            if ($existing_slug) {
                throw new Exception(sprintf(__('Slug "%s" قبلاً استفاده شده است.', 'woogravity-manager'), $slug));
            }
            
            // اعتبارسنجی مدت زمان
            if ($time_limit_enabled && $duration <= 0) {
                throw new Exception(__('مدت زمان باید بیشتر از صفر باشد.', 'woogravity-manager'));
            }
            
            // اگر رکورد وجود دارد، آپدیت کن، وگرنه درج کن
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE form_id = %d", $form_id));
            
            $data_arr = [
                'form_id' => $form_id,
                'form_slug' => $slug,
                'title' => $title,
                'duration_minutes' => $time_limit_enabled ? $duration : null,
                'is_active' => $active,
                'time_limit_enabled' => $time_limit_enabled,
            ];
            
            if ($exists) {
                $result = $wpdb->update(
                    $table, 
                    $data_arr, 
                    ['form_id' => $form_id],
                    ['%d', '%s', '%s', '%d', '%d', '%d'],
                    ['%d']
                );
                wgm_log("Test updated for form ID: {$form_id}");
            } else {
                $result = $wpdb->insert(
                    $table, 
                    $data_arr,
                    ['%d', '%s', '%s', '%d', '%d', '%d']
                );
                wgm_log("Test created for form ID: {$form_id}");
            }
            
            if ($result !== false) {
                $success_count++;
            } else {
                throw new Exception($wpdb->last_error ?: __('خطا در ذخیره اطلاعات', 'woogravity-manager'));
            }
            
        } catch (Exception $e) {
            $error_count++;
            $errors[] = sprintf(__('فرم %d: %s', 'woogravity-manager'), $form_id, $e->getMessage());
            wgm_log("Error saving test for form ID {$form_id}: " . $e->getMessage(), 'error');
        }
    }
    
    // نمایش پیغام‌های نتیجه
    if ($success_count > 0) {
        echo '<div class="updated"><p>' . sprintf(_n('تنظیمات ذخیره شد.', 'تنظیمات ذخیره شدند.', $success_count, 'woogravity-manager'), $success_count) . '</p></div>';
    }
    
    if ($error_count > 0) {
        echo '<div class="error"><p>' . sprintf(_n('%d خطا رخ داد:', '%d خطا رخ داد:', $error_count, 'woogravity-manager'), $error_count) . '</p>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }
}

// بررسی وجود Gravity Forms
if (!class_exists('GFAPI')) {
    echo '<div class="error"><p>' . __('افزونه Gravity Forms فعال نیست. لطفا ابتدا آن را نصب و فعال کنید.', 'woogravity-manager') . '</p></div>';
    return;
}

// گرفتن فرم‌های گرویتی فرم
try {
    $forms = GFAPI::get_forms();
    if (empty($forms)) {
        echo '<div class="notice notice-warning"><p>' . __('هیچ فرمی در Gravity Forms یافت نشد. ابتدا فرم‌های خود را ایجاد کنید.', 'woogravity-manager') . '</p></div>';
        return;
    }
} catch (Exception $e) {
    echo '<div class="error"><p>' . __('خطا در دریافت فرم‌ها از Gravity Forms.', 'woogravity-manager') . '</p></div>';
    wgm_log("Error fetching GF forms: " . $e->getMessage(), 'error');
    return;
}

// گرفتن اطلاعات آزمون‌ها از جدول
try {
    $tests = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
    if ($wpdb->last_error) {
        throw new Exception($wpdb->last_error);
    }
} catch (Exception $e) {
    echo '<div class="error"><p>' . __('خطا در دریافت اطلاعات آزمون‌ها از دیتابیس.', 'woogravity-manager') . '</p></div>';
    wgm_log("Database error: " . $e->getMessage(), 'error');
    $tests = [];
}

$tests_by_form = [];
foreach ($tests as $t) {
    $tests_by_form[$t['form_id']] = $t;
}
?>

<div class="wrap">
    <h1><?php _e('مدیریت آزمون‌ها', 'woogravity-manager'); ?></h1>
    
    <div class="wgm-help-text">
        <p><?php _e('در این صفحه می‌توانید گرویتی فرم‌های خود را به عنوان آزمون تنظیم کنید. برای هر فرم می‌توانید Slug (آدرس یکتا)، عنوان، محدودیت زمانی و وضعیت فعال/غیرفعال را تعیین کنید.', 'woogravity-manager'); ?></p>
    </div>
    
    <form method="post" action="" id="wgm-test-manager-form">
        <?php wp_nonce_field('wgm_save_test_settings', 'wgm_test_manager_nonce'); ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('نام فرم', 'woogravity-manager'); ?></th>
                    <th scope="col"><?php _e('Slug', 'woogravity-manager'); ?> <span class="description">(<?php _e('آدرس یکتا', 'woogravity-manager'); ?>)</span></th>
                    <th scope="col"><?php _e('عنوان آزمون', 'woogravity-manager'); ?></th>
                    <th scope="col"><?php _e('محدودیت زمان', 'woogravity-manager'); ?></th>
                    <th scope="col"><?php _e('زمان آزمون (دقیقه)', 'woogravity-manager'); ?></th>
                    <th scope="col"><?php _e('فعال؟', 'woogravity-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): 
                    $form_id = intval($form['id']);
                    $existing = $tests_by_form[$form_id] ?? [];
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($form['title']); ?></strong>
                        <div class="row-actions">
                            <span><?php printf(__('شناسه: %d', 'woogravity-manager'), $form_id); ?></span>
                        </div>
                    </td>
                    <td>
                        <input 
                            type="text"
                            name="wgm_tests[<?php echo $form_id; ?>][slug]" 
                            value="<?php echo esc_attr($existing['form_slug'] ?? ''); ?>" 
                            placeholder="<?php _e('مثال: test-iq', 'woogravity-manager'); ?>"
                            pattern="[a-z0-9\-]+"
                            title="<?php _e('فقط حروف کوچک انگلیسی، اعداد و خط تیره مجاز است', 'woogravity-manager'); ?>"
                        />
                    </td>
                    <td>
                        <input 
                            type="text"
                            name="wgm_tests[<?php echo $form_id; ?>][title]" 
                            value="<?php echo esc_attr($existing['title'] ?? $form['title']); ?>" 
                            placeholder="<?php echo esc_attr($form['title']); ?>"
                        />
                    </td>
                    <td>
                        <input 
                            type="checkbox" 
                            name="wgm_tests[<?php echo $form_id; ?>][time_limit_enabled]" 
                            value="1" 
                            <?php checked($existing['time_limit_enabled'] ?? false); ?> 
                            class="wgm-time-limit-toggle" 
                            data-form-id="<?php echo $form_id; ?>"
                        />
                    </td>
                    <td>
                        <input 
                            type="number" 
                            name="wgm_tests[<?php echo $form_id; ?>][duration]" 
                            value="<?php echo ($existing['time_limit_enabled'] ?? false) ? esc_attr($existing['duration_minutes'] ?? '') : ''; ?>" 
                            min="1" 
                            max="1440"
                            <?php echo !($existing['time_limit_enabled'] ?? false) ? 'disabled' : ''; ?> 
                            class="wgm-duration-field" 
                            data-form-id="<?php echo $form_id; ?>"
                            placeholder="15"
                        />
                    </td>
                    <td>
                        <input 
                            type="checkbox" 
                            name="wgm_tests[<?php echo $form_id; ?>][active]" 
                            value="1" 
                            <?php checked($existing['is_active'] ?? false); ?>
                        />
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="wgm-form-actions">
            <?php submit_button(__('ذخیره تغییرات', 'woogravity-manager'), 'primary', 'submit', false); ?>
            <button type="button" class="button" id="wgm-preview-urls"><?php _e('مشاهده آدرس‌ها', 'woogravity-manager'); ?></button>
        </div>
    </form>
    
    <!-- مودال نمایش آدرس‌ها -->
    <div id="wgm-urls-modal" class="wgm-modal" style="display: none;">
        <div class="wgm-modal-content">
            <div class="wgm-modal-header">
                <h3><?php _e('آدرس‌های آزمون‌ها', 'woogravity-manager'); ?></h3>
                <button type="button" class="wgm-modal-close">&times;</button>
            </div>
            <div class="wgm-modal-body">
                <p><?php _e('آدرس‌های زیر برای دسترسی به آزمون‌ها استفاده می‌شود:', 'woogravity-manager'); ?></p>
                <div id="wgm-urls-list"></div>
            </div>
        </div>
    </div>
</div>

<style>
.wgm-help-text {
    background: #f0f6fc;
    border-left: 4px solid #0073aa;
    padding: 12px;
    margin: 20px 0;
    border-radius: 4px;
}

.wgm-form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.wgm-form-actions .button {
    margin-left: 10px;
}

.wgm-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 100000;
}

.wgm-modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.wgm-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wgm-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.wgm-modal-body {
    padding: 20px;
}

.wgm-url-item {
    background: #f9f9f9;
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.wgm-url-item strong {
    display: block;
    margin-bottom: 5px;
}

.wgm-url-item code {
    background: #fff;
    padding: 5px;
    border-radius: 3px;
    font-size: 13px;
    word-break: break-all;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // مدیریت فعال/غیرفعال کردن فیلد مدت زمان
    document.querySelectorAll('.wgm-time-limit-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var formId = this.getAttribute('data-form-id');
            var durationField = document.querySelector('.wgm-duration-field[data-form-id="' + formId + '"]');
            if (this.checked) {
                durationField.disabled = false;
                durationField.focus();
            } else {
                durationField.value = '';
                durationField.disabled = true;
            }
        });
    });
    
    // مدیریت slug ها - تبدیل به حروف کوچک و جایگزینی فاصله با خط تیره
    document.querySelectorAll('input[name*="[slug]"]').forEach(function(input) {
        input.addEventListener('blur', function() {
            this.value = this.value.toLowerCase()
                .replace(/[^a-z0-9\s\-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/\-+/g, '-')
                .replace(/^\-+|\-+$/g, '');
        });
    });
    
    // نمایش آدرس‌ها
    document.getElementById('wgm-preview-urls').addEventListener('click', function() {
        var modal = document.getElementById('wgm-urls-modal');
        var urlsList = document.getElementById('wgm-urls-list');
        var urls = [];
        
        document.querySelectorAll('input[name*="[slug]"]').forEach(function(input) {
            var slug = input.value.trim();
            var row = input.closest('tr');
            var title = row.querySelector('input[name*="[title]"]').value || 'بدون عنوان';
            
            if (slug) {
                urls.push({
                    title: title,
                    slug: slug,
                    url: '<?php echo home_url('/exam/'); ?>?test=' + slug
                });
            }
        });
        
        if (urls.length === 0) {
            urlsList.innerHTML = '<p><?php _e('هیچ آزمونی با Slug تعریف نشده است.', 'woogravity-manager'); ?></p>';
        } else {
            var html = '';
            urls.forEach(function(item) {
                html += '<div class="wgm-url-item">';
                html += '<strong>' + item.title + '</strong>';
                html += '<code>' + item.url + '</code>';
                html += '</div>';
            });
            urlsList.innerHTML = html;
        }
        
        modal.style.display = 'flex';
    });
    
    // بستن مودال
    document.querySelector('.wgm-modal-close').addEventListener('click', function() {
        document.getElementById('wgm-urls-modal').style.display = 'none';
    });
    
    // بستن مودال با کلیک بیرون از آن
    document.getElementById('wgm-urls-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    
    // اعتبارسنجی فرم قبل از ارسال
    document.getElementById('wgm-test-manager-form').addEventListener('submit', function(e) {
        var slugs = [];
        var duplicates = [];
        var hasError = false;
        
        // بررسی تکراری نبودن slug ها
        document.querySelectorAll('input[name*="[slug]"]').forEach(function(input) {
            var slug = input.value.trim();
            if (slug) {
                if (slugs.includes(slug)) {
                    duplicates.push(slug);
                    hasError = true;
                } else {
                    slugs.push(slug);
                }
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('<?php _e('Slug های تکراری یافت شد:', 'woogravity-manager'); ?> ' + duplicates.join(', '));
            return false;
        }
        
        // بررسی مدت زمان برای آزمون‌های با محدودیت زمانی
        var timeErrors = [];
        document.querySelectorAll('.wgm-time-limit-toggle:checked').forEach(function(checkbox) {
            var formId = checkbox.getAttribute('data-form-id');
            var durationField = document.querySelector('.wgm-duration-field[data-form-id="' + formId + '"]');
            var duration = parseInt(durationField.value);
            
            if (!duration || duration <= 0) {
                var row = checkbox.closest('tr');
                var formName = row.querySelector('td:first-child strong').textContent;
                timeErrors.push(formName);
                hasError = true;
            }
        });
        
        if (timeErrors.length > 0) {
            e.preventDefault();
            alert('<?php _e('لطفاً مدت زمان را برای فرم‌های زیر تعیین کنید:', 'woogravity-manager'); ?>\n' + timeErrors.join('\n'));
            return false;
        }
    });
});
</script>