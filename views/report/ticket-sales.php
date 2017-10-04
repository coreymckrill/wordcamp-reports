<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\Ticket_Sales;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var Report\Ticket_Sales|null $report */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\Ticket_Sales::NAME ); ?>
	</h1>

	<p>
		<?php echo wp_kses_post( Report\Ticket_Sales::DESCRIPTION ); ?>
	</p>

	<form method="post" action="">
		<input type="hidden" name="action" value="run-report" />
		<?php wp_nonce_field( 'run-report', Report\Ticket_Sales::SLUG . '-nonce' ); ?>

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
			</tbody>
		</table>

		<?php submit_button( 'Submit', 'primary' ); ?>
	</form>

	<?php if ( $report instanceof Report\Ticket_Sales ) : ?>
		<?php $report->render_html(); ?>
	<?php endif; ?>
</div>
