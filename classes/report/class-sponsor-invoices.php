<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Utilities;
use WordCamp\Budgets\Sponsor_Invoices as WCB_Sponsor_Invoices;
use WordCamp\Budgets_Dashboard\Sponsor_Invoices as WCBD_Sponsor_Invoices;

/**
 * Class Sponsor_Invoices
 *
 * @package WordCamp\Reports\Report
 */
class Sponsor_Invoices extends Date_Range {
	/**
	 * Report name.
	 */
	public static $name = 'Sponsor Invoices';

	/**
	 * Report slug.
	 */
	public static $slug = 'sponsor-invoices';

	/**
	 * Report description.
	 */
	public static $description = 'A summary of sponsor invoice activity during a given time period.';

	/**
	 * @var int The ID of the WordCamp post for this report.
	 */
	public $wordcamp_id = 0;

	/**
	 * @var int The ID of the WordCamp site where the invoices are located.
	 */
	public $wordcamp_site_id = 0;

	/**
	 * @var string The status ID to filter for in the report.
	 */
	public $status = '';

	/**
	 * Sponsor_Invoices constructor.
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

		if ( $wordcamp_id && $this->validate_wordcamp_id( $wordcamp_id ) ) {
			$this->wordcamp_id      = $wordcamp_id;
			$this->wordcamp_site_id = get_wordcamp_site_id( get_post( $wordcamp_id ) );
		}
	}

	/**
	 * Validate the given WordCamp post ID.
	 *
	 * @param int $wordcamp_id The ID of a WordCamp post to retrieve invoices for.
	 *
	 * @return bool True if the WordCamp ID is valid. Otherwise false.
	 */
	protected function validate_wordcamp_id( $wordcamp_id ) {
		$wordcamp = get_post( $wordcamp_id );

		if ( ! $wordcamp instanceof \WP_Post || Utilities\get_wordcamp_post_type_id() !== get_post_type( $wordcamp ) ) {
			$this->error->add( 'invalid_wordcamp_id', 'Please enter a valid WordCamp ID.' );

			return false;
		}

		$wordcamp_site_id = get_wordcamp_site_id( $wordcamp );

		if ( ! $wordcamp_site_id ) {
			$this->error->add( 'wordcamp_without_site', 'The specified WordCamp does not have a site yet.' );

			return false;
		}

		return true;
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

		$qbo_invoices = $this->get_qbo_invoices();

		if ( is_wp_error( $qbo_invoices ) ) {
			$this->error = $this->merge_errors( $this->error, $qbo_invoices );
			return array();
		}

		$indexed_invoices = $this->get_indexed_invoices( array_keys( $qbo_invoices ) );

		// Filter out QBO invoices not in the index list.
		// Excludes non-sponsor invoices and invoices from other WordCamps if the `wordcamp_id` property has been set.
		$qbo_invoices = array_intersect_key( $qbo_invoices, $indexed_invoices );

		$qbo_payments = $this->get_qbo_payments();

		if ( is_wp_error( $qbo_payments ) ) {
			$this->error = $this->merge_errors( $this->error, $qbo_payments );
			return array();
		}

		// Filter out QBO payments that aren't for invoices.
		$qbo_payments = array_filter( $qbo_payments, function( $payment ) {
			if ( ! isset( $payment['Line'] ) || empty( $payment['Line'] ) ) {
				return false;
			}

			$return = false;

			foreach ( $payment['Line'] as $line ) {
				if ( ! isset( $line['LinkedTxn'] ) ) {
					continue;
				}

				foreach ( $line['LinkedTxn'] as $txn ) {
					if ( 'Invoice' === $txn['TxnType'] ) {
						$return = true;
						break 2;
					}
				}
			}

			return $return;
		} );

		$data = array(
			'invoices' => $this->parse_transaction_stats( $qbo_invoices ),
			'payments' => $this->parse_transaction_stats( $qbo_payments ),
		);

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Get all the invoices created in QBO within the given date range.
	 *
	 * @return array|\WP_Error An array of invoices or an error object.
	 */
	protected function get_qbo_invoices() {
		$qbo = new Reports\QBO_Client();

		$invoices = $qbo->get_transactions_by_date( 'Invoice', $this->start_date, $this->end_date );

		if ( is_wp_error( $invoices ) ) {
			return $invoices;
		}

		// Key the invoice array with the invoice IDs.
		$invoices = array_combine(
			wp_list_pluck( $invoices, 'Id' ),
			$invoices
		);

		return $invoices;
	}

	/**
	 * Get invoices from the WordCamp database that match invoice IDs from QBO.
	 *
	 * Limit the returned invoices to a specific WordCamp if the `wordcamp_id` property has been set.
	 *
	 * @return array
	 */
	protected function get_indexed_invoices( array $ids = array() ) {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$table_name = self::get_index_table_name();

		$where_clause = array();
		$where_values = array();
		$where        = '';

		if ( ! empty( $ids ) ) {
			$ids             = array_map( 'absint', $ids );
			$id_placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$where_clause[]  = "qbo_invoice_id IN ( $id_placeholders )";
			$where_values   += $ids;
		} else {
			// Invoices that don't have a corresponding entity in QBO yet have a `qbo_invoice_id` value of 0.
			$where_clause[]  = "qbo_invoice_id != 0";
		}

		if ( $this->wordcamp_site_id ) {
			$where_clause[] = "blog_id = %d";
			$where_values[] = $this->wordcamp_site_id;
		}

		if ( ! empty( $where_clause ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clause );
		}

		$sql = "
			SELECT *
			FROM $table_name
		" . $where;

		$query   = $wpdb->prepare( $sql, $where_values );
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $results ) ) {
			// Key the invoices array with the `qbo_invoice_id` field.
			$results = array_combine(
				wp_list_pluck( $results, 'qbo_invoice_id' ),
				$results
			);
		}

