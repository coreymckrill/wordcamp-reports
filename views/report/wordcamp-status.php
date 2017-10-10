<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Status;
defined( 'WPINC' ) || die();

use WordCamp\Reports;
use WordCamp\Reports\Report;

/** @var array  $statuses */
/** @var string $start_date */
/** @var string $end_date */
/** @var string $status */
/** @var Report\WordCamp_Status|null $report */
/** @var \WP_Error|null $error */
?>

<div class="wrap">
	<h1>
		<a href="<?php echo esc_attr( Reports\get_page_url() ); ?>">WordCamp Reports</a>
		&raquo;
		<?php echo esc_html( Report\WordCamp_Status::NAME ); ?>
	</h1>

	<p>
		<?php echo wp_kses_post( Report\WordCamp_Status::DESCRIPTION ); ?>
	</p>

	<form method="post" action="">
		<input type="hidden" name="action" value="run-report" />
		<?php wp_nonce_field( 'run-report', Report\WordCamp_Status::SLUG . '-nonce' ); ?>

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
					<th scope="row"><label for="status">Status</label></th>
					<td>
						<select id="status" name="status">
							<option value=""<?php selected( null, $status ); ?>>Any</option>
							<?php foreach ( $statuses as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $status ); ?>><?php echo esc_attr( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( 'Submit', 'primary' ); ?>
	</form>

	<?php if ( $error instanceof \WP_Error ) : ?>
		<div class="notice notice-error">
			<p>
				Error:
				<?php echo wp_kses_post( $error->get_error_message() ); ?>
			</p>
		</div>
	<?php elseif ( $report instanceof Report\WordCamp_Status ) : ?>
		<?php $report->render_html(); ?>
	<?php endif; ?>
</div>