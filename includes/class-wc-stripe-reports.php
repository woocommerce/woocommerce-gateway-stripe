<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WC_ABSPATH' ) ) {
	exit;
}

include_once( WC_ABSPATH . '/includes/admin/reports/class-wc-admin-report.php' );

/**
 * Class WC_Stripe_Reports
 *
 * @extends          WC_Admin_Report
 * @author           Nicolas GEHIN - studio RVOLA
 * @author_uri       https://www.rvola.com
 * @since            4.0.5
 * @created          2017-09-27
 * @updated          2018-02-11
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

		$check_init = $this->init();

		if ( $check_init === true ) {

			add_filter( 'woocommerce_admin_report_data', array( $this, 'queryReport' ), 10, 1 );
			add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'cleanQueryReport' ), 10, 1 );
			add_filter( 'woocommerce_admin_report_chart_data', array( $this, 'addDataInChart' ), 10, 1 );
			add_action( 'admin_print_footer_scripts', array( $this, 'addLegendToChart' ), 10 );
			add_action( 'admin_print_footer_scripts', array( $this, 'updateLegendPlaceholder' ), 10 );
			add_action( 'admin_print_footer_scripts', array( $this, 'drawGraph' ), 10 );
			add_action( 'admin_print_footer_scripts', array( $this, 'updateSizeChart' ), 10 );
		}

	}

	/**
	 * Initializes some value required for changes in reports
	 */
	private function init() {

		global $pagenow;

		/*Range date "default - 7 days*/
		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
			$current_range = '7day';
		}

		if ( ! method_exists( $this, 'check_current_range_nonce' ) || ! method_exists( $this,
				'calculate_current_range' ) ) {
			return false;
		}
		$this->check_current_range_nonce( $current_range );
		$this->calculate_current_range( $current_range );

		/*Range date if Dashboard page*/
		if ( $pagenow === 'index.php' ) {
			$this->start_date = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
			$this->end_date   = current_time( 'timestamp' );
		}

		return true;
	}

	/**
	 * Adds a request to the loop to get the total of Stripe fee
	 *
	 * @param $report_data
	 *
	 * @return mixed
	 */
	public function queryReport( $report_data ) {

		$this->report_data = $report_data;

		/*search Stripe fee's*/
		$stripe_fee = $this->get_order_report_data( array(
			'data'         => array(
				'___stripe_fee___' => array(
					'type'     => 'meta',
					'function' => 'SUM',
					'name'     => 'total',
				),
				'post_date'        => array(
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

		/*Add Fee in Report Data*/
		$this->report_data->stripe_fee = $stripe_fee;

		/*Add Total Fee*/
		$this->report_data->total_stripe_fee = wc_format_decimal( array_sum( wp_list_pluck( $this->report_data->stripe_fee,
			'total' ) ), 2 );

		/*Net Sale - Stripe fee*/
		$this->report_data->net_sales = wc_format_decimal(
			$this->report_data->total_sales
			- $this->report_data->total_shipping
			- $this->report_data->total_stripe_fee
			- max( 0, $this->report_data->total_tax )
			- max( 0, $this->report_data->total_shipping_tax )
			- max( 0, $this->report_data->total_stripe_fee )
			, 2 );

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

		preg_match( '/___stripe_fee___/', $query['select'], $match );
		if ( $match ) {
			$query['select'] = str_replace( '___stripe_fee___', 'key', $query['select'] );
			$query['join']   = str_replace( 'meta____stripe_fee___', 'meta_key', $query['join'] );
			$query['join']   = str_replace( "'___stripe_fee___'", "'" . self::META_NAME_FEE . "'", $query['join'] );
		}

		return $query;
	}

	/**
	 * Added the results found in the table to build the graph.
	 *
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
	 * Add the new legend and adjust the height of the graph
	 */
	public function addLegendToChart() {

		if ( ! $this->data ) {
			return;
		}
		$link = sprintf(
			'<li style="border-color: %1$s" class="highlight_series" data-series="%2$d">%3$s</li>',
			self::COLOR,
			9,
			sprintf(
				__( '%s Stripe fees', 'woocommerce-gateway-stripe' ),
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
	 * Replaces hover text for net value.
	 * Because now we deduct Stripe fees from net worth
	 */
	public function updateLegendPlaceholder() {

		$tripe_legend = array(
			7 => __( 'This is the sum of the order totals after any refunds and excluding shipping, taxes and Stripe fee.',
				'woocommerce-gateway-stripe' ),
		);
		echo '<script type="text/javascript">';
		foreach ( $tripe_legend as $serie => $text ) {
			printf(
				"jQuery('ul.chart-legend').find('li[data-series=%d]').attr('data-tip', '%s');",
				$serie,
				$text
			);
		}
		echo '</script>';
	}

	/**
	 * Change the size of the graph to be in harmony with the legend
	 */
	public function updateSizeChart() {

		echo '<script type="text/javascript">';
		echo 'jQuery(".woocommerce-reports-wide .postbox .chart-placeholder").height(755);';
		echo '</script>';
	}

	/**
	 * There is a way to add data to jQuery.plot, but it is almost useless if you change the graph options.
	 * The ideal would probably be a hook directly in WooCommerce.
	 * Much of the code comes from the WC_Report_Sales_By_Date get_main_chart () method
	 */
	public function drawGraph() {

		global $wp_locale;

		if ( ! $this->data ) {
			return;
		}

		$stripe_fees = json_encode(
			array_map( array( $this, 'round_chart_totals' ), array_values( $this->data['stripe_fee'] ) )
		);

		?>
		<script type="text/javascript">

			jQuery(function () {

				var stripe_main_char;

				var drawGraph = function (highlight) {

					/*Get Data*/
					var series = [];
					var current_series = main_chart.getData();
					jQuery(current_series).each(function (i, value) {
						series.push(value);
					});

					/*Stripe serie*/
					var stripe_series = {
						label: "<?php echo esc_js( __( 'Stripe fees', 'woocommerce-gateway-stripe' ) ) ?>",
						data: <?php echo $stripe_fees;?>,
						yaxis: 2,
						color: '<?php echo self::COLOR; ?>',
						points: {show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true},
						lines: {show: true, lineWidth: 2, fill: false},
						shadowSize: 0,
						<?php echo $this->get_currency_tooltip(); ?>
					}
					series.push(stripe_series);

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

					stripe_main_char = jQuery.plot(
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
	 * Method from the 'WC_Report_Taxes_By_Date' file required for the construction of the graph
	 *
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
