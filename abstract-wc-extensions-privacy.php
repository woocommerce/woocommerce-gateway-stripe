<?php
/**
 * Abstract class that is intended to be extended by
 * extension specific privacy class. It handles the display
 * of the privacy message of the extension to the admin,
 * privacy data to be exported and privacy data to be deleted.
 *
 * @since 1.0.0
 */
abstract class WC_Extensions_Privacy {
	public $plugin_name;
	protected $exporters;
	protected $erasures;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The display name of plugin.
	 */
	public function __construct( $plugin_name = '' ) {
		$this->plugin_name = $plugin_name;
		$this->exporters   = array();
		$this->erasures    = array();

		add_action( 'admin_init', array( $this, 'add_privacy_message' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasures' ) );
	}

	/**
	 * Adds the privacy message on WC
	 * privacy page.
	 *
	 * @since 1.0.0
	 */
	public function add_privacy_message() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( $this->plugin_name, $this->get_message() );
		}
	}

	/**
	 * Gets the message of the privacy to display.
	 * To be overloaded by the implementor.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	abstract public function get_message();

	/**
	 * Integrate this exporter implementation within the WordPress core exporters.
	 *
	 * @param array $exporters List of exporter callbacks.
	 * @return array
	 */
	public function register_exporters( $exporters = array() ) {
		return array_merge( $exporters, $this->exporters );
	}

	/**
	 * Integrate this erasure implementation within the WordPress core erasures.
	 *
	 * @param array $erasures List of erasure callbacks.
	 * @return array
	 */
	public function register_erasures( $erasures = array() ) {
		return array_merge( $erasures, $this->erasures );
	}


	/**
	 * Add exporter to list of exporters.
	 *
	 * @param string $name Exporter name
	 * @param string $callback Exporter callback
	 */
	public function add_exporter( $name, $callback ) {
		$this->exporters[] = array(
			'exporter_friendly_name' => $name,
			'callback'               => $callback,
		);

		return $this->exporters;
	}

	/**
	 * Add erasure to list of exporters.
	 *
	 * @param string $name Exporter name
	 * @param string $callback Exporter callback
	 */
	public function add_erasure( $name, $callback ) {
		$this->erasures[] = array(
			'exporter_friendly_name' => $name,
			'callback'               => $callback,
		);

		return $this->erasures;
	}

	/**
	 * Example implementation of an exporter.
	 *
	 * Plugins can add as many items in the item data array as they want. Example:
	 *
	 *     $data = array(
	 *   	array(
	 *   	  'name'  => __( 'Commenter Latitude' ),
	 *   	  'value' => $latitude
	 *   	),
	 *   	array(
	 *   	  'name'  => __( 'Commenter Longitude' ),
	 *   	  'value' => $longitude
	 *   	)
	 *     );
	 *
	 *     $export_items[] = array(
	 *   	'group_id'    => $group_id,
	 *   	'group_label' => $group_label,
	 *   	'item_id'     => $item_id,
	 *   	'data'        => $data,
	 *     );
	 *   }
	 * }
	 *
	 * Tell core if we have more comments to work on still. Example:
	 * $done = count( $comments ) < $number;
	 *
	 * return array(
	 *   'data' => $export_items,
	 *   'done' => $done,
	 * );
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public final function example_exporter( $email_address, $page = 1 ) {}

	/**
	 * Example implementation of an erasure.
	 *
	 * Plugins can add as many items in the item data array as they want. Example:
	 *
	 *     $data = array(
	 *   	array(
	 *   	  'name'  => __( 'Commenter Latitude' ),
	 *   	  'value' => $latitude
	 *   	),
	 *   	array(
	 *   	  'name'  => __( 'Commenter Longitude' ),
	 *   	  'value' => $longitude
	 *   	)
	 *     );
	 *
	 *     $export_items[] = array(
	 *   	'group_id'    => $group_id,
	 *   	'group_label' => $group_label,
	 *   	'item_id'     => $item_id,
	 *   	'data'        => $data,
	 *     );
	 *   }
	 * }
	 *
	 * Tell core if we have more comments to work on still. Example:
	 * $done = count( $comments ) < $number;
	 *
	 * return array(
	 *   'data' => $export_items,
	 *   'done' => $done,
	 * );
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public final function example_erasure( $email_address, $page = 1 ) {}
}
