<?php
namespace PayPingInstallment\Payment;

use PayPingInstallment\Compatibility\CompatibilityManager;

class Gateway {
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        CompatibilityManager::init();
        // woocommerce plugins
        if( CompatibilityManager::is_plugin_active('woocommerce/woocommerce.php') ){
            add_filter('woocommerce_payment_gateways', [$this, 'Woocommerce_Add_payping_installment_Gateway']);
            new \PayPingInstallment\compatibility\Woocommerce\WooPayPingInstallment();
            add_action('woocommerce_blocks_loaded', [$this, 'payping_installment_blocks_payment_method_type_registration']);
        } // woocommerce plugins
    }

    // woocommerce plugins functions
    public function Woocommerce_Add_payping_installment_Gateway($methods) {
        $methods[] = '\PayPingInstallment\Compatibility\Woocommerce\WooPayPingInstallment';
        return $methods;
    }

    public function payping_installment_blocks_payment_method_type_registration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once PPINSTLMNT_PLUGIN_DIR . '/includes/compatibility/woocommerce/woopaypinginstallmentblocks.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function($registry) {
                    $registry->register(new \PayPingInstallment\Compatibility\WooCommerce\WooPayPingInstallmentBlocks());
                }
            );
        }
    }
    // woocommerce plugins functions
}