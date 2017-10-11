<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Status;
defined( 'WPINC' ) || die();

use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var string $status */
/** @var array  $statuses */
/** @var Report\WordCamp_Status|null $report */
?>

<div class="<?php echo esc_attr( Report\WordCamp_Status::SLUG ); ?>">
	<p>
		<?php echo wp_kses_post( Report\WordCamp_Status::DESCRIPTION ); ?>
	</p>

	<form method="get" action="">
		<input type="hidden" name="action" value="run-report" />

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

		<?php submit_button( 'Submit', 'primary', '' ); ?>
	</form>

	<?php if ( $report instanceof Report\WordCamp_Status ) : ?>
		<?php $report->render_html(); ?>
	<?php endif; ?>
</div>
