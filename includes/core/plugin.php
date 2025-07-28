<?php
namespace PayPingInstallment\Core;

class Plugin {
    private static $instance = null;

    private function __construct() {
        $this->init();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        add_action('init', [$this, 'init_gateway']);
        add_action('admin_enqueue_scripts', [$this, 'payping_installment_wp_admin_css_and_js']);
    }

    public function init_gateway() {
        load_plugin_textdomain('payping-installment', false, trailingslashit(PPINSTLMNT_PLUGIN_DIR) . 'languages/');
        new \PayPingInstallment\admin\admin();
        new \PayPingInstallment\Payment\Gateway();
    }

    public function payping_installment_wp_admin_css_and_js($hook){
        $allowed_pages = [
            'toplevel_page_payping-installment',
            'payping-instalment_page_payping-installment-token-setting',
            
        ];

        wp_enqueue_style(
            'payping_installment_wp_admin',
            PPINSTLMNT_PLUGIN_URL.'assets/css/admin.css',
            [],
            filemtime(PPINSTLMNT_PLUGIN_DIR . 'assets/css/admin.css')
        );
    
        if(strpos($hook, 'payping-installment') !== false || in_array($hook, $allowed_pages)){

            wp_enqueue_style(
                'payping_installment_wp_admin_pp_installment',
                PPINSTLMNT_PLUGIN_URL.'assets/css/admin_pp_installment.css',
                [],
                filemtime(PPINSTLMNT_PLUGIN_DIR . 'assets/css/admin_pp_installment.css')
            );
            
            wp_enqueue_script(
                'payping_installment_wp_admin',
                PPINSTLMNT_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery', 'wp-i18n'],
                filemtime(PPINSTLMNT_PLUGIN_DIR . 'assets/js/admin.js'),
                true
            );
    
            wp_localize_script('payping_installment_wp_admin', 'paypingData', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('payping-installment-nonce')
            ]);
        }
    }
}