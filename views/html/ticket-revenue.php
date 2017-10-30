<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Ticket_Revenue;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var array $wpcs */
/** @var array $non_wpcs */
/** @var array $total */
?>

<?php if ( ! empty( $wpcs['net_revenue_by_currency'] ) ) : ?>
	<h3>
		WPCS Ticket Revenue
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<li>Tickets sold: <?php echo number_format_i18n( $wpcs['tickets_sold'] ); ?></li>
		<li>Tickets refunded: <?php echo number_format_i18n( $wpcs['tickets_refunded'] ); ?></li>
	</ul>

	<table class="widefat striped">
		<thead>
			<tr>
				<td>Currency</td>
				<td>Gross Revenue</td>
				<td>Discounts</td>
				<td>Refunds</td>
				<td>Net Revenue</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_keys( $wpcs['net_revenue_by_currency'] ) as $currency ) : ?>
				<tr>
					<td><?php echo esc_html( $currency ); ?></td>
					<td><?php echo number_format_i18n( $wpcs['gross_revenue_by_currency'][ $currency ] ); ?></td>
					<td><?php echo number_format_i18n( $wpcs['discounts_by_currency'][ $currency ] ); ?></td>
					<td><?php echo number_format_i18n( $wpcs['amount_refunded_by_currency'][ $currency ] ); ?></td>
					<td><?php echo number_format_i18n( $wpcs['net_revenue_by_currency'][ $currency ] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $non_wpcs['net_revenue_by_currency'] ) ) : ?>
	<h3>
		Non-WPCS Ticket Revenue
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<li>Tickets sold: <?php echo number_format_i18n( $non_wpcs['tickets_sold'] ); ?></li>
		<li>Tickets refunded: <?php echo number_format_i18n( $non_wpcs['tickets_refunded'] ); ?></li>
	</ul>

	<table class="widefat striped">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Gross Revenue</td>
			<td>Discounts</td>
			<td>Refunds</td>
			<td>Net Revenue</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $non_wpcs['net_revenue_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td><?php echo number_format_i18n( $non_wpcs['gross_revenue_by_currency'][ $currency ] ); ?></td>
				<td><?php echo number_format_i18n( $non_wpcs['discounts_by_currency'][ $currency ] ); ?></td>
				<td><?php echo number_format_i18n( $non_wpcs['amount_refunded_by_currency'][ $currency ] ); ?></td>
				<td><?php echo number_format_i18n( $non_wpcs['net_revenue_by_currency'][ $currency ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $wpcs['net_revenue_by_currency'] ) && ! empty( $non_wpcs['net_revenue_by_currency'] ) ) : ?>
	<h3>
		Total Ticket Revenue
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</h3>

	<ul>
		<li>Tickets sold: <?php echo number_format_i18n( $total['tickets_sold'] ); ?></li>
		<li>Tickets refunded: <?php echo number_format_i18n( $total['tickets_refunded'] ); ?></li>
	</ul>

	<table class="widefat striped">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Gross Revenue</td>
			<td>Discounts</td>
			<td>Refunds</td>
			<td>Net Revenue</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $total['net_revenue_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td><?php echo number_format_i18n( $total['gross_revenue_by_currency'][ $currency ] ); ?></td>
				<td><?php echo number_format_i18n( $total['discounts_by_currency'][ $currency ] ); ?></td>
				<td><?php echo number_format_i18n( $total['amount_refunded_by_currency'][ $currency ] ); ?></td>
				<td><?php echo number_format_i18n( $total['net_revenue_by_currency'][ $currency ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( empty( $total['net_revenue_by_currency'] ) ) : ?>
	<p>
		No data
		<?php if ( $wordcamp_name ) : ?>
			for <?php echo esc_html( $wordcamp_name ); ?>
		<?php endif; ?>
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
