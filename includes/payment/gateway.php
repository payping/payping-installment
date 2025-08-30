<?php
namespace PayPingInstallment\Payment;

use PayPingInstallment\Compatibility\CompatibilityManager;

class Gateway {
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        CompatibilityManager::init();
        
        // Debug log to check if this method is being called
        error_log('PayPing Installment: Gateway init method called');
        
        // woocommerce plugins
        $is_woo_active = CompatibilityManager::is_plugin_active('woocommerce/woocommerce.php');
        error_log('PayPing Installment: WooCommerce detected as ' . ($is_woo_active ? 'active' : 'inactive'));
        
        if( $is_woo_active ){
            error_log('PayPing Installment: Adding WooCommerce hooks');
            \add_filter('woocommerce_payment_gateways', [$this, 'Woocommerce_Add_payping_installment_Gateway'], 10, 1);
            new \PayPingInstallment\compatibility\Woocommerce\WooPayPingInstallment();
            \add_action('woocommerce_blocks_loaded', [$this, 'payping_installment_blocks_payment_method_type_registration'], 10);
        } else {
            error_log('PayPing Installment: WooCommerce not detected, hooks not added');
        } // woocommerce plugins
    }

    // woocommerce plugins functions
    public function Woocommerce_Add_payping_installment_Gateway($methods) {
        error_log('PayPing Installment: Adding gateway to WooCommerce payment methods');
        $methods[] = '\PayPingInstallment\Compatibility\Woocommerce\WooPayPingInstallment';
        return $methods;
    }

    public function payping_installment_blocks_payment_method_type_registration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once PPINSTLMNT_PLUGIN_DIR . '/includes/compatibility/woocommerce/woopaypinginstallmentblocks.php';
            \add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function($registry) {
                    $registry->register(new \PayPingInstallment\Compatibility\WooCommerce\WooPayPingInstallmentBlocks());
                }
            );
        }
    }
    // woocommerce plugins functions
}