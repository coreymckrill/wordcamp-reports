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

$asterisk2 = false;
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

	<p class="description">These numbers are based on the assumption that every camp using a currency that is supported by PayPal is running its ticket sales through WPCS.</p>

	<ul>
		<li>Tickets sold: <?php echo number_format_i18n( $wpcs['tickets_sold'] ); ?></li>
		<li>Tickets refunded: <?php echo number_format_i18n( $wpcs['tickets_refunded'] ); ?></li>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
			<tr>
				<td>Currency</td>
				<td>Gross Revenue</td>
				<td>Discounts</td>
				<td>Refunds</td>
				<td>Net Revenue</td>
				<td>Estimated Value in USD *</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_keys( $wpcs['net_revenue_by_currency'] ) as $currency ) : ?>
				<tr>
					<td><?php echo esc_html( $currency ); ?></td>
					<td class="number"><?php echo number_format_i18n( $wpcs['gross_revenue_by_currency'][ $currency ] ); ?></td>
					<td class="number"><?php echo number_format_i18n( $wpcs['discounts_by_currency'][ $currency ] ); ?></td>
					<td class="number"><?php echo number_format_i18n( $wpcs['amount_refunded_by_currency'][ $currency ] ); ?></td>
					<td class="number"><?php echo number_format_i18n( $wpcs['net_revenue_by_currency'][ $currency ] ); ?></td>
					<td class="number">
						<?php echo number_format_i18n( $wpcs['converted_net_revenue'][ $currency ] ); ?>
						<?php if ( $wpcs['net_revenue_by_currency'][ $currency ] > 0 && $wpcs['converted_net_revenue'][ $currency ] === 0 ) : $asterisk2 = true; ?>
							**
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td>Total: </td>
				<td class="number total"><?php echo number_format_i18n( $wpcs['total_converted_revenue'] ); ?></td>
			</tr>
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

	<p class="description">These numbers are based on the assumption that the currencies used in these transactions are not supported by PayPal, and thus the ticket sales are not through WPCS.</p>

	<ul>
		<li>Tickets sold: <?php echo number_format_i18n( $non_wpcs['tickets_sold'] ); ?></li>
		<li>Tickets refunded: <?php echo number_format_i18n( $non_wpcs['tickets_refunded'] ); ?></li>
	</ul>

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Gross Revenue</td>
			<td>Discounts</td>
			<td>Refunds</td>
			<td>Net Revenue</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $non_wpcs['net_revenue_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $non_wpcs['gross_revenue_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $non_wpcs['discounts_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $non_wpcs['amount_refunded_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $non_wpcs['net_revenue_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $non_wpcs['converted_net_revenue'][ $currency ] ); ?>
					<?php if ( $non_wpcs['net_revenue_by_currency'][ $currency ] > 0 && $non_wpcs['converted_net_revenue'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $non_wpcs['total_converted_revenue'] ); ?></td>
		</tr>
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

	<table class="striped widefat but-not-too-wide">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Gross Revenue</td>
			<td>Discounts</td>
			<td>Refunds</td>
			<td>Net Revenue</td>
			<td>Estimated Value in USD *</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $total['net_revenue_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td class="number"><?php echo number_format_i18n( $total['gross_revenue_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $total['discounts_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $total['amount_refunded_by_currency'][ $currency ] ); ?></td>
				<td class="number"><?php echo number_format_i18n( $total['net_revenue_by_currency'][ $currency ] ); ?></td>
				<td class="number">
					<?php echo number_format_i18n( $total['converted_net_revenue'][ $currency ] ); ?>
					<?php if ( $total['net_revenue_by_currency'][ $currency ] > 0 && $total['converted_net_revenue'][ $currency ] === 0 ) : $asterisk2 = true; ?>
						**
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td>Total: </td>
			<td class="number total"><?php echo number_format_i18n( $total['total_converted_revenue'] ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! empty( $total['net_revenue_by_currency'] ) ) : ?>
	<p class="description">* Estimate based on exchange rates for <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?></p>
	<?php if ( $asterisk2 ) : ?>
		<p class="description">** Currency exchange rate not available.</p>
	<?php endif; ?>
<?php else : ?>
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
