<?php
/**
 * PayPing Installment Debug Test File
 * 
 * این فایل برای تست و تشخیص مشکل افزونه پرداخت اقساطی پی‌پینگ استفاده می‌شود
 * 
 * برای استفاده:
 * 1. این فایل را در ریشه سایت WordPress قرار دهید
 * 2. به آدرس yoursite.com/debug-test.php مراجعه کنید
 * 3. نتایج را بررسی کنید
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

echo '<h2>PayPing Installment Plugin Debug Test</h2>';

// Check if WordPress is loaded
if (!function_exists('is_plugin_active')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

echo '<h3>Basic Checks:</h3>';
echo '<ul>';

// Check if WooCommerce is active
$woo_active = is_plugin_active('woocommerce/woocommerce.php');
echo '<li>WooCommerce Active: ' . ($woo_active ? '<strong style="color:green">YES</strong>' : '<strong style="color:red">NO</strong>') . '</li>';

// Check if PayPing Installment is active
$payping_active = is_plugin_active('payping-installment/payping-Installment.php');
echo '<li>PayPing Installment Active: ' . ($payping_active ? '<strong style="color:green">YES</strong>' : '<strong style="color:red">NO</strong>') . '</li>';

// Check if the main class exists
$class_exists = class_exists('\PayPingInstallment\Compatibility\Woocommerce\WooPayPingInstallment');
echo '<li>PayPing Gateway Class Exists: ' . ($class_exists ? '<strong style="color:green">YES</strong>' : '<strong style="color:red">NO</strong>') . '</li>';

// Check token configuration
$token = get_option('payping_access_token');
echo '<li>PayPing Access Token: ' . (!empty($token) ? '<strong style="color:green">SET (' . strlen($token) . ' chars)</strong>' : '<strong style="color:red">NOT SET</strong>') . '</li>';

echo '</ul>';

if ($woo_active) {
    echo '<h3>WooCommerce Payment Gateways:</h3>';
    $gateways = WC_Payment_Gateways::instance()->payment_gateways();
    echo '<ul>';
    foreach ($gateways as $id => $gateway) {
        $status = $gateway->enabled === 'yes' ? 'Enabled' : 'Disabled';
        echo '<li>' . $gateway->method_title . ' (ID: ' . $id . ') - ' . $status . '</li>';
    }
    echo '</ul>';
    
    // Check if our gateway is in the list
    if (isset($gateways['payping_installment'])) {
        echo '<p style="color:green"><strong>✓ PayPing Installment gateway found in WooCommerce!</strong></p>';
        
        $our_gateway = $gateways['payping_installment'];
        echo '<h4>Gateway Details:</h4>';
        echo '<ul>';
        echo '<li>Title: ' . $our_gateway->title . '</li>';
        echo '<li>Description: ' . $our_gateway->description . '</li>';
        echo '<li>Enabled: ' . ($our_gateway->enabled === 'yes' ? 'YES' : 'NO') . '</li>';
        echo '<li>Available: ' . ($our_gateway->is_available() ? 'YES' : 'NO') . '</li>';
        echo '</ul>';
    } else {
        echo '<p style="color:red"><strong>✗ PayPing Installment gateway NOT found in WooCommerce!</strong></p>';
        echo '<p>This means the plugin is not properly registering with WooCommerce.</p>';
    }
} else {
    echo '<p style="color:red">WooCommerce is not active. Please activate WooCommerce first.</p>';
}

echo '<h3>Active Plugins:</h3>';
$active_plugins = get_option('active_plugins');
echo '<ul>';
foreach ($active_plugins as $plugin) {
    echo '<li>' . $plugin . '</li>';
}
echo '</ul>';

echo '<h3>Debug Logs:</h3>';
echo '<p>Check your WordPress debug log (usually in wp-content/debug.log) for messages starting with "PayPing Installment:"</p>';
echo '<p>To enable debug logging, add these lines to your wp-config.php:</p>';
echo '<pre>define("WP_DEBUG", true);
define("WP_DEBUG_LOG", true);
define("WP_DEBUG_DISPLAY", false);</pre>';

echo '<hr>';
echo '<p><em>Test completed. Please review the results above.</em></p>';
?>