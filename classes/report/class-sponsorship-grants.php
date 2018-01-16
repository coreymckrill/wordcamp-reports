<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;


class Sponsorship_Grants extends Date_Range {
	/**
	 * Report name.
	 *
	 * @var string
	 */
	public static $name = 'Global Sponsorship Grants';

	/**
	 * Report slug.
	 *
	 * @var string
	 */
	public static $slug = 'sponsorship-grants';

	/**
	 * Report description.
	 *
	 * @var string
	 */
	public static $description = 'A summary of sponsorship grant awards during a given time period.';

	/**
	 * Report group.
	 *
	 * @var string
	 */
	public static $group = 'finance';

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
	 * Sponsorship_Grants constructor.
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

		$wordcamps = $this->get_wordcamps();
		$data      = array();

		foreach ( $wordcamps as $wordcamp_id => $wordcamp ) {
			if ( $this->wordcamp_id && $wordcamp_id !== $this->wordcamp_id ) {
				continue;
			}

			$currency = get_post_meta( $wordcamp_id, 'Global Sponsorship Grant Currency', true );
			$amount   = get_post_meta( $wordcamp_id, 'Global Sponsorship Grant Amount', true );

			if ( $currency && $amount ) {
				$data[] = array(
					'id'       => $wordcamp_id,
					'name'     => $wordcamp['name'],
					'currency' => $currency,
					'amount'   => $amount,
				);
			}
		}

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
		$compiled_data = $this->derive_totals_from_grant_amounts( $data );

		return $compiled_data;
	}

	/**
	 * Get a list of WordCamps that might have received a grant during the given date range.
	 *
	 * Camps are considered to have officially received their grants when their status changes to
	 * "Needs Contract to be Signed".
	 *
	 * Uses the WordCamp Status report to find camps that were set to the relevant status during the
	 * given date range.
	 *
	 * @return array
	 */
	protected function get_wordcamps() {
		$status_report = new Report\WordCamp_Status(
			$this->start_date->format( 'Y-m-d' ),
			$this->end_date->format( 'Y-m-d' ),
			'wcpt-needs-contract'
		);
		
		return $status_report->get_data();
	}

	/**
	 * Aggregate the number and amounts of Global Sponsorship Grants.
	 *
	 * @param array $grants The grants to aggregate.
	 *
	 * @return array
	 */
	protected function derive_totals_from_grant_amounts( $grants ) {
		$data = array(
			'grant_count'              => 0,
			'total_amount_by_currency' => array(),
			'converted_amounts'        => array(),
			'total_amount_converted'   => 0,
		);

		$currencies = array();

		foreach ( $grants as $grant ) {
			if ( ! in_array( $grant['currency'], $currencies, true ) ) {
				$data['total_amount_by_currency'][ $grant['currency'] ] = 0;
				$currencies[]                                           = $grant['currency'];
			}

			$data['grant_count'] ++;
			$data['total_amount_by_currency'][ $grant['currency'] ] += floatval( $grant['amount'] );
		}

		foreach ( $data['total_amount_by_currency'] as $currency => $amount ) {
			if ( 'USD' === $currency ) {
				$data['converted_amounts'][ $currency ] = $amount;
			} else {
				$data['converted_amounts'][ $currency ] = 0;

				$conversion = $this->xrt->convert( $amount, $currency, $this->end_date->format( 'Y-m-d' ) );

				if ( is_wp_error( $conversion ) ) {
					// Unsupported currencies are ok, but other errors should be surfaced.
					if ( 'unknown_currency' !== $conversion->get_error_code() ) {
						$this->merge_errors( $this->error, $conversion );
					}
				} else {
					$data['converted_amounts'][ $currency ] = $conversion->USD;
				}
			}
		}

		$data['total_amount_converted'] = array_reduce( $data['converted_amounts'], function( $carry, $item ) {
			return $carry + floatval( $item );
		}, 0 );

		return $data;
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

		if ( ! empty( $this->error->get_error_messages() ) ) {
			?>
			<div class="notice notice-error">
				<?php foreach ( $this->error->get_error_messages() as $message ) : ?>
					<?php echo wpautop( wp_kses_post( $message ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php
		} else {
			include Reports\get_views_dir_path() . 'html/sponsorship-grants.php';
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

		include Reports\get_views_dir_path() . 'report/sponsorship-grants.php';
	}
}