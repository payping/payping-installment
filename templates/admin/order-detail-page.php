<?php
/**
 * Order details page template
 *
 * @package PayPingInstallment
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verify tracking code
$tracking_code = isset( $_GET['trackingCode'] ) ? sanitize_text_field( wp_unslash( $_GET['trackingCode'] ) ) : '';

if ( empty( $tracking_code ) ) {
	wp_die( esc_html__( 'کد سفارش نامعتبر است!', 'payping-instalment' ) );
}

// Check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'دسترسی لازم برای مشاهده این صفحه را ندارید!', 'payping-instalment' ) );
}

// Fetch order details
$order_detail = new \PayPingInstallment\Admin\OrderDetail();
$data         = $order_detail->fetch_order_details( $tracking_code );

// Verify API response
if ( is_wp_error( $data ) ) {
	wp_die( esc_html( $data->get_error_message() ) );
}
?>

<div class="wrap pp-instalment-order-detail">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( sprintf( __( 'جزئیات سفارش: %s', 'payping-instalment' ), $data['trackingCode'] )); ?>
	</h1>
	
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=payping-installment-orders-list' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'بازگشت به لیست سفارش‌ها', 'payping-instalment' ); ?>
	</a>

	<div class="pp-instalment-columns">
		<!-- Left Column -->
		<div class="pp-instalment-column-main">
			<!-- Customer Information -->
			<div class="pp-instalment-card">
				<h2><?php esc_html_e( 'مشخصات مشتری', 'payping-instalment' ); ?></h2>
				<div class="customer-info">
					<p><?php echo esc_html( sprintf( 
						__( 'نام کامل: %s', 'payping-instalment' ),
						$data['consumerInfo']['firstName'] . ' ' . $data['consumerInfo']['lastName']
					) ); ?></p>
					<p><?php echo esc_html( sprintf(
						__( 'کد ملی: %s', 'payping-instalment' ),
						$data['consumerInfo']['nationalCode']
					) ); ?></p>
					<p><span dir="ltr"><?php echo esc_html( sprintf(
						__( 'تاریخ تولد: %s', 'payping-instalment' ),
						date_i18n( get_option('date_format'), strtotime( $data['consumerInfo']['birthDate'] ))
					) ); ?></span></p>
					<p><?php echo esc_html( sprintf(
						__( 'شماره همراه: %s', 'payping-instalment' ),
						$data['consumerInfo']['mobileNumber']
					) ); ?></p>
				</div>
			</div>

			<!-- Installments Table -->
			<div class="pp-instalment-card">
				<h2><?php esc_html_e( 'تاریخچه بازپرداخت', 'payping-instalment' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'اقساط #', 'payping-instalment' ); ?></th>
							<th><?php esc_html_e( 'مبلغ (تومان)', 'payping-instalment' ); ?></th>
							<th><?php esc_html_e( 'تاریخ', 'payping-instalment' ); ?></th>
							<th><?php esc_html_e( 'وضعیت', 'payping-instalment' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['installmentDetails'] as $index => $installment ) : ?>
							<tr>
								<td><?php echo (int) ( $index + 1 ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $installment['finalAmount'] ) ); ?></td>
								<td>
									<?php echo $installment['dueDate'] ? 
										esc_html( date_i18n( get_option( 'date_format' ), strtotime( $installment['dueDate'] ) )) : 
										'—'; ?>
								</td>
								<td>
									<span class="status-badge status-<?php echo esc_attr( $installment['status'] ); ?>">
										<?php echo esc_html( $order_detail->get_status_label( $installment['status'] ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Right Column -->
		<div class="pp-instalment-column-side">
			<!-- Payment Summary -->
			<div class="pp-instalment-card">
				<h2><?php esc_html_e( 'خلاصه پرداخت', 'payping-instalment' ); ?></h2>
				<div class="payment-summary">
					<p><?php echo esc_html( sprintf(
						__( 'مبلغ سبد خرید: %s تومان', 'payping-instalment' ),
						number_format_i18n( $data['totalBasketAmount'] )
					) ); ?></p>
					<p><?php echo esc_html( sprintf(
						__( 'پیش پرداخت: %s تومان', 'payping-instalment' ),
						number_format_i18n( $data['prepayment'] )
					) ); ?></p>
					<p><?php echo esc_html( sprintf(
						__( 'اعتبار ارائه شده: %s تومان', 'payping-instalment' ),
						number_format_i18n( $data['creditAmount'] )
					) ); ?></p>
					<p><?php echo esc_html( sprintf(
						__( 'جمع قابل پرداخت: %s تومان', 'payping-instalment' ),
						number_format_i18n( $data['totalPayment'] )
					) ); ?></p>
					<hr>
					<p><?php echo esc_html( sprintf(
						__( 'رتبه اعتباری (در زمان خرید): %s', 'payping-instalment' ),
						$data['creditScoreDescription']
					) ); ?></p>
					<p><?php echo esc_html( sprintf(
						__( 'وضعیت انصراف: %s', 'payping-instalment' ),
						$data['isCancelable'] ? __( 'فعال', 'payping-instalment' ) : __( 'غیرفعال', 'payping-instalment' )
					) ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>