		return $results;
	}

	/**
	 * Get all the payment transactions created in QBO within the given date range.
	 *
	 * @return array|\WP_Error An array of payments or an error object.
	 */
	protected function get_qbo_payments() {
		$qbo = new Reports\QBO_Client();

		$payments = $qbo->get_transactions_by_date( 'Payment', $this->start_date, $this->end_date );

		if ( is_wp_error( $payments ) ) {
			return $payments;
		}

		// Key the invoice array with the invoice IDs.
		$payments = array_combine(
			wp_list_pluck( $payments, 'Id' ),
			$payments
		);

		return $payments;
	}

	/**
	 * Gather statistics about a given collection of transactions.
	 *
	 * @param array $transactions A list of invoice or payment entities from QBO.
	 *
	 * @return array
	 */
	protected function parse_transaction_stats( array $transactions ) {
		$total_count = count( $transactions );

		$amount_by_currency = array();

		foreach ( $transactions as $transaction ) {
			$currency = $transaction['CurrencyRef']['value'];
			$amount   = $transaction['TotalAmt'];

			if ( ! isset( $amount_by_currency[ $currency ] ) ) {
				$amount_by_currency[ $currency ] = 0;
			}

			$amount_by_currency[ $currency ] += $amount;
		}

		return array(
			'total_count'        => $total_count,
			'amount_by_currency' => $amount_by_currency,
		);
	}

	/**
	 * The name of the table containing an index of all sponsor invoices in the network.
	 *
	 * Wrapper method to help minimize coupling with the WordCamp Payments Network plugin.
	 *
	 * If this needs to be used outside of this class, move it to utilities.php.
	 *
	 * @return string
	 */
	protected static function get_index_table_name() {
		return WCBD_Sponsor_Invoices\get_index_table_name();
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

		$wordcamp_name     = ( $this->wordcamp_site_id ) ? get_wordcamp_name( $this->wordcamp_site_id ) : '';
		$invoices_sent     = $data['invoices']['total_count'];
		$invoice_amounts   = $data['invoices']['amount_by_currency'];
		$payments_received = $data['payments']['total_count'];
		$payment_amounts   = $data['payments']['amount_by_currency'];

		if ( ! empty( $this->error->get_error_messages() ) ) {
			?>
			<div class="notice notice-error">
				<?php foreach ( $this->error->get_error_messages() as $message ) : ?>
					<?php echo wpautop( wp_kses_post( $message ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php
		} else {
			include Reports\get_views_dir_path() . 'html/sponsor-invoices.php';
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
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2007-11-17' ), // Date of first WordCamp in the system.
				'cache_data'     => false, // WP Admin is low traffic and more trusted, so turn off caching.
			);

			$report = new self( $start_date, $end_date, $wordcamp_id, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}

		include Reports\get_views_dir_path() . 'report/sponsor-invoices.php';
	}
}
