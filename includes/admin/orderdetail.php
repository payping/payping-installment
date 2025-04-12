<?php
namespace PayPingInstallment\Admin;
use PayPingInstallment\Cache\CacheHandler;

class OrderDetail {

    public function fetch_order_details($tracking_code) {
        $token = get_option('payping_access_token');

        $cache_key = 'payping_order_' . $tracking_code;
        $cached_data = CacheHandler::get($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $response = wp_remote_get("https://api.payping.ir/v1/bnpl/merchant/order/detail?trackingCode=$tracking_code", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ],
            'timeout' => 15
        ]);

        if(is_wp_error($response)) {
            return new \WP_Error('api_error', 'خطا در ارتباط با سرور');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if(isset($body['error'])) {
            return new \WP_Error('api_error', $body['message'] ?? 'خطای ناشناخته');
        }
        CacheHandler::set($cache_key, $body, 300);

        return $body;
    }

    public function get_status_label($status) {
        $labels = [
            1 => 'در انتظار پرداخت',
            4 => 'پرداخت شده',
            // سایر وضعیت‌ها...
        ];
        return $labels[$status] ?? 'نامشخص';
    }
}