<?php
namespace PayPingInstallment\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OrderList extends \WP_List_Table {
    private $per_page = 10;
    private $api_url = 'https://api.payping.ir/v1/bnpl/merchant/order/list';

    public function __construct() {
        parent::__construct([
            'singular' => 'سفارش',
            'plural'   => 'سفارشات',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            //'orderTrackingCode' => 'کد رهگیری',
            'createdDate'      => 'تاریخ ایجاد',
            'creditAmount'     => 'مبلغ اعتبار',
            'consumer'         => 'مشتری',
            //'mobile'           => 'موبایل',
            'installments'     => 'اقساط',
            //'status'           => 'وضعیت',
            'source'           => 'منبع',
            'actions'          => 'عملیات'
        ];
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '—';
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], []];
        $current_page = $this->get_pagenum();
        $response = $this->fetch_orders($current_page);
        
        $this->items = $response['orders'] ?? [];
        $total_items = $response['total'] ?? 0;
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $this->per_page,
            'total_pages' => ceil($total_items / $this->per_page)
        ]);
    }

    private function fetch_orders($page) {
        $token = get_option('payping_access_token');
        if(empty($token)) return [];
        
        $response = wp_remote_get($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ],
            'body' => [
                'page'  => $page,
                'limit' => $this->per_page
            ],
            'timeout' => 15
        ]);
        
        return !is_wp_error($response) 
            ? json_decode(wp_remote_retrieve_body($response), true) 
            : [];
    }

    /* Colums */
    public function column_createdDate($item) {
        return date_i18n('Y/m/d', strtotime($item['createdDate']));
    }

    public function column_creditAmount($item) {
        return number_format($item['creditAmount']) . ' تومان';
    }

    public function column_source($item) {
        $sources = [
            'wc' => ['ووکامرس', 'source-wc'],
            'gf' => ['گرویتی فرم', 'source-gf'],
            'edd' => ['EDD', 'source-edd']
        ];

        // woocommerce
        $order_id = $this->get_order_id_by_transaction_id($item['orderTrackingCode']);

        if(isset($order_id)){
            $item['source'] = 'wc';
        }
        $source = $item['source'] ?? 'other';
        
        $data = $sources[$source] ?? ['سایر', 'source-other'];

        return sprintf(
            '<span class="%s">%s</span> | <a href="%s" target="_blank">مشاهده</a>',
            esc_attr($data[1]),
            esc_html($data[0]),
            admin_url("post.php?post={$order_id}&action=edit")
        );
    }

    public function column_installments($item) {
        return sprintf(
            '%d قسط (%d پرداخت شده)',
            $item['totalInstallmentCount'],
            $item['paidoffInstallmentCount']
        );
    }

    public function column_status($item) {
        $status = $item['isCancelable'] ? 'فعال' : 'غیرفعال';
        $class = $item['isCancelable'] ? 'success' : 'warning';
        
        return sprintf(
            '<span class="payping-status payping-status-%s">%s</span>',
            esc_attr($class),
            esc_html($status)
        );
    }

    public function column_actions($item) {
        $links = [];
        if(!empty($item['orderTrackingCode'])) {
            $detail_url = admin_url("admin.php?page=payping-installment-order-detail&trackingCode=" . $item['orderTrackingCode']);
            $links[] = sprintf(
                '<a href="%s" target="_self" rel="noopener">مشاهده جزئیات</a>',
                esc_url($detail_url)
            );
        }
        
        return implode(' | ', $links);
    }

    public function print_styles() {
        echo '
        <style>
            .source-wc { background: #d1e8ff; color: #004c87; }
            .source-gf { background: #ffe9d6; color: #6b3e00; }
            .source-edd { background: #e0f0eb; color: #005a40; }
            .source-other { background: #f0f0f0; color: #444; }
            
            .payping-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 12px;
                font-size: 13px;
            }
            
            .payping-status-success { background: #d1fae5; }
            .payping-status-warning { background: #fef3c7; }
        </style>
        ';
    }

    public function get_order_id_by_transaction_id($transaction_id) {
        global $wpdb;
    
        // Validate and sanitize the transaction_id
        if (empty($transaction_id) || !is_string($transaction_id)) {
            return false;
        }
        $transaction_id = sanitize_text_field($transaction_id);
    
        // Determine if HPOS is enabled
        $is_hpos_enabled = false;
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
            method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            $is_hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
    
        $order_id = null;
    
        if ($is_hpos_enabled) {
            // HPOS mode: Query new orders table first
            $query = $wpdb->prepare(
                "SELECT id 
                 FROM {$wpdb->prefix}wc_orders
                 WHERE transaction_id = %s
                 LIMIT 1",
                $transaction_id
            );
            $order_id = $wpdb->get_var($query);
    
            // If not found, try alternative meta key in wc_orders_meta
            if (is_null($order_id)) {
                $query = $wpdb->prepare(
                    "SELECT order_id 
                     FROM {$wpdb->prefix}wc_orders_meta
                     WHERE meta_key = '_payping_tracking_code' 
                       AND meta_value = %s 
                     LIMIT 1",
                    $transaction_id
                );
                $order_id = $wpdb->get_var($query);
            }
        } else {
            // Legacy mode: Query postmeta for _transaction_id key first
            $query = $wpdb->prepare(
                "SELECT post_id 
                 FROM {$wpdb->prefix}postmeta 
                 WHERE meta_key = '_transaction_id' 
                   AND meta_value = %s 
                 LIMIT 1",
                $transaction_id
            );
            $order_id = $wpdb->get_var($query);
    
            // If not found, try alternative meta key _payping_tracking_code
            if (is_null($order_id)) {
                $query = $wpdb->prepare(
                    "SELECT post_id 
                     FROM {$wpdb->prefix}postmeta
                     WHERE meta_key = '_payping_tracking_code' 
                       AND meta_value = %s 
                     LIMIT 1",
                    $transaction_id
                );
                $order_id = $wpdb->get_var($query);
            }
        }
    
        // Return a valid numeric order id or false
        return ($order_id && is_numeric($order_id)) ? (int)$order_id : false;
    }    

    public function display() {
        $this->print_styles();
        parent::display();
    }
}