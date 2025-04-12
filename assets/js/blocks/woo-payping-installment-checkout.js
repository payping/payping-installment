/**
 * Register PayPing Installment payment method for WooCommerce Blocks (Gutenberg).
 * Includes logo support from plugin settings.
 */

// Destructure required utilities from global WC Blocks Registry and WordPress libraries
const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { createElement } = wp.element;
const { __ } = wp.i18n;

/**
 * React component for payment method content
 * @returns {JSX.Element} Payment method UI with logo and description
 */
const PaypingContent = () => {
  return createElement(
    'div',
    { 
      className: 'payping-installment-content',
      'data-testid': 'payping-installment-container'
    },
    // Payment Description
    createElement(
      'div',
      { className: 'payping-installment-description' },
      paypingInstallmentSettings.description
    )
  );
};

// Register payment method with WooCommerce Blocks
registerPaymentMethod({
  name: 'payping_installment', // Unique payment method ID
  label: createElement(
    'div',
    { className: 'payping-installment-label-wrapper' },
    // Logo in payment method list
    createElement('img', {
      src: paypingInstallmentSettings.icon,
      alt: paypingInstallmentSettings.title,
      style: { // Inline styles
        display: 'inline-block',
        margin: '0 0 0 10px',
        verticalAlign: 'middle',
        maxWidth: '100px'
      },
      className: 'payping-installment-icon'
    }),
    paypingInstallmentSettings.title
  ),
  ariaLabel: paypingInstallmentSettings.ariaLabel,
  content: createElement(PaypingContent),
  edit: null,
  canMakePayment: () => true,
  paymentMethodId: 'payping_installment'
});