<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\Report\WordCamp_Status;
defined( 'WPINC' ) || die();

use WordCamp\Reports\Report;

/** @var string $start_date */
/** @var string $end_date */
/** @var Report\WordCamp_Status|null $report */
?>

<div class="wrap">
	<h1>
		<?php
		printf(
			'%1$s &raquo; %2$s',
			'WordCamp Reports',
			Report\WordCamp_Status::NAME
		);
		?>
	</h1>

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
			</tbody>
		</table>

		<?php submit_button( 'Submit', 'primary' ); ?>
	</form>

	<?php if ( $report instanceof Report\WordCamp_Status ) : ?>
		<?php $report->render_html(); ?>
	<?php endif; ?>
</div>
