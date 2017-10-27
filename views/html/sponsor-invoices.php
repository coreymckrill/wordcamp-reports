<?php
/**
 * @package WordCamp\Reports
 */

namespace WordCamp\Reports\Views\HTML\Sponsor_Invoices;
defined( 'WPINC' ) || die();

/** @var \DateTime $start_date */
/** @var \DateTime $end_date */
/** @var string $wordcamp_name */
/** @var int $invoices_sent */
/** @var array $invoice_amounts */
/** @var int $payments_received */
/** @var array $payment_amounts */
?>

<h3>
	Sponsor Invoice activity
	<?php if ( $wordcamp_name ) : ?>
		for <?php echo esc_html( $wordcamp_name ); ?>
	<?php endif; ?>
	<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
		on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
	<?php else : ?>
		between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
	<?php endif; ?>
</h3>

<?php if ( $invoices_sent > 0 || $payments_received > 0 ) : ?>
	<ul class="ul-disc">
		<?php if ( $invoices_sent > 0 ) : ?>
			<li>Invoices sent: <?php echo esc_html( $invoices_sent ); ?></li>
		<?php endif; ?>
		<?php if ( ! empty( $invoice_amounts ) ) : ?>
			<li>
				<ul class="ul-disc">
					<?php foreach ( $invoice_amounts as $currency => $amount ) : ?>
						<li><?php echo esc_html( $currency ); ?>: <?php echo number_format_i18n( $amount ) ?></li>
					<?php endforeach; ?>
				</ul>
			</li>
		<?php endif; ?>
		<?php if ( $payments_received > 0 ) : ?>
			<li>Payments received: <?php echo esc_html( $payments_received ); ?></li>
		<?php endif; ?>
		<?php if ( ! empty( $payment_amounts ) ) : ?>
			<li>
				<ul class="ul-disc">
					<?php foreach ( $payment_amounts as $currency => $amount ) : ?>
						<li><?php echo esc_html( $currency ); ?>: <?php echo number_format_i18n( $amount ) ?></li>
					<?php endforeach; ?>
				</ul>
			</li>
		<?php endif; ?>
	</ul>
<?php else : ?>
	<p>
		No data
		<?php if ( $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) : ?>
			on <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?>
		<?php else : ?>
			between <?php echo esc_html( $start_date->format( 'M jS, Y' ) ); ?> and <?php echo esc_html( $end_date->format( 'M jS, Y' ) ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>
