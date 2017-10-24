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
class Sponsor_Invoices extends Base {
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
	public static $description = 'A list of sponsor invoices for a given WordCamp.';

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
	 * @param int    $wordcamp_id The ID of a WordCamp post to retrieve invoices for.
	 * @param string $status      Optional. The status ID to filter for in the report.
	 * @param array  $options     {
	 *     Optional. Additional report parameters.
	 *     See Base::__construct for additional parameters.
	 *
	 *     @type
	 * }
	 */
	public function __construct( $wordcamp_id, $status = '', array $options = array() ) {
		// Sponsor Invoices specific options.
		$options = wp_parse_args( $options, array(
			'cache_data' => false,
		) );

		parent::__construct( $options );

		if ( $this->validate_wordcamp_id( $wordcamp_id ) ) {
			$this->wordcamp_id      = $wordcamp_id;
			$this->wordcamp_site_id = get_wordcamp_site_id( get_post( $wordcamp_id ) );
		}

		if ( 'any' === $status ) {
			$status = '';
		}

		if ( $status && $this->validate_status_input( $status ) ) {
			$this->status = $status;
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
	 * Validate the given status ID string.
	 *
	 * @param string $status The status ID to filter for in the report.
	 *
	 * @return bool True if the status ID is valid. Otherwise false.
	 */
	protected function validate_status_input( $status ) {
		if ( ! in_array( $status, array_keys( self::get_all_sponsor_invoice_statuses() ), true ) ) {
			$this->error->add( 'invalid_status', 'Please enter a valid status ID.' );

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
		$cache_key = parent::get_cache_key() . '_' . $this->wordcamp_id;

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

		// Maybe use cached data.
		$data = $this->maybe_get_cached_data();
		if ( is_array( $data ) ) {
			return $data;
		}

		/** @var \wpdb $wpdb */
		global $wpdb;

		$table_name = self::get_index_table_name();

		$where = "
			WHERE
				blog_id = %d
		";
		$args = array( $this->wordcamp_site_id );

		if ( $this->status ) {
			$where .= "
				AND
					status = %s
			";
			$args[] = $this->status;
		}

		$sql = "
			SELECT *
			FROM $table_name
		" . $where;

		$query = $wpdb->prepare( $sql, $args );

		$data = $wpdb->get_results( $query, ARRAY_A );

		// Maybe cache the data.
		$this->maybe_cache_data( $data );

		return $data;
	}

	/**
	 * A list of all possible Sponsor Invoice post statuses.
	 *
	 * Wrapper method to help minimize coupling with the WordCamp Payments plugin.
	 *
	 * If this needs to be used outside of this class, move it to utilities.php.
	 *
	 * @return array
	 */
	protected static function get_all_sponsor_invoice_statuses() {
		return WCB_Sponsor_Invoices\get_custom_statuses();
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
	 * @todo Remove this or finish it.
	 */
	public static function render_admin_page() {
		$wordcamp_id = filter_input( INPUT_POST, 'wordcamp-id' );
		$status      = filter_input( INPUT_POST, 'status' );
		$action      = filter_input( INPUT_POST, 'action' );

		$report = null;

		if ( 'run-report' === $action ) {
			$options = array(
				'cache_data' => false, // WP Admin is low traffic and more trusted, so turn off caching.
			);

			$report = new self( $wordcamp_id, $status, $options );
		}

		?>
		<div class="wrap">
			<h1>
				<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
				&raquo;
				<?php echo esc_html( self::$name ); ?>
			</h1>

			<p>
				<?php echo wp_kses_post( self::$description ); ?>
			</p>

			<form method="post" action="">
				<input type="hidden" name="action" value="run-report" />

				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row"><label for="wordcamp-id">WordCamp ID</label></th>
						<td><input type="number" id="wordcamp-id" name="wordcamp-id" value="<?php echo esc_attr( $wordcamp_id ) ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="status">Status</label></th>
						<td><input type="text" id="status" name="status" value="<?php echo esc_attr( $status ) ?>" /></td>
					</tr>
					</tbody>
				</table>

				<?php submit_button( 'Submit', 'primary', '' ); ?>
			</form>

			<?php if ( $report instanceof self ) : ?>
				<pre>
				<?php var_dump( $report->error->get_error_messages() ); ?>
				<?php var_dump( $report->get_data() ); ?>
				</pre>
			<?php endif; ?>
		</div>
	<?php
	}
}
