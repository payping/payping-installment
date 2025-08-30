<?php
namespace PayPingInstallment\Compatibility\Woocommerce;

use WC_Payment_Gateway;
use WC_Order;
use Exception;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

class WooPayPingInstallment extends WC_Payment_Gateway{
    
    /**
     * Base API URL
     * @var string
     */
    private $baseurl = 'https://api.payping.ir/v1';

    /**
     * PayPing API access token
     * @var string
     */
    private $paypingToken;
    private $mobile_number_type;
    
    /**
     * Determine if the payment method is available
     *
     * @override
     * @return bool
     */
    public function is_available() {
        // Disable for WooCommerce Blocks checkout
        if (function_exists('is_wc_block_checkout') && is_wc_block_checkout()) {
            error_log('PayPing Installment: Gateway disabled for WooCommerce Blocks checkout');
            return false;
        }
        
        // Check if token is configured
        if (empty($this->paypingToken)) {
            error_log('PayPing Installment: Gateway not available - Token is empty or not configured');
            return false;
        }
        
        $parent_available = parent::is_available();
        error_log('PayPing Installment: Gateway availability check - Token OK, Parent available: ' . ($parent_available ? 'YES' : 'NO'));
        
        return $parent_available;
    }
	
	/**
     * Constructor for the gateway
     *
     * @constructor
     * @uses init_settings()
     * @uses add_action()
     */
    public function __construct() {
        $this->id = 'payping_installment';
        $this->method_title = __('پرداخت اقساطی پی پینگ', 'payping-installment');
        $this->method_description = __('پرداخت اقساطی پی پینگ برای ووکامرس', 'payping-installment');
        $this->icon = apply_filters('woo_payping_logo', PPINSTLMNT_PLUGIN_URL . '/assets/img/logo.png');
        $this->has_fields = false;

        // Initialize settings
        $this->init_form_fields();
        $this->init_settings();

        // Configure API endpoints
        $this->baseurl = ($this->settings['ioserver'] ?? '') === 'yes' 
            ? 'https://api.payping.io/v1' 
            : 'https://api.payping.ir/v1';

        // Set user-facing fields
        $this->title = $this->settings['title'] ?? '';
        $this->description = $this->settings['description'] ?? '';

        // Configure messages
        $this->success_massage = $this->settings['success_massage'] ?? '';
        $this->failed_massage = $this->settings['failed_massage'] ?? '';

        // Get API credentials
        $this->paypingToken = sanitize_text_field(get_option('payping_access_token'));

        $this->mobile_number_type = get_option('payping_mobile_source', 'force_add_field');

        // Hook into WooCommerce
        $this->add_hooks();
    }

	/**
     * Add required WordPress/WooCommerce hooks
     *
     * @private
     * @uses version_compare()
     * @uses add_action()
     */
    private function add_hooks() {
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        } else {
            add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
        }

        // added hooks 
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_mobile_in_admin_order'], 10, 1);
        add_action('woocommerce_order_details_after_customer_details', [$this, 'display_mobile_in_order_details']);

        add_action('woocommerce_receipt_' . $this->id, [$this, 'Send_to_payping_Gateway']);
        add_action('woocommerce_api_' . $this->id, [$this, 'Return_from_payping_Gateway']);

        $checkout_type = $this->detect_checkout_type();
        
        // Block-based checkout
        if ('block' === $checkout_type && $this->mobile_number_type == 'force_add_field') {
            $this->register_block_checkout_hooks();
            return;
        }

