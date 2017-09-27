<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( WC_ABSPATH . '/includes/admin/reports/class-wc-admin-report.php' );

/**
 * Class WC_Stripe_Reports
 *
 * @extends         WC_Admin_Report
 * @author          Nicolas GEHIN - studio RVOLA
 * @author_uri      https://www.rvola.com
 * @version         1.0.0
 * Created          2017-09-27
 */

class WC_Stripe_Reports extends WC_Admin_Report {

	/**
	 * Color graph
	 */
	const COLOR = '#572ff8';
	/**
	 * post_meta name for Stripe fee
	 */
	const META_NAME_FEE = 'Stripe Fee';

	/**
	 * @var $data
	 */
	private $data;
	/**
	 * @var $report_data
	 */
	private $report_data;

	/**
	 * WC_Stripe_Reports constructor.
	 */
	public function __construct() {

		$this->init();

		add_filter( 'woocommerce_admin_report_data', array( $this, 'queryReport' ), 10, 1 );
		add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'cleanQueryReport' ), 10, 1 );
		add_filter( 'woocommerce_admin_report_chart_data', array( $this, 'addDataInChart' ), 10, 1 );
		add_action( 'admin_print_footer_scripts', array( $this, 'addLegendToChart' ), 10 );
		add_action( 'admin_print_footer_scripts', array( $this, 'reloadGraph' ), 20 );
	}

	/**
	 * Initializes some value required for changes in reports
	 */
	private function init() {

		$ranges = array(
			'year'       => __( 'Year', 'woocommerce' ),
			'last_month' => __( 'Last month', 'woocommerce' ),
			'month'      => __( 'This month', 'woocommerce' ),
			'7day'       => __( 'Last 7 days', 'woocommerce' ),
		);

		$this->chart_colours = array(
			'sales_amount'     => '#b1d4ea',
			'net_sales_amount' => '#3498db',
			'average'          => '#b1d4ea',
			'net_average'      => '#3498db',
			'order_count'      => '#dbe1e3',
			'item_count'       => '#ecf0f1',
			'shipping_amount'  => '#5cc488',
			'coupon_amount'    => '#f1c40f',
			'refund_amount'    => '#e74c3c',
			'stripe_fee'       => self::COLOR,
		);

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
			$current_range = '7day';
		}

		$this->check_current_range_nonce( $current_range );
		$this->calculate_current_range( $current_range );

	}

	/**
	 * Adds a request to the loop to get the total of Stripe fee
	 * @param $report_data
	 *
	 * @return mixed
	 */
	public function queryReport( $report_data ) {

		$this->report_data = $report_data;

		/*search Stripe fee's*/
		$stripe_fee = $this->get_order_report_data( array(
			'data'         => array(
				'%stripe_fee%' => array(
					'type'     => 'meta',
					'function' => 'SUM',
					'name'     => 'total',
				),
				'post_date'    => array(
					'type'     => 'post_data',
					'function' => '',
					'name'     => 'post_date',
				),
			),
			'group_by'     => $this->group_by_query,
			'order_by'     => 'post_date ASC',
			'query_type'   => 'get_results',
			'filter_range' => true,
			'order_types'  => wc_get_order_types( 'sales-reports' ),
			'order_status' => array( 'completed', 'processing', 'on-hold', 'refunded' ),
		) );

		if ( $stripe_fee ) {

			/*Add Fee in Report Data*/
			$this->report_data->stripe_fee = $stripe_fee;

			/*Add Total Fee*/
			$this->report_data->total_stripe_fee = wc_format_decimal( array_sum( wp_list_pluck( $this->report_data->stripe_fee,
				'total' ) ), 2 );

			/*Net Sale - Stripe fee*/
			$this->report_data->net_sales = wc_format_decimal( $this->report_data->total_sales - $this->report_data->total_shipping - max( 0,
					$this->report_data->total_tax ) - max( 0, $this->report_data->total_shipping_tax ) - max( 0,
					$this->report_data->total_stripe_fee ), 2 );

		}

		return $this->report_data;
	}

	/**
	 * Unfortunately, the post_meta that stores the Stripe Fee uses a space.
	 * With this method, the query is modified to be operational.
	 *
	 * @param $query
	 *
	 * @return mixed
	 */
	public function cleanQueryReport( $query ) {

		preg_match( '/%stripe_fee%/', $query['select'], $match );
		if ( $match ) {
			$query['select'] = str_replace( '%stripe_fee%', 'key', $query['select'] );
			$query['join']   = str_replace( 'meta_%stripe_fee%', 'meta_key', $query['join'] );
			$query['join']   = str_replace( "'%stripe_fee%'", "'" . self::META_NAME_FEE . "'", $query['join'] );
		}

		return $query;
	}

	/**
	 * Added the results found in the table to build the graph.
	 * @param $data
	 *
	 * @return mixed
	 */
	public function addDataInChart( $data ) {

		$this->data = $data;

		/*Add Stripe fee in data graph array*/
		$this->data['stripe_fee'] = $this->prepare_chart_data( $this->report_data->stripe_fee, 'post_date', 'total',
			$this->chart_interval, $this->start_date, $this->chart_groupby );

		/*Net Sale - Stripe fee*/
		foreach ( $this->data['net_order_amounts'] as $order_amount_key => $order_amount_value ) {
			$this->data['net_order_amounts'][ $order_amount_key ][1] -= $this->data['stripe_fee'][ $order_amount_key ][1];
		}

		return $this->data;
	}

	/**
	 * Injects the chart 'naughty' to add the Stripe fee. No hook exists (Automattic if you hear me)
	 */
	public function reloadGraph() {

		global $wp_locale;

		// Encode in json format
		$chart_data = json_encode( array(
			'order_counts'        => array_values( $this->data['order_counts'] ),
			'order_item_counts'   => array_values( $this->data['order_item_counts'] ),
			'order_amounts'       => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['order_amounts'] ) ),
			'gross_order_amounts' => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['gross_order_amounts'] ) ),
			'net_order_amounts'   => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['net_order_amounts'] ) ),
			'shipping_amounts'    => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['shipping_amounts'] ) ),
			'coupon_amounts'      => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['coupon_amounts'] ) ),
			'refund_amounts'      => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['refund_amounts'] ) ),
			'stripe_fee'          => array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['stripe_fee'] ) ),
		) );

		?>
		<script type="text/javascript">

			var main_chart;

			jQuery(function () {
				var order_data = jQuery.parseJSON('<?php echo $chart_data; ?>');
				var drawGraph = function (highlight) {

					var series = [
						{
							label: "<?php echo esc_js( __( 'Number of items sold', 'woocommerce' ) ) ?>",
							data: order_data.order_item_counts,
							color: '<?php echo $this->chart_colours['item_count']; ?>',
							bars: { fillColor: '<?php echo $this->chart_colours['item_count']; ?>', fill: true, show: true, lineWidth: 0, barWidth: <?php echo $this->barwidth; ?> * 0.5, align: 'center' },
						shadowSize: 0,
						hoverable: false
				},
					{
						label: "<?php echo esc_js( __( 'Number of orders', 'woocommerce' ) ) ?>",
							data: order_data.order_counts,
						color: '<?php echo $this->chart_colours['order_count']; ?>',
						bars: { fillColor: '<?php echo $this->chart_colours['order_count']; ?>', fill: true, show: true, lineWidth: 0, barWidth: <?php echo $this->barwidth; ?> * 0.5, align: 'center' },
						shadowSize: 0,
							hoverable: false
					},
					{
						label: "<?php echo esc_js( __( 'Average gross sales amount', 'woocommerce' ) ) ?>",
							data: [ [ <?php echo min( array_keys( $this->data['order_amounts'] ) ); ?>, <?php echo $this->report_data->average_total_sales; ?> ], [ <?php echo max( array_keys( $this->data['order_amounts'] ) ); ?>, <?php echo $this->report_data->average_total_sales; ?> ] ],
						yaxis: 2,
						color: '<?php echo $this->chart_colours['average']; ?>',
						points: { show: false },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
							hoverable: false
					},
					{
						label: "<?php echo esc_js( __( 'Average net sales amount', 'woocommerce' ) ) ?>",
							data: [ [ <?php echo min( array_keys( $this->data['order_amounts'] ) ); ?>, <?php echo $this->report_data->average_sales; ?> ], [ <?php echo max( array_keys( $this->data['order_amounts'] ) ); ?>, <?php echo $this->report_data->average_sales; ?> ] ],
						yaxis: 2,
						color: '<?php echo $this->chart_colours['net_average']; ?>',
						points: { show: false },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
							hoverable: false
					},
					{
						label: "<?php echo esc_js( __( 'Coupon amount', 'woocommerce' ) ) ?>",
							data: order_data.coupon_amounts,
						yaxis: 2,
						color: '<?php echo $this->chart_colours['coupon_amount']; ?>',
						points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
						<?php echo $this->get_currency_tooltip(); ?>
					},
					{
						label: "<?php echo esc_js( __( 'Shipping amount', 'woocommerce' ) ) ?>",
							data: order_data.shipping_amounts,
						yaxis: 3,
						color: '<?php echo $this->chart_colours['shipping_amount']; ?>',
						points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
							prepend_tooltip: "<?php echo get_woocommerce_currency_symbol(); ?>"
					},
					{
						label: "<?php echo esc_js( __( 'Gross sales amount', 'woocommerce' ) ) ?>",
							data: order_data.gross_order_amounts,
						yaxis: 2,
						color: '<?php echo $this->chart_colours['sales_amount']; ?>',
						points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
						<?php echo $this->get_currency_tooltip(); ?>
					},
					{
						label: "<?php echo esc_js( __( 'Net sales amount', 'woocommerce' ) ) ?>",
							data: order_data.net_order_amounts,
						yaxis: 2,
						color: '<?php echo $this->chart_colours['net_sales_amount']; ?>',
						points: { show: true, radius: 6, lineWidth: 4, fillColor: '#fff', fill: true },
						lines: { show: true, lineWidth: 5, fill: false },
						shadowSize: 0,
						<?php echo $this->get_currency_tooltip(); ?>
					},
					{
						label: "<?php echo esc_js( __( 'Refund amount', 'woocommerce' ) ) ?>",
							data: order_data.refund_amounts,
						yaxis: 2,
						color: '<?php echo $this->chart_colours['refund_amount']; ?>',
						points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
							prepend_tooltip: "<?php echo get_woocommerce_currency_symbol(); ?>"
					},
					{
						label: "<?php echo esc_js( __( 'Stripe fee', 'woocommerce-gateway-stripe' ) ) ?>",
						data: order_data.stripe_fee,
						yaxis: 3,
						color: '<?php echo $this->chart_colours['stripe_fee']; ?>',
						points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
						lines: { show: true, lineWidth: 2, fill: false },
						shadowSize: 0,
						prepend_tooltip :"<?php echo get_woocommerce_currency_symbol(); ?>"
					}
				];


					if (highlight !== 'undefined' && series[highlight]) {
						highlight_series = series[highlight];

						highlight_series.color = '#9c5d90';

						if (highlight_series.bars) {
							highlight_series.bars.fillColor = '#9c5d90';
						}

						if (highlight_series.lines) {
							highlight_series.lines.lineWidth = 5;
						}
					}

					main_chart = jQuery.plot(
						jQuery('.chart-placeholder.main'),
						series,
						{
							legend: {
								show: false
							},
							grid: {
								color: '#aaa',
								borderColor: 'transparent',
								borderWidth: 0,
								hoverable: true
							},
							xaxes: [{
								color: '#aaa',
								position: "bottom",
								tickColor: 'transparent',
								mode: "time",
								timeformat: "<?php echo ( 'day' === $this->chart_groupby ) ? '%d %b' : '%b'; ?>",
								monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ) ?>,
								tickLength: 1,
								minTickSize: [1, "<?php echo $this->chart_groupby; ?>"],
								font: {
									color: "#aaa"
								}
							}],
							yaxes: [
								{
									min: 0,
									minTickSize: 1,
									tickDecimals: 0,
									color: '#d4d9dc',
									font: {color: "#aaa"}
								},
								{
									position: "right",
									min: 0,
									tickDecimals: 2,
									alignTicksWithAxis: 1,
									color: 'transparent',
									font: {color: "#aaa"}
								},
								{
									position: "right",
									color: '#d4d9dc',
									font: {color: "#aaa"},
									tickDecimals: 2,
									autoscaleMargin: 5
								}
							],
						}
					);

					jQuery('.chart-placeholder').resize();
				}

				drawGraph();

				jQuery('.highlight_series').hover(
					function () {
						drawGraph(jQuery(this).data('series'));
					},
					function () {
						drawGraph();
					}
				);
			});
		</script>
		<?php

	}

	/**
	 * Same as for data injection, it is impossible to simply add a caption to the graph.
	 */
	public function addLegendToChart() {

		$link = sprintf(
			'<li style="border-color: %1$s" class="highlight_series" data-series="%2$d">%3$s</li>',
			$this->chart_colours['stripe_fee'],
			9,
			sprintf(
				__( '%s Stripe fee', 'woocommerce-gateway-stripe' ),
				'<strong>' . wc_price( $this->report_data->total_stripe_fee ) . '</strong>'
			)
		);
		?>
		<script type="text/javascript">
			jQuery('ul.chart-legend').append('<?php echo $link;?>');
		</script>
		<?php

	}

	/**
	 * Method from the 'WC_Report_Taxes_By_Date' file required for the construction of the graph
	 * @param $amount
	 *
	 * @return array|string
	 */
	private function round_chart_totals( $amount ) {

		if ( is_array( $amount ) ) {
			return array( $amount[0], wc_format_decimal( $amount[1], wc_get_price_decimals() ) );
		} else {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}
	}
}
