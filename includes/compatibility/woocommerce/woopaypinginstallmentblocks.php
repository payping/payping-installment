<?php
namespace PayPingInstallment\Compatibility\Woocommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WooPayPingInstallmentBlocks extends AbstractPaymentMethodType {

    protected $name = 'payping_installment';
    
    public function initialize() {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        $this->gateway = WC()->payment_gateways->payment_gateways()[$this->name] ?? null;
    }

    public function is_active() {
        return $this->gateway ? $this->gateway->is_available() : false;
    }

    public function get_payment_method_script_handles() {
        
        wp_register_script(
            'payping-installment-blocks',
            PPINSTLMNT_PLUGIN_URL . 'assets/js/blocks/woo-payping-installment-checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            false,
            true
        );
        
        wp_localize_script(
            'payping-installment-blocks',
            'paypingInstallmentSettings',
            [
                'icon'        => $this->gateway->icon,
                'title'       => $this->gateway->title ?? '',
                'description' => $this->gateway->description ?? '',
                'ariaLabel'   => $this->gateway->description ?? ''
            ]
        );
        
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'payping-installment-blocks',
                'payping-installment',
                PPINSTLMNT_PLUGIN_DIR . 'languages'
            );
        }
        
        return ['payping-installment-blocks'];
    }

    public function get_payment_method_data() {
        if (is_null($this->gateway)) {
            return [];
        }
        
        return [
            'title' => $this->gateway->title ?? '',
            'description' => $this->gateway->description ?? '',
            'icon' => PPINSTLMNT_PLUGIN_URL . '/assets/img/logo.png',
            'supports' => $this->gateway->supports ?? [],
            'custom_data' => [
                'payment_instructions' => $this->gateway->description ?? ''
            ]
        ];
    }
}