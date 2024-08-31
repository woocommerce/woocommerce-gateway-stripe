import StripeMark from 'wcstripe/brand-logos/stripe-mark';
import WooLogo from 'wcstripe/brand-logos/woo-white';

/**
 * StripeAuthDiagram component.
 *
 * @return {JSX.Element} The rendered StripeAuthDiagram component.
 */
const StripeAuthDiagram = () => {
	return (
		<div className="woocommerce-stripe-auth__diagram">
			<WooLogo />
			<div className="woocommerce-stripe-auth__diagram__dotted-line" />
			<StripeMark />
		</div>
	);
};

export default StripeAuthDiagram;
