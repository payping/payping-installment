<div class="wrap">
    <form method="post" action="options.php" id="payping-settings">
        <?php settings_fields('payping_installment_setting');
            do_settings_sections('payping_installment_setting'); ?>
        <div class="full-width-settings">
            <h3>تنظیمات احراز هویت</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">توکن پی پینگ</th>
                    <td>
                        <input type="password" 
                            name="payping_access_token" 
                            id="payping_access_token" 
                            value="<?php echo esc_attr(get_option('payping_access_token')); ?>" 
                            class="regular-text"
                            autocomplete="off">
                        <p class="description">
                            توکن را می‌توانید از طریق 
                            <a href="https://newapp.payping.ir/connections/developers" target="_blank">پنل کاربری پی‌پینگ</a> 
                            دریافت کنید.
                        </p>
                    </td>
                </tr>
            </table>

            <h3>تنظیمات یکپارچه‌سازی</h3>
            <blockquote>
            برای استفاده از سرویس پرداخت اقساطی لازم است که شماره موبایل مشتریان شما در مرحله ثبت سفارش ارسال گردد،‌ بر این اساس لازم است تا یکی از روش های زیر انتخاب گردد.
            </blockquote>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">استفاده از دیجیتس</th>
                    <td>
                        <label>
                            <input type="checkbox" name="payping_use_digits" id="payping_use_digits" value="1" <?php checked(1, get_option('payping_use_digits'), true); ?>>
                             فعال کردن یکپارچه‌سازی با افزونه دیجیتس
                        </label>
                        <p class="description">
                        با فعال کردن این حالت شماره موبایل از اطلاعات مشتری در افزونه دیجیتس دریافت خواهد شد.
                        </p>
                    </td>
                </tr>
                <tr valign="top" class="non-digits-option" style="<?php echo get_option('payping_use_digits') ? 'display:none;' : '';?>">
                    <th scope="row">روش جایگزین دریافت شماره همراه</th>
                    <td>
                        <label>
                            <input type="radio" name="payping_mobile_source" value="force_add_field" <?php checked('force_add_field', get_option('payping_mobile_source'), true); ?> >
                            ایجاد فیلد اجباری در صفحه تسویه حساب
                            <blockquote>
                            با فعال کردن این حالت یک فیلد اجباری شماره همراه در مراحل ثبت سفارش اضافه خواهد شد.
                            </blockquote>
                        </label>
                        <br>
                        <label>
                            <input type="radio" 
                                name="payping_mobile_source" 
                                value="custom_field" 
                                <?php checked('custom_field', get_option('payping_mobile_source'), true); ?> >
                            استفاده از فیلد سفارشی
                            <blockquote>
                            با فعال کردن این حالت لازم است تا نام فیلدی که در آن شماره همراه مشتری در سفارش ذخیره می‌شود را وارد نمایید.
                            </blockquote>
                            <input type="text" name="payping_custom_mobile_field" value="<?php echo esc_attr(get_option('payping_custom_mobile_field')); ?>" placeholder="_billing_phone" dir="ltr" class="regular-text">
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php submit_button('ذخیره تنظیمات'); ?>
    </form>
    <div id="oauth-modal" style="display:none;">
        <div class="oauth-modal-content">
            <iframe id="oauth-frame" src="" style="width:100%;height:500px;border:0;"></iframe>
        </div>
    </div>
</div>