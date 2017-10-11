<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Report;
defined( 'WPINC' ) || die();

use WordCamp\Reports;

/**
 * Class WordCamp_Status
 *
 * @package WordCamp\Reports\Report
 */
class WordCamp_Status extends Base {
	/**
	 * Report name.
	 */
	const NAME = 'WordCamp Status';

	/**
	 * Report slug.
	 */
	const SLUG = 'wordcamp-status';

	/**
	 * Report description.
	 */
	const DESCRIPTION = 'A summary of WordCamp status changes during a given time period.';

	/**
	 * Shortcode tag for outputting the public report form.
	 */
	const SHORTCODE_TAG = 'wordcamp_status_report';

	/**
	 * The start of the date range for the report.
	 *
	 * @var \DateTime|null
	 */
	public $start_date = null;

	/**
	 * The end of the date range for the report.
	 *
	 * @var \DateTime|null
	 */
	public $end_date = null;

	/**
	 * The status to filter for in the report.
	 *
	 * @var string
	 */
	public $status = '';

	/**
	 * Additional report parameters.
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * A container object to hold error messages.
	 *
	 * @var \WP_Error
	 */
	public $error = null;

	/**
	 * WordCamp_Status constructor.
	 *
	 * @param string        $start_date The start of the date range for the report.
	 * @param string        $end_date   The end of the date range for the report.
	 * @param string        $status     Optional. The status ID to filter for in the report.
	 * @param array         $options    {
	 *     Optional. Additional report parameters.
	 *
	 *     @type \DateTime     $earliest_start The earliest date that can be used for the start of the date range.
	 *     @type \DateInterval $max_interval   The max interval of time between the start and end dates.
	 *     @type array         $status_subset  A list of valid status IDs.
	 *     @type bool          $cache_data     True to look for cached data and cache the generated data set.
	 * }
	 */
	public function __construct( $start_date, $end_date, $status = '', array $options = array() ) {
		$this->error = new \WP_Error();

		$this->options = wp_parse_args( $options, array(
			'earliest_start' => null,
			'max_interval'   => null,
			'status_subset'  => array(),
			'cache_data'     => true,
		) );

		if ( $this->validate_date_range_inputs( $start_date, $end_date ) ) {
			$this->start_date = new \DateTime( $start_date );
			$this->end_date   = new \DateTime( $end_date );

			// If the end date doesn't have a specific time, make sure
			// the entire day is included.
			if ( '00:00:00' === $this->end_date->format( 'H:i:s' ) ) {
				$this->end_date->setTime( 23, 59, 59 );
			}
		}

		if ( $status && $this->validate_status_input( $status ) ) {
			$this->status = $status;
		}
	}

	/**
	 * Validate the given strings for the start and end dates.
	 *
	 * @param string $start_date The start of the date range for the report.
	 * @param string $end_date   The end of the date range for the report.
	 *
	 * @return bool True if the parameters are valid. Otherwise false.
	 */
	protected function validate_date_range_inputs( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			$this->error->add( 'invalid_date', 'Please enter a valid start and end date.' );

			return false;
		}

		try {
			$start_date = new \DateTimeImmutable( $start_date ); // Immutable so methods don't modify the original object.
		} catch ( \Exception $e ) {
			$this->error->add( 'invalid_date', 'Please enter a valid start date.' );

			return false;
		}

		// Check for start date boundary.
		if ( $this->options['earliest_start'] instanceof \DateTime && $start_date < $this->options['earliest_start'] ) {
			$this->error->add( 'start_date_too_old', sprintf(
				'Please enter a start date of %s or later.',
				$this->options['earliest_start']->format( 'Y-m-d' )
			) );

			return false;
		}

		try {
			$end_date = new \DateTimeImmutable( $end_date ); // Immutable so methods don't modify the original object.
		} catch ( \Exception $e ) {
			$this->error->add( 'invalid_date', 'Please enter a valid end date.' );

			return false;
		}

		// No negative date intervals.
		if ( $start_date > $end_date ) {
			$this->error->add( 'negative_date_interval', 'Please enter an end date that is the same as or after the start date.' );

			return false;
		}

