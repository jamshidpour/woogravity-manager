/**
 * مدیریت رفتارهای جاوااسکریپت افزونه WooGravity Manager
 */

jQuery(document).ready(function($) {
    
    let timerInterval;
    let remainingTime;
    let totalDuration;
    
    // مدیریت تایمر آزمون
    function initTestTimer() {
        const $timer = $('#wgm-countdown');
        if (!$timer.length) return;

        totalDuration = parseInt($timer.data('duration')) * 60; // تبدیل دقیقه به ثانیه
        remainingTime = totalDuration;
        const redirectUrl = $timer.data('redirect-url') || '';

        function updateTimer() {
            const mins = Math.floor(remainingTime / 60);
            const secs = remainingTime % 60;
            
            // نمایش تایمر
            $timer.text(
                `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
            );
            
            // بروزرسانی progress bar
            const percent = ((totalDuration - remainingTime) / totalDuration) * 100;
            $('#wgm-progress').css('width', percent + '%');
            
            // تغییر رنگ در زمان کم
            if (remainingTime <= 60) {
                $timer.addClass('danger');
                $('#wgm-progress').css('background-color', '#e74c3c');
            } else if (remainingTime <= 180) {
                $timer.css('background-color', '#f39c12');
                $('#wgm-progress').css('background-color', '#f39c12');
            }
            
            // اتمام زمان
            if (remainingTime <= 0) {
                clearInterval(timerInterval);
                submitForm();
                return;
            }
            
            remainingTime--;
        }
        
        // شروع تایمر
        timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // اجرای فوری برای نمایش اولیه
    }

    // مدیریت ارسال خودکار فرم
    function submitForm() {
        console.log('WGM: Starting auto-submit process');
        
        const $form = $('.gform_wrapper form');
        if (!$form.length) {
            console.log('WGM: No GF form found, showing modal');
            showTimeoutModal();
            return;
        }
        
        // غیرفعال کردن اعتبارسنجی
        $form.find('.validation_message').hide();
        $form.find('.gfield_error').removeClass('gfield_error');
        
        // پر کردن پاسخ‌های خالی برای رادیو باتن‌ها
        const processedGroups = new Set();
        $form.find('input[type="radio"]').each(function() {
            const name = $(this).attr('name');
            if (name && !processedGroups.has(name)) {
                processedGroups.add(name);
                
                // بررسی اینکه آیا هیچ گزینه‌ای انتخاب نشده
                if (!$form.find(`input[name="${name}"]:checked`).length) {
                    // انتخاب اولین گزینه
                    const $firstOption = $form.find(`input[name="${name}"]`).first();
                    $firstOption.prop('checked', true);
                    
                    // اضافه کردن فلگ برای شناسایی انتخاب خودکار
                    $form.append(`<input type="hidden" name="${name}_auto_selected" value="1">`);
                    console.log(`WGM: Auto-selected first option for field: ${name}`);
                }
            }
        });
        
        // افزودن فلگ ارسال خودکار
        $form.append('<input type="hidden" name="wgm_auto_submit" value="1">');
        
        // ارسال فرم
        try {
            if (typeof window.gform !== 'undefined' && window.gform.submitForm) {
                console.log('WGM: Using GF submitForm method');
                window.gform.submitForm($form[0]);
            } else {
                console.log('WGM: Using standard form submit');
                $form.submit();
            }
        } catch (error) {
            console.error('WGM: Error submitting form:', error);
        }
        
        // نمایش مودال
        setTimeout(function() {
            showTimeoutModal();
        }, 1000);
    }

    // نمایش مودال اتمام زمان
    function showTimeoutModal() {
        console.log('WGM: Showing timeout modal');
        
        const $modal = $('#wgm-timeout-modal');
        if (!$modal.length) {
            console.error('WGM: Timeout modal not found');
            return;
        }
        
        $modal.fadeIn(300);
        
        const redirectUrl = $modal.data('redirect-url') || '';
        let countdown = 10;
        
        const $timer = $('#wgm-redirect-timer');
        const $progress = $('#wgm-redirect-progress');
        
        // شروع شمارش معکوس
        const redirectInterval = setInterval(function() {
            $timer.text(countdown);
            
            // بروزرسانی progress bar
            const progressPercent = (100 - (countdown * 10));
            $progress.css('width', progressPercent + '%');
            
            if (countdown <= 0) {
                clearInterval(redirectInterval);
                redirectToUrl(redirectUrl);
            }
            countdown--;
        }, 1000);
        
        // کلیک دکمه انتقال فوری
        $('#wgm-redirect-now-btn').off('click').on('click', function() {
            clearInterval(redirectInterval);
            redirectToUrl(redirectUrl);
        });
    }
    
    // هدایت به URL
    function redirectToUrl(url) {
        if (url) {
            console.log('WGM: Redirecting to:', url);
            window.location.href = url;
        } else {
            console.log('WGM: No redirect URL provided');
            // بستن مودال در صورت عدم وجود URL
            $('#wgm-timeout-modal').fadeOut();
        }
    }

    // جلوگیری از خروج هنگام انجام آزمون
    function preventPageExit() {
        const $timer = $('#wgm-countdown');
        if (!$timer.length) return;

        window.addEventListener('beforeunload', function(e) {
            if (remainingTime > 0) {
                const message = wgm_ajax && wgm_ajax.strings ? 
                    wgm_ajax.strings.confirm_exit : 
                    'آیا مطمئن هستید که می‌خواهید صفحه را ترک کنید؟';
                
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
        });
    }

    // پردازش submission موفق گرویتی فرم
    $(document).on('gform_confirmation_loaded', function(event, formId) {
        console.log('WGM: Form submitted successfully, form ID:', formId);
        
        // پاک کردن تایمر
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        
        // حذف محافظت از خروج
        window.onbeforeunload = null;
        
        // اگر ارسال خودکار بود، مودال را نمایش بده
        if ($('input[name="wgm_auto_submit"]').length) {
            setTimeout(showTimeoutModal, 500);
        }
    });

    // مقداردهی اولیه
    initTestTimer();
    preventPageExit();

    // بستن مودال با کلیک خارج از آن
    $(document).on('click', '.wgm-modal', function(e) {
        if (e.target === this) {
            $(this).fadeOut();
        }
    });
});