        // Shortcode-based checkout
        if ('shortcode' === $checkout_type || 'none' === $checkout_type && $this->mobile_number_type == 'force_add_field') {
            $this->register_shortcode_checkout_hooks();
        }
    }

    /**
     * Register hooks for block-based checkout
     */
    private function register_block_checkout_hooks() {
        add_action('woocommerce_init', [$this, 'register_mobile_number_checkout_field']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate_mobile_number_field'], 10, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'save_mobile_number_to_order_meta'], 10, 2); //woocommerce_checkout_update_order_meta
    }

    /**
     * Register hooks for shortcode-based checkout
     */
    private function register_shortcode_checkout_hooks() {
        add_filter('woocommerce_checkout_fields', [$this, 'add_mobile_checkout_field']);
        add_action('woocommerce_checkout_process', [$this, 'validate_mobile_field']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_mobile_meta']);
    }
	
	/**
     * Safely constructs API request URL
     * 
     * @param string $endpoint API endpoint path
     * @return string Full sanitized URL
     */
    private function build_api_url($endpoint) {
        // Normalize base URL
        $base_url = untrailingslashit($this->baseurl);
        
        // Sanitize endpoint path
        $endpoint = '/' . ltrim($endpoint, '/');
        
        // Combine parts
        $full_url = $base_url . $endpoint;
        
        return esc_url_raw($full_url);
    }

	/**
	 * Display gateway configuration in admin panel
	 *
	 * @override
	 * @uses parent::admin_options()
	 */
	public function admin_options() {
		parent::admin_options();
	}

	/**
	 * Initialize gateway settings form fields
	 *
	 * Defines configuration fields for the payment gateway including:
	 * - Basic settings
	 * - Account configuration
	 * - Payment operation settings
	 * 
	 * @override
	 * @uses apply_filters()
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters('payping_installment_Config', [
			'base_confing' => [
				'title'       => __('تنظیمات پایه ای', 'payping-installment'),
				'type'        => 'title',
				'description' => '',
			],
			'enabled' => [
				'title'       => __('فعالسازی/غیرفعالسازی', 'payping-installment'),
				'type'        => 'checkbox',
				'label'      => __('فعالسازی درگاه اقساطی پی پینگ', 'payping-installment'),
				'description' => __('برای فعالسازی درگاه پرداخت اقساطی پی پینگ باید چک باکس را تیک بزنید', 'payping-installment'),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'ioserver' => [
				'title'       => __('سرور خارج', 'payping-installment'),
				'type'        => 'checkbox',
				'label'       => __('اتصال به سرور خارج', 'payping-installment'),
				'description' => __('در صورت تیک خوردن، درگاه به سرور خارج از کشور متصل می شود.', 'payping-installment'),
				'default'     => 'no',
				'desc_tip'    => true,
			],
			'title' => [
				'title'       => __('عنوان درگاه', 'payping-installment'),
				'type'        => 'text',
				'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'payping-installment'),
				'default'     => __('پرداخت اقساطی پی پینگ', 'payping-installment'),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __('توضیحات درگاه', 'payping-installment'),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'payping-installment'),
				'default'     => __('پرداخت با استفاده از درگاه اقساطی پی پینگ', 'payping-installment')
			],
			'account_confing' => [
				'title'       => __('تنظیمات حساب پی پینگ', 'payping-installment'),
				'type'        => 'title',
				'description' => '',
			],
			'payment_confing' => [
				'title'       => __('تنظیمات عملیات پرداخت', 'payping-installment'),
				'type'        => 'title',
				'description' => '',
			],
			'success_massage' => [
				'title'       => __('پیام پرداخت موفق', 'payping-installment'),
				'type'        => 'textarea',
				'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) پی پینگ استفاده نمایید .', 'payping-installment'),
				'default'     => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'payping-installment'),
			],
			'failed_massage' => [
				'title'       => __('پیام پرداخت ناموفق', 'payping-installment'),
				'type'        => 'textarea',
				'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید .', 'payping-installment'),
				'default'     => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'payping-installment'),
			]
		]);
	}

	/**
	 * Process payment and redirect to payment gateway
	 *
	 * @param int $order_id WooCommerce order ID
	 * @return array Payment processing result with redirect URL
	 * 
	 * @throws Exception If order cannot be retrieved
	 */
	public function process_payment($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			throw new Exception(__('سفارش یافت نشد!', 'payping-installment'));
		}
		
		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url(true)
		];
	}

	/**
     * Process payment and redirect to PayPing gateway
     *
     * @param int $order_id WC Order ID
     * @throws Exception On payment processing errors
     */
    public function send_to_payping_gateway($order_id) {
        try {
            // Validate and initialize order
            $order = $this->validate_order($order_id);
            $this->initialize_session($order_id);

            // Generate and display payment form
            echo $this->generate_payment_form($order);

            // Prepare and send payment request
            $response = $this->process_payment_request($order);
            $this->handle_api_response($order, $response);

        } catch (Exception $e) {
            $this->handle_payment_error($order, $e->getMessage());
        }
    }

	/**
     * Validate and retrieve order object
     *
     * @param int $order_id
     * @return WC_Order
     * @throws Exception
     */
    private function validate_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || !$order->get_id()) {
            throw new Exception(__('شناسه سفارش نادرست است!', 'payping-installment'));
        }
        
        return $order;
    }

	/**
     * Initialize payment session
     *
     * @param int $order_id
     */
    private function initialize_session($order_id) {
        WC()->session->set('payping_installment_order_id', $order_id);
    }

    /**
     * Generate secure payment form for Payping Installment
     * 
     * @param WC_Order $order WooCommerce order object
     * @return string HTML payment form
     */
    private function generate_payment_form(WC_Order $order){
        // Verify order object validity
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        // Generate unique nonce per order
        $nonce_action = 'payping_payment_action_' . $order->get_id();
        $nonce_name = 'payping_installment_nonce_' . $order->get_id();

        // Get URLs
        $checkout_url = wc_get_checkout_url();
        $form_action = esc_url(wc_get_checkout_url());
        
        // Build form HTML with secure output
        $form_html = sprintf(
            '<form method="POST" class="payping-installment-checkout-form" id="payping-installment-form-%1$s" action="%2$s" aria-label="%3$s">',
            $order->get_id(),
            $form_action,
            esc_attr__('فرم پرداخت پی پینگ', 'payping-installment')
        );
        
        $form_html .= sprintf(
            '<input type="hidden" name="order_id" value="%s">',
            esc_attr($order->get_id())
        );
        
        $form_html .= wp_nonce_field(
            $nonce_action,
            $nonce_name,
            true,
            false
        );
        
        $form_html .= sprintf(
            '<div class="payping-button-group">'.
            '<button type="submit" name="payping_submit" class="button alt payping-submit-button" id="payping-submit-%1$s">%2$s</button>'.
            '<a class="button cancel payping-cancel-button" href="%3$s" role="button">%4$s</a>'.
            '</div>',
            $order->get_id(),
            esc_html__('پرداخت', 'payping-installment'),
            esc_url($checkout_url),
            esc_html__('بازگشت', 'payping-installment')
        );
        
        $form_html .= '</form>';

        /**
         * Filter final form HTML output
         * 
         * @param string $form_html Generated form HTML
         * @param int $order_id Order ID
         */
        return apply_filters(
            'payping_payment_form_output', 
            $form_html, 
            $order->get_id()
        );
    }

	/**
     * Process payment request to PayPing API
     *
     * @param WC_Order $order
     * @return array|WP_Error
     */
    private function process_payment_request(WC_Order $order){

        // Verify nonce
        $nonce_action = 'payping_payment_action_' . $order->get_id();
        $nonce_name = 'payping_installment_nonce_' . $order->get_id();

        /*if(! wp_verify_nonce($nonce_name, $nonce_action)){
            if ( ! wc_add_notice( __('درخواست نامعتبر شناسایی شد.', 'payping-installment'), 'error') ) {
                wc_add_notice( __('درخواست نامعتبر شناسایی شد.', 'payping-installment'), 'error');
            }
            return;
        }*/

        $request_data = $this->prepare_request_data($order);
        $api_url = $this->build_api_url('/bnpl/merchant/order/create');
        
        return wp_remote_post($api_url, $this->build_request_args($request_data));
    }

	/**
     * Prepare payment request data
     *
     * @param WC_Order $order
     * @return array
     */
    private function prepare_request_data(WC_Order $order) {
        $amount = $this->calculate_order_amount($order);
        $description = $this->generate_order_description($order);
        
        return [
            'amount'      => $amount,
            'description' => sanitize_text_field($description),
            'callbackUrl' => $this->build_callback_url($order),
            'cancelUrl'   => $this->build_cancel_url($order),
            'mobile'      => $this->validate_customer_mobile($order),
            'refId'       => (string)$order->get_id()
        ];
    }

	/**
     * Build API request arguments
     *
     * @param array $data
     * @return array
     */
    private function build_request_args(array $data) {
        return [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->paypingToken,
                'Content-Type' => 'application/json',
                'X-Platform' => 'woocommerce-installment',
                'X-Platform-Version' => '0.0.1'
            ],
            'body' => wp_json_encode($data),
            'sslverify' => true,
            'httpversion' => '1.1'
        ];
    }

	/**
	 * Build the callback URL for the given WooCommerce order.
	 *
	 * The callback URL includes the order ID as a query parameter and points to the PayPing Installment API endpoint.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string The generated callback URL.
	 */
	public function build_callback_url($order) {
		// Get order ID
		$order_id = $order->get_id();

		// Build callback URL using WooCommerce API request URL and add order ID as query parameter
		$CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('payping_installment'));

		return $CallbackUrl;
	}

	/**
	 * Build the cancel URL for the given WooCommerce order.
	 *
	 * The cancel URL is based on the callback URL with an additional query parameter indicating cancellation status.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string The generated cancel URL.
	 */
	public function build_cancel_url($order) {
		// Get the base callback URL
		$CallbackUrl = $this->build_callback_url($order);

		// Add cancellation status to the URL
		$CancelUrl = add_query_arg('payment_status', 'canceled', $CallbackUrl);

		return $CancelUrl;
	}

	/**
	 * Validate and sanitize customer's mobile number for the given order.
	 *
	 * This method retrieves the raw mobile number from the order, sanitizes it,
	 * and handles any exception by displaying an error notice in WooCommerce.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string|null The sanitized mobile number if valid, otherwise null.
	 */
	public function validate_customer_mobile($order) {
		// Get raw mobile number from order
		$raw_mobile = $this->get_customer_mobile($order);
		try {
			// Try to sanitize the mobile number
			$mobile = $this->sanitize_mobile_number($raw_mobile);
		} catch (Exception $e) {
			// On error, add notice and stop execution
            if( ! wc_add_notice($e->getMessage(), 'error') ){
                wc_add_notice($e->getMessage(), 'error');
            }
			return null;
		}
		return $mobile;
	}

	/**
     * Handle API response and redirect
     *
     * @param WC_Order $order
     * @param array|WP_Error $response
     * @throws Exception
     */
    private function handle_api_response(WC_Order $order, $response) {
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception($this->parse_error_response($status_code, $body));
        }

        $this->finalize_payment($order, $body);
    }

	/**
     * Finalize successful payment
     *
     * @param WC_Order $order
     * @param array $response_data
     */
    private function finalize_payment(WC_Order $order, array $response_data) {
        $order->update_meta_data('_payping_tracking_code', 
            sanitize_text_field($response_data['orderTrackingCode']));
        
        $order->add_order_note(
            sprintf(__('ساخت پرداخت موفق. شناسه رهگیری: %s', 'payping-installment'), 
                $response_data['orderTrackingCode'])
        );
        
        $order->save();
        
        wp_redirect(esc_url_raw($response_data['redirectUrl']));
        exit;
    }

	/**
     * Handle payment errors
     *
     * @param WC_Order|null $order
     * @param string $error_message
     */
    private function handle_payment_error(?WC_Order $order, string $error_message) {
        if ($order) {
            $order->update_meta_data('_payment_error', sanitize_text_field($error_message));
            $order->add_order_note(
                sprintf(__('پرداخت ناموفق: %s', 'payping-installment'), $error_message)
            );
            $order->save();
        }
        
        wc_add_notice(
            __('پردازش پرداخت ناموفق بود. لطفا دوباره تلاش کنید.', 'payping-installment') . 
            '<br>' . esc_html($error_message), 
            'error'
        );
    }

	/**
	 * Generate a custom description for the given WooCommerce order.
	 *
	 * The description includes order ID, customer's full name, and a list of ordered products with quantity.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string The formatted order description string.
	 */
	public function generate_order_description($order) {
		// Get order ID
		$order_id = $order->get_id();

		// Get paymenter's name using existing method
		$Paymenter = $this->get_paymenter_name($order);

		// Prepare product list with quantities
		$products = [];
		$order_items = $order->get_items();

		foreach ($order_items as $item) {
			// Get product name and quantity
			$name = $item->get_name();
			$qty  = $item->get_quantity();

			// Format: Product Name (Qty)
			$products[] = $name . ' (' . $qty . ')';
		}

		// Join product list with separator
		$products_string = implode(' - ', $products);

		// Build final description
		$Description = 'خرید اقساطی سفارش: ' . $order_id . ' | خریدار : ' . $Paymenter . ' | محصولات : ' . $products_string;

		return $Description;
	}

	/**
	 * Calculate and validate order amount for PayPing gateway
	 * 
	 * @param WC_Order $order
	 * @return int
	 * @throws Exception
	 */
	private function calculate_order_amount(WC_Order $order) {
		// Get base order amount in original currency
		$amount = (float) $order->get_total();
		$currency = get_woocommerce_currency();
		
		// Apply filters before currency conversion
		$amount = apply_filters(
			'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency',
			$amount,
			$currency
		);

		// Convert to IRR if needed
		$amount = $this->payping_check_currency($amount, $currency);
		
		// Apply post-conversion filters
		$amount = apply_filters(
			'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency',
			$amount,
			$currency
		);
		
		$amount = apply_filters(
			'woocommerce_order_amount_total_IRANIAN_gateways_irt',
			$amount,
			$currency
		);
		
		$amount = apply_filters(
			'woocommerce_order_amount_total_payping_gateway',
			$amount,
			$currency
		);

		// Final validation
		if (!is_numeric($amount) || $amount <= 0) {
			throw new Exception(__('مبلغ پرداخت با خطا مواجه شد', 'payping-installment'));
		}

		return (int) round($amount);
	}

	public function Return_from_payping_Gateway(){
		try {
            // 1. Validate and sanitize input
            $input = $this->validate_input();
            
            // 2. Decode and validate data
            $data = $this->parse_data($input['data']);
            // 3. Retrieve order
			$order = wc_get_order($data['clientRefId']);
            
            // 4. Handle payment status
            if($input['payment_status'] === 'canceled') {
                $this->handle_cancellation($order);
            } else {
                $this->handle_payment_status($order, $input, $data);
            }

        } catch (Exception $e) {
            $this->log_error($e);
            wp_redirect(wc_get_checkout_url());
            exit;
        }
	}
    
	/**
	 * Retrieve formatted payer name with fallback logic to ensure non-empty value
	 *
	 * @since 1.0.0
	 * @link https://developer.wordpress.org/reference/functions/get_userdata/
	 * 
	 * @param WC_Order $order WooCommerce order instance
	 * @param string $fallback_text Fallback text pattern (uses sprintf() with order ID)
	 * @return string Sanitized payer name
	 * @throws InvalidArgumentException If invalid order is passed
	 * 
	 * @example 
	 * $order = wc_get_order(123);
	 * echo get_paymenter_name($order); // "Mahdi Sarani"
	 * 
	 * @example
	 * // For empty names:
	 * echo get_paymenter_name($order); // "Customer#123"
	 */
	public function get_paymenter_name(WC_Order $order, string $fallback_text = 'Customer#%s'): string {
		// Validate order instance
		if (!$order instanceof WC_Order || !$order->get_id()) {
			throw new InvalidArgumentException(__('Invalid WooCommerce order instance', 'text-domain'));
		}

		// Get base name components
		$first_name = trim($order->get_billing_first_name());
		$last_name = trim($order->get_billing_last_name());
		$combined_name = implode(' ', array_filter([$first_name, $last_name]));

		// Return combined name if not empty
		if (!empty($combined_name)) {
			return sanitize_text_field($combined_name);
		}

		// Fallback 1: User display name
		if ($user_id = $order->get_user_id()) {
			$user = get_userdata($user_id);
			if (!empty($user->display_name)) {
				return sanitize_text_field($user->display_name);
			}
			if (!empty($user->user_login)) {
				return sanitize_text_field($user->user_login);
			}
		}

		// Fallback 2: Billing email
		if ($email = trim($order->get_billing_email())) {
			return sanitize_email($email);
		}

		// Final fallback: Order ID pattern
		return sanitize_text_field(
			sprintf($fallback_text, $order->get_id())
		);
	}

    public function get_customer_mobile($order): string {
        
        if ($this->shouldUseDigitsMobile()) {
            return digits_get_mobile(get_current_user_id()) ?: '';
        }
    
        if ($this->isCustomFieldActive()) {
            return $this->getCustomMobileNumber($order);
        }
    
        if ($this->isForceFieldActive()) {
            return $this->getForcedMobileNumber($order);
        }
        
        return '';
    }
    
    private function shouldUseDigitsMobile(): bool {
        return $this->mobile_number_type && function_exists('digits_get_mobile');
    }
    
    private function isCustomFieldActive(): bool {
        return $this->mobile_number_type === 'custom_field';
    }
    
    private function getCustomMobileNumber($order): string {
        return (string) $order->get_meta(get_option('payping_custom_mobile_field'));
    }
    
    private function isForceFieldActive(): bool {
        return $this->mobile_number_type == 'force_add_field';
    }
    
    private function getForcedMobileNumber($order): string {
        $fields = Package::container()->get(CheckoutFields::class)->get_all_fields_from_object($order, 'billing');
        $mobile = $fields['payping_installment/mobile_number'] ?? $order->get_meta('_mobile_number');
        return sanitize_text_field($mobile);
    }

	public function payping_check_currency( $Amount, $currency ){
		if( strtolower( $currency ) == strtolower('IRT') || strtolower( $currency ) == strtolower('TOMAN') || strtolower( $currency ) == strtolower('Iran TOMAN') || strtolower( $currency ) == strtolower('Iranian TOMAN') || strtolower( $currency ) == strtolower('Iran-TOMAN') || strtolower( $currency ) == strtolower('Iranian-TOMAN') || strtolower( $currency ) == strtolower('Iran_TOMAN') || strtolower( $currency ) == strtolower('Iranian_TOMAN') || strtolower( $currency ) == strtolower('تومان') || strtolower( $currency ) == strtolower('تومان ایران') ){
			$Amount = $Amount * 1;
		}elseif(strtolower($currency) == strtolower('IRHT')){
			$Amount = $Amount * 1000;
		}elseif( strtolower( $currency ) == strtolower('IRHR') ){
			$Amount = $Amount * 100;					
		}elseif( strtolower( $currency ) == strtolower('IRR') ){
			$Amount = $Amount / 10;
		}
		return  $Amount;                      
	}

	public function sanitize_mobile_number($mobile) { //var_dump($mobile); die();
		// preg mobile number
		$mobile = preg_replace('/[^0-9]/', '', $mobile);
		
		// remove prefix
		if (substr($mobile, 0, 2) == '98') {
			$mobile = substr($mobile, 2);
		} elseif (substr($mobile, 0, 3) == '098') {
			$mobile = substr($mobile, 1);
		}
		
		// add 0 to first mobile
		if (substr($mobile, 0, 1) == '9' && strlen($mobile) == 10) {
			$mobile = '0' . $mobile;
		}
		
		// check final mobile
		if (!preg_match('/^09\d{9}$/', $mobile)) {
			throw new Exception('شماره همراه '. $mobile . ' نامعتبر است.');
		}
		
		return $mobile;
	}

	/**
     * Validate and sanitize POST input
     */
    private function validate_input() {
        $required_params = [
            'status'    => FILTER_VALIDATE_INT,
            'errorCode' => FILTER_VALIDATE_INT,
            'data'      => FILTER_UNSAFE_RAW,
            'payment_status' => FILTER_SANITIZE_STRING
        ];

        $input = filter_input_array(INPUT_POST, $required_params);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($input['data'])) {
            throw new Exception('ساختار نادرست داده های بازگشت.');
        }

        return $input;
    }
    
    /**
     * Parse and validate data field
     */
    private function parse_data($json_data) {
        return json_decode($json_data, true);
    }	
    
	/**
     * Verify payment with PayPing API
     */
    private function verify_payment($order, $data) {
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->paypingToken,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'amount' => (int)$data['amount'],
                'paymentRefId' => (int)$data['paymentRefId'],
                'paymentCode' => sanitize_text_field($data['paymentCode'])
            ])
        ];
        
        $verify_url = $this->build_api_url('/bnpl/consumer/order/prepay/verify');
        
        $response = wp_remote_post($verify_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('خطای تایید پرداخت: ' . $response->get_error_message());
        }
        $status_code = wp_remote_retrieve_response_code($response);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code !== 200) {
            throw new Exception('تایدد پرداخت ناموفق: ' . $body['message']);
        }

        return $body;
    }
	
	/**
     * Handle payment status
     */
    private function handle_payment_status($order, $input, $data) {
        switch ($input['status']) {
            case 1:
                $this->handle_success($order, $data);
                break;
                
            case 0:
                $this->handle_failure($order, $input['errorCode']);
                break;
                
            default:
                throw new Exception('وضعیت پرداخت نامشخص است!');
        }
    }
    
    /**
     * Handle successful payment
     */
    private function handle_success($order, $data) {
       
        // Verify payment with PayPing
        $verification = $this->verify_payment($order, $data);

        // Update order meta
        $order->update_meta_data('_payping_payment_ref_id', $data['paymentRefId']);
        $order->update_meta_data('_payping_card_number', $verification['cardNumber']);
        
        // Complete payment
        $order->payment_complete($data['paymentRefId']);
        
        // Add order note
        $order->add_order_note(sprintf(
            __('پرداخت موفق - شماره رهگیری: %s - شماره کارت: %s', 'payping-installment'),
            $data['paymentRefId'],
            $verification['cardNumber']
        ));
        
        // Redirect to thank you page
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    }
	
	/**
     * Handle payment failure
     */
    private function handle_failure($order, $error_code) {
        $order->update_status('failed', sprintf(
            __('خطای پرداخت - کد خطا: %d', 'payping-installment'),
            $error_code
        ));
        
        if ( ! wc_add_notice( __('پرداخت ناموفق بود. لطفا مجددا تلاش کنید.', 'payping-installment'), 'error' ) ) {
            wc_add_notice( __('پرداخت ناموفق بود. لطفا مجددا تلاش کنید.', 'payping-installment'), 'error' );
        }
        
        
        wp_redirect($order->get_checkout_payment_url());
        exit;
    }
    
	/**
     * Handle payment cancellation
     */
    private function handle_cancellation($order) {
        $order->update_status('cancelled', __('انصراف کاربر از فرایند پرداخت.', 'payping-installment'));
        
        if ( ! wc_add_notice( __('انصراف در فرایند پرداخت.', 'payping-installment'), 'notice' ) ) {
            wc_add_notice( __('انصراف در فرایند پرداخت.', 'payping-installment'), 'notice' );
        }
        
        wp_redirect(wc_get_cart_url());
        exit;
    }

    /**
     * Parse API error response into human-readable message
     * 
     * @param int $status_code HTTP status code
     * @param mixed $response_body API response body
     * @return string Formatted error message
     */
    private function parse_error_response($status_code, $response_body) {
        // Initialize default message
        $default_message = sprintf(
            __('خطای وب سرویس پرداخت (کد %d)', 'payping-installment'),
            $status_code
        );
        
        // Handle empty response
        if (empty($response_body)) {
            return $default_message;
        }
        
        // Try to extract API-specific error
        $api_error = '';
        if (is_array($response_body)) {
            $api_error = $response_body['message'] ?? '';
            $api_code = $response_body['code'] ?? '';
        } elseif (is_string($response_body)) {
            $decoded = json_decode($response_body, true);
            $api_error = $decoded['message'] ?? '';
            $api_code = $decoded['code'] ?? '';
        }
        
        // Build error components
        $components = [];
        if ($api_code) {
            $components[] = sprintf(
                __('کد خطا: %s', 'payping-installment'),
                sanitize_text_field($api_code)
            );
        }
        
        if ($api_error) {
            $components[] = sanitize_text_field($api_error);
        }
        
        // Add HTTP status interpretation
        $http_status_msg = $this->get_http_status_message($status_code);
        if ($http_status_msg) {
            $components[] = sprintf(
                __('وضعیت درخواست: %s', 'payping-installment'),
                $http_status_msg
            );
        }
        
        // Fallback to default if no components
        if (empty($components)) {
            return $default_message;
        }
        
        return implode(' | ', $components);
    }

    /**
     * Get human-readable HTTP status message
     * 
     * @param int $status_code
     * @return string
     */
    private function get_http_status_message($status_code) {
        $status_messages = [
            200 => __('عملیات با موفقیت انجام شد', 'payping-installment'),
            400 => __('مشکلی در ارسال درخواست وجود دارد', 'payping-installment'),
            401 => __('عدم دسترسی - توکن API نامعتبر', 'payping-installment'),
            403 => __('دسترسی غیر مجاز', 'payping-installment'),
            404 => __('آیتم درخواستی مورد نظر موجود نمی باشد', 'payping-installment'),
            429 => __('درخواست بیش از حد مجاز - محدودیت استفاده از API', 'payping-installment'),
            500 => __('مشکلی در سرور رخ داده است', 'payping-installment'),
            502 => __('Gateway نامعتبر', 'payping-installment'),
            503 => __('سرور در حال حاضر قادر به پاسخگویی نمی باشد', 'payping-installment'),
            504 => __('Gateway Timeout', 'payping-installment'),
        ];
        
        return $status_messages[$status_code] ?? __('Unknown HTTP error', 'payping-installment');
    }

    /**
     * register mobile number field in block woo
     */
    public function register_mobile_number_checkout_field() {
        woocommerce_register_additional_checkout_field(
            array(
                'id'            => 'payping_installment/mobile_number',
                'label'         => __('شماره همراه', 'payping-installment'),
                'optionalLabel' => __('شماره همراه با 09 شروع میشود.', 'payping-installment'),
                'location'      => 'address',
                'required'      => true,
                'attributes'    => array(
                    'autocomplete'     => 'tel',
                    'aria-label'       => __('شماره همراه', 'payping-installment'),
                    'pattern'          => '09[0-9]{9}', // Only allows Iranian mobile numbers
                    'title'            => __('شماره همراه باید با 09 شروع شده و 11 رقم باشد.', 'payping-installment'),
                    'inputmode'        => 'numeric'
                ),
            )
        );
    }
    
    public function validate_mobile_number_field($fields, $errors) {
        // Check if mobile number is set
        if (empty($fields['mobile_number'])) {
            if (!$errors->get_error_data('mobile_number_error')) {
                $errors->add(
                    'mobile_number_error',
                    __('لطفاً شماره همراه خود را وارد کنید.', 'payping-installment')
                );
            }
            return;
        }        
    
        $mobile_number = $fields['mobile_number'];
    
        // Validate Iranian mobile format: should start with 09 and be 11 digits
        if (!preg_match('/^09[0-9]{9}$/', $mobile_number)) {
            if (!$errors->get_error_messages('mobile_number_invalid')) {
                $errors->add(
                    'mobile_number_invalid',
                    __('شماره همراه وارد شده معتبر نیست. لطفاً شماره‌ای با فرمت 09123456789 وارد کنید.', 'payping-installment'),
                    'mobile_format_invalid'
                );
            }
        }
        
    }
    
    public function save_mobile_number_to_order_meta($order, $data) {
        if (!empty($data['mobile_number'])) {
            // Save to order meta
            $order->update_meta_data('_mobile_number', sanitize_text_field($data['mobile_number']));
        }
    }

    /**
     * register mobile number field in shortcode woo
     */
    public function add_mobile_checkout_field($fields) {
        $fields['billing']['mobile_number'] = array(
            'label'        => __('شماره همراه', 'payping-installment'),
            'placeholder'  => __('مثال: 09123456789', 'payping-installment'),
            'required'     => true,
            'class'        => array('form-row-wide'),
            'clear'        => true,
            'autocomplete' => 'tel',
            'inputmode'    => 'numeric',
            'priority'     => 25,
            'validate'     => array('custom_mobile_number_validation'),
            'custom_attributes' => array(
                'pattern' => '09[0-9]{9}',
                'title'   => __('شماره همراه باید با 09 شروع شده و 11 رقم باشد.', 'payping-installment'),
            ),
        );
    
        return $fields;
    }    

    public function validate_mobile_field(){
        if(empty($_POST['mobile_number'])){
            if ( ! wc_add_notice(__('لطفاً شماره همراه خود را وارد کنید.', 'payping-installment'), 'error') ) {
                wc_add_notice(__('لطفاً شماره همراه خود را وارد کنید.', 'payping-installment'), 'error');
            }
        } elseif (!preg_match('/^09[0-9]{9}$/', $_POST['mobile_number'])) {
            if ( ! wc_has_notice(__('شماره همراه معتبر نیست. لطفاً شماره‌ای 11 رقمی با شروع 09 وارد کنید.', 'payping-installment'), 'error') ) {
                wc_add_notice(__('شماره همراه معتبر نیست. لطفاً شماره‌ای 11 رقمی با شروع 09 وارد کنید.', 'payping-installment'), 'error');
            }
        }
    }    

    public function save_mobile_meta($order_id) {
        $order = wc_get_order($order_id);
        
        if (!empty($_POST['mobile_number'])) {
            // Save to order meta
            $order->update_meta_data('_mobile_number', sanitize_text_field($_POST['mobile_number']));
            $order->save();
        }
    }
    
    // Display mobile number in admin order panel
    public function display_mobile_in_admin_order($order) {
        $mobile = $order->get_meta('_mobile_number');
        if ($mobile && !did_action('display_mobile_rendered')) {
            echo '<p class="mobile-number"><strong>' 
                 . __('شماره همراه', 'payping-installment') 
                 . ':</strong> ' 
                 . esc_html($mobile) 
                 . '</p>';
            do_action('display_mobile_rendered');
        }
    }

    // Display in frontend order details
    public function display_mobile_in_order_details($order) {
        // Check if mobile exists in multiple meta keys
        $mobile = $order->get_meta('_mobile_number') ?: $order->get_meta('_billing_mobile');
        
        if ($mobile) {
            echo '<section class="woocommerce-customer-details--mobile">',
                '<h2>' . esc_html__('شماره همراه', 'payping-installment') . '</h2>',
                '<address>' . esc_html($mobile) . '</address>',
                '</section>';
        }
    }

    /**
    * Check if current checkout page uses block or shortcode
    * @return string 'block' | 'shortcode' | 'none'
    */
    public function detect_checkout_type() {
       // Check if WooCommerce is active
       if (!class_exists('WooCommerce')) {
           return 'none';
       }
   
       $post = get_post(get_option('woocommerce_checkout_page_id'));

       // Check for Checkout Block
       $is_block = has_block('woocommerce/checkout', $post);
   
       // Check for Classic Shortcode
       $is_shortcode = (
           is_checkout() && 
           !$is_block && 
           has_shortcode($post->post_content, 'woocommerce_checkout')
       );
   
       return $is_block ? 'block' : ($is_shortcode ? 'shortcode' : 'none');
   }
    
   	/**
     * Error logging
     */
    private function log_error($exception){
        $logger = wc_get_logger();
        $logger->error($exception->getMessage(), [
            'source' => 'payping-installment',
            'trace' => $exception->getTraceAsString()
        ]);
    }
}