		// Check for date interval boundary.
		if ( $this->options['max_interval'] instanceof \DateInterval ) {
			$max_end_date = $start_date->add( $this->options['max_interval'] );

			if ( $end_date > $max_end_date ) {
				$this->error->add( 'exceeds_max_date_interval', sprintf(
					'Please enter an end date that is no more than %s days after the start date.',
					$start_date->diff( $max_end_date )->format( '%a' )
				) );

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate the given status ID string.
	 *
	 * @param string $status The status ID to filter for in the report.
	 *
	 * @return bool True if the status ID is valid. Otherwise false.
	 */
	protected function validate_status_input( $status ) {
		if ( is_array( $this->options['status_subset'] ) && ! empty( $this->options['status_subset'] ) ) {
			if ( ! in_array( $status, $this->options['status_subset'], true ) ) {
				$this->error->add( 'invalid_status', 'Please enter a valid status ID.' );

				return false;
			}

			return true;
		}

		if ( ! in_array( $status, array_keys( self::get_all_statuses() ), true ) ) {
			$this->error->add( 'invalid_status', 'Please enter a valid status ID.' );

			return false;
		}

		return true;
	}

	/**
	 * Filter: Set the locale to en_US.
	 *
	 * Some translated strings in the wcpt plugin are used here for comparison and matching. To ensure
	 * that the matching happens correctly, we need need to prevent these strings from being converted
	 * to a different locale.
	 *
	 * @return string
	 */
	public function set_locale_to_en_US() {
		return 'en_US';
	}

	/**
	 * Generate a cache key based on the input parameters.
	 *
	 * @return string
	 */
	protected function get_cache_key() {
		$cache_key = self::SLUG . '_' . $this->start_date->getTimestamp() . '-' . $this->end_date->getTimestamp();

		if ( $this->status ) {
			$cache_key .= '_' . $this->status;
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

		// Maybe check the cache.
		if ( false !== $this->options['cache_data'] ) {
			$cache_key = $this->get_cache_key();
			$data      = get_transient( $cache_key );

			if ( is_array( $data ) ) {
				return $data;
			}
		}

		// Ensure status labels can match status log messages.
		add_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		$wordcamp_posts = $this->get_wordcamp_posts();
		$statuses       = self::get_all_statuses();
		$data           = array();

		foreach ( $wordcamp_posts as $wordcamp ) {
			$logs = $this->get_wordcamp_status_logs( $wordcamp );

			// Trim log entries occurring after the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $entry['timestamp'] > $this->end_date->getTimestamp() ) {
					return false;
				}

				return true;
			} );

			// Skip if there is no log activity before the end of the date range.
			if ( empty( $logs ) ) {
				continue;
			}

			$latest_log    = end( $logs );
			$latest_status = $this->get_log_status_result( $latest_log );
			reset( $logs );

			// Trim log entries occurring before the date range.
			$logs = array_filter( $logs, function( $entry ) {
				if ( $entry['timestamp'] < $this->start_date->getTimestamp() ) {
					return false;
				}

				return true;
			} );

			// Skip if there is no log activity in the date range and the camp has an inactive status.
			if ( empty( $logs ) && ( in_array( $latest_status, self::get_inactive_statuses(), true ) || ! $latest_status ) ) {
				continue;
			}

			// Skip if there is no log entry with a resulting status that matches the status filter.
			if ( $this->status && $latest_status !== $this->status ) {
				$filtered = array_filter( $logs, function( $entry ) use ( $statuses ) {
					return preg_match( '/' . preg_quote( $statuses[ $this->status ], '/' ) . '$/', $entry['message'] );
				} );

				if ( empty( $filtered ) ) {
					continue;
				}
			}

			if ( $site_id = get_wordcamp_site_id( $wordcamp ) ) {
				$name = get_wordcamp_name( $site_id );
			} else {
				$name = get_the_title( $wordcamp );
			}

			$data[ $wordcamp->ID ] = array(
				'name'          => $name,
				'logs'          => $logs,
				'latest_log'    => $latest_log,
				'latest_status' => $latest_status,
			);
		}

		// Remove the temporary locale change.
		remove_filter( 'locale', array( $this, 'set_locale_to_en_US' ) );

		// Maybe cache the data.
		if ( false !== $this->options['cache_data'] ) {
			$cache_key  = $this->get_cache_key();
			$expiration = DAY_IN_SECONDS;

			// Expire the cache sooner if the data includes the current day.
			if ( date_create( 'now' )->format( 'Y-m-d' ) === $this->end_date->format( 'Y-m-d' ) ) {
				$expiration = HOUR_IN_SECONDS;
			}

			set_transient( $cache_key, $data, $expiration );
		}

		return $data;
	}

	/**
	 * Get all current WordCamp posts.
	 *
	 * @return array
	 */
	protected function get_wordcamp_posts() {
		$post_args = array(
			'post_type'           => \WCPT_POST_TYPE_ID,
			'post_status'         => 'any',
			'posts_per_page'      => 9999,
			'nopaging'            => true,
			'no_found_rows'       => false,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'ASC',
			'meta_query'          => array(
				'relation' => 'OR',
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '=',
					'value'   => '',
				),
				array(
					// Don't include WordCamps that happened more than 3 months ago.
					'key'     => 'Start Date (YYYY-mm-dd)',
					'compare' => '>=',
					'value'   => strtotime( '-3 months', $this->start_date->getTimestamp() ),
					'type'    => 'NUMERIC',
				),
			),
		);

