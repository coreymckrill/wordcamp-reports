<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\Sponsor_Invoices;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var int $wordcamp_id */
/** @var Report\Sponsor_Invoices|null $report */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\Sponsor_Invoices::$name ); ?>
	</h1>

	<p>
		<?php echo wp_kses_post( Report\Sponsor_Invoices::$description ); ?>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'run-report', Report\Sponsor_Invoices::$slug . '-nonce' ); ?>

		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row"><label for="start-date">Start Date</label></th>
				<td><input type="date" id="start-date" name="start-date" value="<?php echo esc_attr( $start_date ) ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="end-date">End Date</label></th>
				<td><input type="date" id="end-date" name="end-date" value="<?php echo esc_attr( $end_date ) ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="wordcamp-id">WordCamp ID (optional)</label></th>
				<td><input type="number" id="wordcamp-id" name="wordcamp-id" value="<?php echo esc_attr( $wordcamp_id ) ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="refresh">Refresh results</label></th>
				<td><input type="checkbox" id="refresh" name="refresh" /></td>
			</tr>
			</tbody>
		</table>

		<?php submit_button( 'Show results', 'primary', 'action', false ); ?>
	</form>

	<?php if ( $report instanceof Report\Sponsor_Invoices ) : ?>
		<?php $report->render_html(); ?>
	<?php endif; ?>
</div>
