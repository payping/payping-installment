<?php
namespace PayPingInstallment\Admin;

class Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_dashboard_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_dashboard_page() {
        add_menu_page(
            'PayPing Installment',
            __('پرداخت اقساطی', 'payping-installment'),
            'manage_options',
            'payping-installment',
            [$this, 'render_main_page'],
            PPINSTLMNT_PLUGIN_URL.'assets/img/icon.png'
        );
        
        add_submenu_page(
            'payping-installment',
            __('دریافت و جایگذاری توکن و ارتباط با سرویس اقساط پی پینگ', 'payping-installment'),
            __('تنظیمات', 'payping-installment'),
            'manage_options',
            'payping-installment-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-settings'
        );
        
        add_submenu_page(
            'payping-installment',
            __('دریافت گزارش تراکنش ها', 'payping-installment'),
            __('گزارش ها', 'payping-installment'),
            'manage_options',
            'payping-installment-orders-list',
            [$this, 'render_orders_list_page']
        );

        add_submenu_page(
            null,
            __('جزئیات سفارش', 'payping-installment'),
            '',
            'manage_options',
            'payping-installment-order-detail',
            [$this, 'render_orders_detail_page']
        );
    }

    public function register_settings() {
        register_setting(
            'payping_installment_setting',
            'payping_access_token',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
                'default' => ''
            ]
        );
        register_setting('payping_installment_setting', 'payping_use_digits');
        register_setting('payping_installment_setting', 'payping_mobile_source');
        register_setting('payping_installment_setting', 'payping_custom_mobile_field');
    }

    public function render_main_page() {
        include PPINSTLMNT_PLUGIN_DIR . 'templates/admin/main-page.php';
    }
    
    public function render_settings_page() {
        include PPINSTLMNT_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
    
    public function render_orders_list_page() {
        include PPINSTLMNT_PLUGIN_DIR . 'templates/admin/orders-list-page.php';
    }

    public function render_orders_detail_page() {
        include PPINSTLMNT_PLUGIN_DIR . 'templates/admin/order-detail-page.php';
    }
}