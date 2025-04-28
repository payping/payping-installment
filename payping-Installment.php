<?php
/**
 * Plugin Name: PayPing Installment for wordpress
 * Description: افزونه درگاه پرداخت اقساطی پی‌پینگ برای وردپرس
 * Version: 0.0.2
 * Requires at least: 6.5
 * Requires PHP: 7.2
 * Author: PHP Team PayPing
 * Author URI: https://payping.io/about
 * Text Domain: payping_installment
 * Domain Path: /languages
 * License: GPLv2 or later
 */

if(!defined('ABSPATH')) exit;

define('PPINSTLMNT_VERSION', '0.0.1');
define('PPINSTLMNT_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
define('PPINSTLMNT_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('PPINSTLMNT_TEXT_DOMAIN', 'payping-installment' );

// Autoload classes
spl_autoload_register(function($class) {
    /**
     * The namespace prefix we want to use.
     * @var string
     */
    $prefix = 'PayPingInstallment\\';

    /**
     * Base directory for the class files.
     * @var string
     */
    $base_dir = __DIR__ . '/includes/';

    /**
     * The length of the prefix.
     * Used for strpos() comparisons.
     * @var int
     */
    $len = strlen($prefix);

    if(strncmp($prefix, $class, $len) !== 0)return;

    /**
     * The relative class name.
     * @var string
     */
    $relative_class = substr($class, $len);

    /**
     * The full path to the class file.
     * @var string
     */
    $file = $base_dir . str_replace('\\', '/', strtolower($relative_class)) . '.php';

    if(file_exists($file))require $file;
});

/*
function payping_installment_gateway() {
    PayPingInstallment\core\Plugin::instance();
}
payping_installment_gateway();
*/
function payping_installment_gateway() {
    return \PayPingInstallment\core\Plugin::instance();
}
payping_installment_gateway();