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

<div id="<?php echo esc_attr( Report\WordCamp_Status::SLUG ); ?>-report" class="report-container">
	<p class="report-description">
		<?php echo wp_kses_post( Report\WordCamp_Status::DESCRIPTION ); ?>
	</p>

	<form method="get" action="" class="report-form contact-form">
		<input type="hidden" name="action" value="run-report" />

		<div>
			<label for="start-date" class="grunion-field-label">Start Date <span>(required)</span></label>
			<input type="date" id="start-date" name="start-date" value="<?php echo esc_attr( $start_date ) ?>" />
		</div>

		<div>
			<label for="end-date" class="grunion-field-label">End Date <span>(required)</span></label>
			<input type="date" id="end-date" name="end-date" value="<?php echo esc_attr( $end_date ) ?>" />
		</div>

		<div>
			<label for="status" class="grunion-field-label">Status</label>
			<select id="status" name="status">
				<option value="any"<?php selected( ( ! $status || 'any' === $status ) ); ?>>Any</option>
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $status ); ?>><?php echo esc_attr( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div>
			<?php submit_button( 'Submit', 'primary', '' ); ?>
		</div>
	</form>

	<?php if ( $report instanceof Report\WordCamp_Status ) : ?>
		<div class="report-results">
			<?php $report->render_html(); ?>
		</div>
	<?php endif; ?>
</div>
