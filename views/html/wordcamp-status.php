<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\WordCamp_Status;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var array $active_camps */
/** @var array $inactive_camps */
/** @var array $statuses */

?>

<?php if ( ! empty( $active_camps ) ) : ?>
	<h3>WordCamp activity between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

	<?php foreach ( $active_camps as $active_camp ) : ?>
		<h4><?php echo esc_html( $active_camp['name'] ); ?> &ndash; <?php echo esc_html( $statuses[ $active_camp['latest_status'] ] ); ?></h4>
		<ul>
			<?php foreach ( $active_camp['logs'] as $log ) : ?>
				<li><?php
				echo date( 'Y-m-d H:i:s', $log['timestamp'] );
				echo ': ';
				echo esc_html( $log['message'] );
				?></li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( ! empty( $inactive_camps ) ) : ?>
	<h3>WordCamps with no activity between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></h3>

	<ul>
	<?php foreach ( $inactive_camps as $inactive_camp ) : ?>
		<li>
			<strong><?php echo esc_html( $inactive_camp['name'] ); ?></strong> &ndash;
			<?php echo esc_html( $statuses[ $inactive_camp['latest_status'] ] ); ?> &ndash;
			<em>Last activity: <?php echo date( 'Y-m-d H:i:s', $inactive_camp['latest_log']['timestamp'] ); ?></em>
		</li>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>
