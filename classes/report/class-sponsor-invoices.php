<?php
/**
 * Sponsor Invoices.
 *
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Budgets_Dashboard\Sponsor_Invoices as WCBD_Sponsor_Invoices;

/**
 * Class Sponsor_Invoices
 *
 * @package WordCamp\Reports\Report
 */
class Sponsor_Invoices extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Sponsor Invoices';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'sponsor-invoices';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'Sponsor invoices sent and paid during a given time period.';

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'finance';

	/**
	 * WordCamp post ID.
	 *
	 * @var int The ID of the WordCamp post for this report.
	 */
	public $wordcamp_id = 0;

	/**
	 * WordCamp site ID.
	 *
	 * @var int The ID of the WordCamp site where the invoices are located.
	 */
	public $wordcamp_site_id = 0;

	/**
	 * Currency exchange rate client.
	 *
	 * @var Reports\Currency_XRT_Client Utility to handle currency conversion.
	 */
	protected $xrt = null;

	/**
	 * Sponsor_Invoices constructor.
	 *
	 * @param string $start_date  The start of the date range for the report.
	 * @param string $end_date    The end of the date range for the report.
	 * @param int    $wordcamp_id Optional. The ID of a WordCamp post to limit this report to.
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
	 * Query and parse the data for the report.
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

		$data = array();

		// If a particular WordCamp is specified, check to see if it has invoices.
		$allowed_invoice_ids = array();
		if ( $this->wordcamp_site_id ) {
			$allowed_invoice_ids = array_keys( $this->get_indexed_invoices() );
		}

		if ( ! $this->wordcamp_site_id || ! empty( $allowed_invoice_ids ) ) {
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

			// Filter out QBO payments that aren't for relevant invoices.
			$qbo_payments = array_filter( $qbo_payments, function ( $payment ) use ( $allowed_invoice_ids ) {
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
							if ( $this->wordcamp_site_id ) {
								if ( in_array( absint( $txn['TxnId'] ), $allowed_invoice_ids, true ) ) {
									$return = true;
									break 2;
								}
							} else {
								$return = true;
								break 2;
							}
						}
					}
				}

				return $return;
			} );

			$data = array_merge(
				array_values( $qbo_invoices ),
				array_values( $qbo_payments )
			);
		} // End if().

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * Compile the report data into results.
	 *
	 * @param array $data The data to compile.
	 *
	 * @return array
	 */
	public function compile_report_data( array $data ) {
		$invoices = $this->filter_transactions_by_type( $data, 'Invoice' );
		$payments = $this->filter_transactions_by_type( $data, 'Payment' );

		$compiled_data = array(
			'invoices' => $this->parse_transaction_stats( $invoices ),
			'payments' => $this->parse_transaction_stats( $payments ),
		);

		return $compiled_data;
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

		// Add a type column.
		$invoices = array_map( function( $invoice ) {
			$invoice['Type'] = 'Invoice';

			return $invoice;
		}, $invoices );

		return $invoices;
	}

	/**
	 * Get invoices from the WordCamp database that match invoice IDs from QBO.
	 *
	 * Limit the returned invoices to a specific WordCamp if the `wordcamp_id` property has been set.
	 *
	 * @param array $ids Optional. A list of QBO invoice IDs to filter by.
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

		// Add a type column.
		$payments = array_map( function( $payment ) {
			$payment['Type'] = 'Payment';

			return $payment;
		}, $payments );

		return $payments;
	}

	/**
	 * Out of an array of transactions, generate an array of only one type of transaction.
	 *
	 * @param array  $transactions The transactions to filter.
	 * @param string $type         The type to filter for.
	 *
	 * @return array
	 */
	protected function filter_transactions_by_type( array $transactions, $type ) {
		return array_filter( $transactions, function( $transaction ) use ( $type ) {
			if ( $type === $transaction['Type'] ) {
				return true;
			}

			return false;
		} );
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

		ksort( $amount_by_currency );

		$converted_amounts = array();

		foreach ( $amount_by_currency as $currency => $amount ) {
			if ( 'USD' === $currency ) {
				$converted_amounts[ $currency ] = $amount;
			} else {
				$converted_amounts[ $currency ] = 0;

				$conversion = $this->xrt->convert( $amount, $currency, $this->end_date->format( 'Y-m-d' ) );

				if ( is_wp_error( $conversion ) ) {
					// Unsupported currencies are ok, but other errors should be surfaced.
					if ( 'unknown_currency' !== $conversion->get_error_code() ) {
						$this->merge_errors( $this->error, $conversion );
					}
				} else {
					$converted_amounts[ $currency ] = $conversion->USD;
				}
			}
		}

		$total_amount_converted = array_reduce( $converted_amounts, function( $carry, $item ) {
			return $carry + floatval( $item );
		}, 0 );

		return array(
			'total_count'            => $total_count,
			'amount_by_currency'     => $amount_by_currency,
			'converted_amounts'      => $converted_amounts,
			'total_amount_converted' => $total_amount_converted,
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
		$data       = $this->compile_report_data( $this->get_data() );
		$start_date = $this->start_date;
		$end_date   = $this->end_date;

		$wordcamp_name = ( $this->wordcamp_site_id ) ? get_wordcamp_name( $this->wordcamp_site_id ) : '';
		$invoices      = $data['invoices'];
		$payments      = $data['payments'];

		if ( ! empty( $this->error->get_error_messages() ) ) {
			$this->render_error_html();
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
		$refresh     = filter_input( INPUT_POST, 'refresh', FILTER_VALIDATE_BOOLEAN );
		$action      = filter_input( INPUT_POST, 'action' );
		$nonce       = filter_input( INPUT_POST, self::$slug . '-nonce' );

		$report = null;

		if ( 'Show results' === $action
		     && wp_verify_nonce( $nonce, 'run-report' )
		     && current_user_can( 'manage_network' )
		) {
			$options = array(
				'earliest_start' => new \DateTime( '2016-01-07' ), // Date of first QBO invoice in the system.
			);

			if ( $refresh ) {
				$options['flush_cache'] = true;
			}

			$report = new self( $start_date, $end_date, $wordcamp_id, $options );

			// The report adjusts the end date in some circumstances.
			if ( empty( $report->error->get_error_messages() ) ) {
				$end_date = $report->end_date->format( 'Y-m-d' );
			}
		}

		include Reports\get_views_dir_path() . 'report/sponsor-invoices.php';
	}
}
