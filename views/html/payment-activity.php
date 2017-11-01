<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Payment_Activity;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var array $requests */
/** @var array $payments */
?>

<?php if ( $requests['vendor_payment_count'] || $requests['reimbursement_count'] ) : ?>
	<h3>
		Requested Payments
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
		<?php if ( $requests['vendor_payment_count'] ) : ?>
			<li>Vendor payments: <?php echo number_format_i18n( $requests['vendor_payment_count'] ) ?></li>
		<?php endif; ?>
		<?php if ( $requests['reimbursement_count'] ) : ?>
			<li>Reimbursements: <?php echo number_format_i18n( $requests['reimbursement_count'] ) ?></li>
		<?php endif; ?>
	</ul>

	<table class="striped">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Requested</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $requests['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td><?php echo number_format_i18n( $requests['total_amount_by_currency'][ $currency ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( $payments['vendor_payment_count'] || $payments['reimbursement_count'] ) : ?>
	<h3>
		Completed Payments
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
		<?php if ( $payments['vendor_payment_count'] ) : ?>
			<li>Vendor payments: <?php echo number_format_i18n( $payments['vendor_payment_count'] ) ?></li>
		<?php endif; ?>
		<?php if ( $payments['reimbursement_count'] ) : ?>
			<li>Reimbursements: <?php echo number_format_i18n( $payments['reimbursement_count'] ) ?></li>
		<?php endif; ?>
	</ul>

	<table class="striped">
		<thead>
		<tr>
			<td>Currency</td>
			<td>Total Amount Requested</td>
		</tr>
		</thead>
		<tbody>
		<?php foreach ( array_keys( $payments['total_amount_by_currency'] ) as $currency ) : ?>
			<tr>
				<td><?php echo esc_html( $currency ); ?></td>
				<td><?php echo number_format_i18n( $payments['total_amount_by_currency'][ $currency ] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( ! $requests['vendor_payment_count'] && ! $requests['reimbursement_count'] && ! $payments['vendor_payment_count'] && ! $payments['reimbursement_count'] ) : ?>
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