		return get_posts( $post_args );
	}

	/**
	 * Retrieve the log of status changes for a particular WordCamp.
	 *
	 * @param \WP_Post $wordcamp A WordCamp post.
	 *
	 * @return array
	 */
	protected function get_wordcamp_status_logs( \WP_Post $wordcamp ) {
		$log_entries = get_post_meta( $wordcamp->ID, '_status_change' );

		if ( ! empty( $log_entries ) ) {
			// Sort log entries in chronological order.
			usort( $log_entries, function( $a, $b ) {
				if ( $a['timestamp'] === $b['timestamp'] ) {
					return 0;
				}

				return ( $a['timestamp'] > $b['timestamp'] ) ? 1 : -1;
			} );

			return $log_entries;
		}

		return array();
	}

	/**
	 * Determine the ending status of a particular status change event.
	 *
	 * E.g. for this event:
	 *
	 *     Needs Vetting → More Info Requested
	 *
	 * The ending status would be "More Info Requested".
	 *
	 * @param array $log_entry A status change log entry.
	 *
	 * @return string
	 */
	protected function get_log_status_result( $log_entry ) {
		if ( isset( $log_entry['message'] ) ) {
			$pieces = explode( ' &rarr; ', $log_entry['message'] );

			if ( isset( $pieces[1] ) ) {
				return $this->get_status_id_from_name( $pieces[1] );
			}
		}

		return '';
	}

	/**
	 * Given the ID of a WordCamp status, determine the ID string.
	 *
	 * @param string $status_name A WordCamp status name.
	 *
	 * @return string
	 */
	protected function get_status_id_from_name( $status_name ) {
		$statuses = array_flip( self::get_all_statuses() );

		if ( isset( $statuses[ $status_name ] ) ) {
			return $statuses[ $status_name ];
		}

		return '';
	}

	/**
	 * A list of all possible WordCamp post statuses.
	 *
	 * Wrapper method to help minimize coupling with the WCPT plugin.
	 *
	 * @return array
	 */
	protected static function get_all_statuses() {
		return \WordCamp_Loader::get_post_statuses();
	}

	/**
	 * A list of status IDs for statuses that indicate a camp is not active.
	 *
	 * @return array
	 */
	protected static function get_inactive_statuses() {
		return array(
			'wcpt-rejected',
			'wcpt-cancelled',
			'wcpt-scheduled',
			'wcpt-closed',
		);
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
		$status     = $this->status;

		$active_camps = array_filter( $data, function( $wordcamp ) {
			if ( ! empty( $wordcamp['logs'] ) ) {
				return true;
			}

			return false;
		} );

		$inactive_camps = array_filter( $data, function( $wordcamp ) {
			if ( empty( $wordcamp['logs'] ) ) {
				return true;
			}

			return false;
		} );

		$statuses = self::get_all_statuses();

		if ( ! empty( $this->error->get_error_messages() ) ) {
			?>
			<div class="notice notice-error">
				<?php foreach ( $this->error->get_error_messages() as $message ) : ?>
					<?php echo wpautop( wp_kses_post( $message ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php
		} else {
			include Reports\get_views_dir_path() . 'html/wordcamp-status.php';
		}
	}

	/**
	 * Render the page for this report in the WP Admin.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		$start_date = filter_input( INPUT_POST, 'start-date' );
		$end_date   = filter_input( INPUT_POST, 'end-date' );
		$status     = filter_input( INPUT_POST, 'status' );
		$action     = filter_input( INPUT_POST, 'action' );
		$nonce      = filter_input( INPUT_POST, self::SLUG . '-nonce' );
		$statuses   = self::get_all_statuses();

		$report = null;

		if ( 'run-report' === $action && wp_verify_nonce( $nonce, 'run-report' ) ) {
			$options = array(
				'earliest_start' => new \DateTime( '2007-11-17' ), // Date of first WordCamp in the system.
				'cache_data'     => false,
			);

			$report = new self( $start_date, $end_date, $status, $options );
		}

		include Reports\get_views_dir_path() . 'report/wordcamp-status.php';
	}

	/**
	 * Determine whether to render the public report form.
	 *
	 * @todo Add front end styles.
	 * @todo Maybe restrict this form to logged in users?
	 */
	public static function handle_shortcode() {
		if ( 'page' === get_post_type() ) {
			self::render_public_page();
		}
	}

	/**
	 * Render the page for this report on the front end.
	 *
	 * @return void
	 */
	public static function render_public_page() {
		$start_date = filter_input( INPUT_GET, 'start-date' );
		$end_date   = filter_input( INPUT_GET, 'end-date' );
		$status     = filter_input( INPUT_GET, 'status' );
		$action     = filter_input( INPUT_GET, 'action' );
		$statuses   = self::get_all_statuses();

		$report = null;

		if ( 'run-report' === $action ) {
			$options = array(
				'earliest_start' => new \DateTime( '2007-11-17' ), // Date of first WordCamp in the system.
				'max_interval'   => new \DateInterval( 'P1Y' ), // 1 year. See http://php.net/manual/en/dateinterval.construct.php.
			);

			$report = new self( $start_date, $end_date, $status, $options );
		}

		include Reports\get_views_dir_path() . 'public/wordcamp-status.php';
	}
}
