<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/**
 * Class Ticket_Revenue
 *
 * @package WordCamp\Reports\Report
 */
class Ticket_Revenue extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Ticket Revenue';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'ticket-revenue';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'A summary of WordCamp ticket revenue during a given time period.';

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'finance';

	/**
	 * REST route for this report.
	 *
	 * @var string
	 */
	public static $rest_base = 'ticket-revenue';

	/**
	 * @var int The ID of the WordCamp post for this report.
	 */
	public $wordcamp_id = 0;

	/**
	 * @var int The ID of the WordCamp site where the invoices are located.
	 */
	public $wordcamp_site_id = 0;

	/**
	 * @var Reports\Currency_XRT_Client Utility to handle currency conversion.
	 */
	protected $xrt = null;

	/**
	 * Ticket_Revenue constructor.
	 *
	 * @param string $start_date  The start of the date range for the report.
	 * @param string $end_date    The end of the date range for the report.
	 * @param int    $wordcamp_id Optional. The ID of a WordCamp post to retrieve invoices for.
	 * @param array  $options     {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct and Date_Range::__construct for additional parameters.
	 * }
	 */
	public function __construct( $start_date, $end_date, $wordcamp_id = 0, array $options = array() ) {
		parent::__construct( $start_date, $end_date, $options );

		$this->xrt = new Reports\Currency_XRT_Client();

		if ( $wordcamp_id && $this->validate_wordcamp_id( $wordcamp_id ) ) {
			$this->wordcamp_id      = $wordcamp_id;
			$this->wordcamp_site_id = get_wordcamp_site_id( get_post( $wordcamp_id ) );
		}
	}

	/**
	 * Generate a cache key.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = parent::get_cache_key();

		if ( $this->wordcamp_id ) {
			$cache_key .= '_' . $this->wordcamp_id;
		}

		return $cache_key;
	}

	/**
	 * Query, parse, and compile the data for the report.
	 *
	 * @return array
	 */
	public function get_data() {
		// Bail if there are errors.
		if ( ! empty( $this->error->get_error_messages() ) ) {
			return array();
		}

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		// This script is a memory hog for date intervals larger than ~2 months.
		// @todo Maybe find a way to run this without having to hack the memory limit.
		ini_set( 'memory_limit', '512M' );

		$events = $this->get_indexed_camptix_events( array(
			'Attendee status has been changed to publish',
			'Attendee status has been changed to refund',
		) );

		array_walk( $events, function( &$event ) {
			if ( false !== strpos( $event['message'], 'publish' ) ) {
				$event['type'] = 'purchase';
				unset( $event['message'] );
			} elseif ( false !== strpos( $event['message'], 'refund' ) ) {
				$event['type'] = 'refund';
				unset( $event['message'] );
			}
		} );

		$tickets_by_site = $this->sort_indexed_ticket_ids_by_site( $events );
		$ticket_details  = array();

		foreach ( $tickets_by_site as $blog_id => $ticket_ids ) {
			$ticket_details = array_merge( $ticket_details, $this->get_ticket_details( $blog_id, $ticket_ids ) );
		}

		$data = $this->derive_revenue_from_ticket_events( $events, $ticket_details );

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Retrieve events from the CampTix log database table.
	 *
	 * @param array $message_filter Array of strings to search for in the event message field, using the OR operator.
	 *
	 * @return array
	 */
	protected function get_indexed_camptix_events( array $message_filter = array() ) {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table_name = $wpdb->base_prefix . 'camptix_log';

		$where_clause = array();
		$where_values = array();
		$where        = '';

		$where_clause[] = 'UNIX_TIMESTAMP( timestamp ) BETWEEN ' .
		                  $this->start_date->getTimestamp() .
		                  ' AND ' .
		                  $this->end_date->getTimestamp();

		if ( ! empty( $message_filter ) ) {
			$like_clause = array();

			foreach ( $message_filter as $string ) {
				$like_clause[] = 'message LIKE \'%%%s%%\'';
				$where_values[] = $string;
			}

			$where_clause[] = '( ' . implode( ' OR ', $like_clause ) . ' )';
		}

		if ( $this->wordcamp_site_id ) {
			$where_clause[] = 'blog_id = %d';
			$where_values[] = $this->wordcamp_site_id;
		}

		if ( ! empty( $where_clause ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clause );
		}

		$sql = "
			SELECT blog_id, object_id, message
			FROM $table_name
		" . $where;

		$query  = $wpdb->prepare( $sql, $where_values );
		$events = $wpdb->get_results( $query, ARRAY_A );

		return $events;
	}

	/**
	 * Group log event ticket IDs by their blog ID.
	 *
	 * @param array $events An array of CampTix log events/tickets.
	 *
	 * @return array
	 */
	protected function sort_indexed_ticket_ids_by_site( $events ) {
		$sorted = array();

		foreach ( $events as $event ) {
			if ( ! isset( $sorted[ $event['blog_id'] ] ) ) {
				$sorted[ $event['blog_id'] ] = array();
			}

			$sorted[ $event['blog_id'] ][] = $event['object_id'];
		}

		$sorted = array_map( 'array_unique', $sorted );

		return $sorted;
	}

	/**
	 * Get relevant details for a given list of tickets for a particular site.
	 *
	 * @param int   $blog_id    The ID of the site that the tickets are associated with.
	 * @param array $ticket_ids The IDs of specific tickets to get details for.
	 *
	 * @return array
	 */
	protected function get_ticket_details( $blog_id, array $ticket_ids ) {
		$ticket_details = array();
		$currency       = '';

		switch_to_blog( $blog_id );

		$options = get_option( 'camptix_options', array() );

		if ( isset( $options['currency'] ) ) {
			$currency = $options['currency'];
		}

		foreach ( $ticket_ids as $ticket_id ) {
			$ticket_details[ $blog_id . '_' . $ticket_id ] = array(
				'blog_id'          => $blog_id,
				'ticket_id'        => $ticket_id,
				'currency'         => $currency,
				'full_price'       => get_post_meta( $ticket_id, 'tix_ticket_price', true ),
				'discounted_price' => get_post_meta( $ticket_id, 'tix_ticket_discounted_price', true ),
			);

			clean_post_cache( $ticket_id );
		}

		restore_current_blog();

		return $ticket_details;
	}

	/**
	 * Apply ticket details to each ticket event and aggregate to revenue totals.
	 *
	 * @param array $events         Ticket events.
	 * @param array $ticket_details Ticket details.
	 *
	 * @return array
	 */
	protected function derive_revenue_from_ticket_events( array $events, array $ticket_details ) {
		$initial_data = array(
			'tickets_sold'                => 0,
			'gross_revenue_by_currency'   => array(),
			'discounts_by_currency'       => array(),
			'tickets_refunded'            => 0,
			'amount_refunded_by_currency' => array(),
			'net_revenue_by_currency'     => array(),
			'converted_net_revenue'       => array(),
			'total_converted_revenue'     => 0,
		);

		$data_groups = array(
			'wpcs'     => $initial_data,
			'non_wpcs' => $initial_data,
			'total'    => $initial_data,
		);

		$wpcs_supported_currencies = self::get_wpcs_currencies();
		$currencies                = array();

		foreach ( $events as $event ) {
			$details = $ticket_details[ $event['blog_id'] . '_' . $event['object_id'] ];

			if ( ! isset( $details['currency'] ) ) {
				continue;
			}

			if ( in_array( $details['currency'], $wpcs_supported_currencies, true ) ) {
				$group = 'wpcs';
			} else {
				$group = 'non_wpcs';
			}

			if ( ! in_array( $details['currency'], $currencies, true ) ) {
				$data_groups[ $group ]['gross_revenue_by_currency'][ $details['currency'] ]   = 0;
				$data_groups[ $group ]['discounts_by_currency'][ $details['currency'] ]       = 0;
				$data_groups[ $group ]['amount_refunded_by_currency'][ $details['currency'] ] = 0;
				$data_groups[ $group ]['net_revenue_by_currency'][ $details['currency'] ]     = 0;
				$data_groups['total']['gross_revenue_by_currency'][ $details['currency'] ]    = 0;
				$data_groups['total']['discounts_by_currency'][ $details['currency'] ]        = 0;
				$data_groups['total']['amount_refunded_by_currency'][ $details['currency'] ]  = 0;
				$data_groups['total']['net_revenue_by_currency'][ $details['currency'] ]      = 0;
				$currencies[]                                                                 = $details['currency'];
			}

			switch ( $event['type'] ) {
				case 'purchase' :
					$data_groups[ $group ]['tickets_sold'] ++;
					$data_groups[ $group ]['gross_revenue_by_currency'][ $details['currency'] ] += floatval( $details['full_price'] );
					$data_groups[ $group ]['discounts_by_currency'][ $details['currency'] ]     += floatval( $details['full_price'] ) - floatval( $details['discounted_price'] );
					$data_groups[ $group ]['net_revenue_by_currency'][ $details['currency'] ]   += floatval( $details['discounted_price'] );
					$data_groups['total']['tickets_sold'] ++;
					$data_groups['total']['gross_revenue_by_currency'][ $details['currency'] ] += floatval( $details['full_price'] );
					$data_groups['total']['discounts_by_currency'][ $details['currency'] ]     += floatval( $details['full_price'] ) - floatval( $details['discounted_price'] );
					$data_groups['total']['net_revenue_by_currency'][ $details['currency'] ]   += floatval( $details['discounted_price'] );
					break;

				case 'refund' :
					$data_groups[ $group ]['tickets_refunded'] ++;
					$data_groups[ $group ]['amount_refunded_by_currency'][ $details['currency'] ] += floatval( $details['discounted_price'] );
					$data_groups[ $group ]['net_revenue_by_currency'][ $details['currency'] ]     -= floatval( $details['discounted_price'] );
					$data_groups['total']['tickets_refunded'] ++;
					$data_groups['total']['amount_refunded_by_currency'][ $details['currency'] ] += floatval( $details['discounted_price'] );
					$data_groups['total']['net_revenue_by_currency'][ $details['currency'] ]     -= floatval( $details['discounted_price'] );
					break;
			}
		}

		foreach ( $data_groups as &$group ) {
			ksort( $group['gross_revenue_by_currency'] );
			ksort( $group['discounts_by_currency'] );
			ksort( $group['amount_refunded_by_currency'] );
			ksort( $group['net_revenue_by_currency'] );

			foreach ( $group['net_revenue_by_currency'] as $currency => $amount ) {
				if ( 'USD' === $currency ) {
					$group['converted_net_revenue'][ $currency ] = $amount;
				} else {
					$group['converted_net_revenue'][ $currency ] = 0;

					$conversion = $this->xrt->convert( $amount, $currency, $this->end_date->format( 'Y-m-d' ) );

					if ( is_wp_error( $conversion ) ) {
						// Unsupported currencies are ok, but other errors should be surfaced.
						if ( 'unknown_currency' !== $conversion->get_error_code() ) {
							$this->merge_errors( $this->error, $conversion );
						}
					} else {
						$group['converted_net_revenue'][ $currency ] = $conversion->USD;
					}
				}
			}

			$group['total_converted_revenue'] = array_reduce( $group['converted_net_revenue'], function( $carry, $item ) {
				return $carry + floatval( $item );
			}, 0 );
		}

		return $data_groups;
	}

	/**
	 * Get the list of currencies supported by the WPCS payment account.
	 *
	 * Any camp using a currency supported by the CampTix PayPal payment gateway is assumed to be using the
	 * WPCS PayPal account for ticket sales.
	 *
	 * Wrapper method to help minimize coupling with the CampTix plugin.
	 *
	 * @return array
	 */
	protected static function get_wpcs_currencies() {
		/** @var \CampTix_Plugin $camptix */
		global $camptix;

		return $camptix->get_payment_method_by_id( 'paypal' )->supported_currencies;
	}

	/**
	 * Render an HTML version of the report output.
	 *
	 * @return void
	 */
	public function render_html() {
		$data       = $this->get_data();
		$start_date = $this->start_date;
		$end_date   = $this->end_date;

		$wordcamp_name = ( $this->wordcamp_site_id ) ? get_wordcamp_name( $this->wordcamp_site_id ) : '';
		$wpcs          = $data['wpcs'];
		$non_wpcs      = $data['non_wpcs'];
		$total         = $data['total'];

		if ( ! empty( $this->error->get_error_messages() ) ) {
			?>
			<div class="notice notice-error">
				<?php foreach ( $this->error->get_error_messages() as $message ) : ?>
					<?php echo wpautop( wp_kses_post( $message ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php
		} else {
			include Reports\get_views_dir_path() . 'html/ticket-revenue.php';
		}
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date  = filter_input( INPUT_POST, 'start-date' );
		$end_date    = filter_input( INPUT_POST, 'end-date' );
		$wordcamp_id = filter_input( INPUT_POST, 'wordcamp-id' );
		$refresh     = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2007-11-17' ), // Date of first WordCamp in the system.
				'max_interval'   => new \DateInterval( 'P1Y' ), // 1 year. See http://php.net/manual/en/dateinterval.construct.php.
			);

			if ( $refresh ) {
				$options['cache_data'] = false;
			}

			$report = new self( $start_date, $end_date, $wordcamp_id, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}

		include Reports\get_views_dir_path() . 'report/ticket-revenue.php';
	}

	/**
	 * Prepare a REST response version of the report output.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function rest_callback( \WP_REST_Request $request ) {
		$params = wp_parse_args( $request->get_params(), array(
			'start_date'  => '',
			'end_date'    => '',
			'wordcamp_id' => 0,
		) );

		$options = array(
			'earliest_start' => new \DateTime( '2007-11-17' ), // Date of first WordCamp in the system.
			'max_interval'   => new \DateInterval( 'P1Y' ), // 1 year. See http://php.net/manual/en/dateinterval.construct.php.
		);

		$report = new self( $params['start_date'], $params['end_date'], $params['wordcamp_id'], $options );

		if ( $report->error->get_error_messages() ) {
			$response = self::prepare_rest_response( $report->error->errors );
			$response->set_status( 400 );
		} else {
			$response = self::prepare_rest_response( $report->get_data() );
		}

		return $response;
	}
}
