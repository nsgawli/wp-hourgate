<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PSR-4: the file is named after its class.
namespace HourGate\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the time an order can be fulfilled (OW-043), read only through
 * `OrderMeta` (HPOS-safe, strict parsing):
 *
 * - a "Scheduled for" row in the order totals, after the payment method:
 *   WooCommerce's customer and admin emails (HTML and plain), the thank-you
 *   page and My Account → View order all print these rows;
 * - the admin order screen, "Scheduled for" and "Placed while the store was
 *   closed" under the order details;
 * - a note above the order table in the customer emails sent before the
 *   order is fulfilled (OW-062, `NOTE_EMAILS`), when the setting is on.
 *
 * Lives in Core because emails are sent from admin, cron and REST requests
 * too. Boot registers hooks only; each callback reads meta already loaded
 * with the order, so it costs no query. An error is logged and the order is
 * shown without the row or note.
 */
class OrderDisplay {

	/**
	 * Emails that get the scheduled-order note: the customer's emails sent
	 * before the order is fulfilled. Admin emails show the totals row only.
	 *
	 * @var string[]
	 */
	const NOTE_EMAILS = array( 'customer_processing_order', 'customer_on_hold_order', 'customer_invoice' );

	/**
	 * Register the hooks. No queries.
	 *
	 * @return void
	 */
	public static function register_hooks() {

		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'add_totals_row' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'admin_box' ), 10, 1 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email_note' ), 10, 4 );
	}

	/**
	 * Print the scheduled-order note in a customer email (OW-062): one
	 * paragraph in HTML, one line in plain text, escaped here.
	 *
	 * @param mixed $order         Order.
	 * @param mixed $sent_to_admin Whether the email goes to the store.
	 * @param mixed $plain_text    Whether the email is plain text.
	 * @param mixed $email         The `WC_Email`.
	 * @return void
	 */
	public static function email_note( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {

		try {
			$note = self::note( $order, $sent_to_admin, $email );

			if ( '' === $note ) {
				return;
			}

			if ( $plain_text ) {
				echo wp_strip_all_tags( $note ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email: tags stripped, nothing is HTML.

				return;
			}

			echo '<p class="hourgate-scheduled-note" style="margin:0 0 16px;overflow-wrap:break-word;word-break:break-word;">' . nl2br( esc_html( $note ) ) . '</p>';
		} catch ( \Throwable $e ) {
			OrderingPolicy::report_display( $e );
		}
	}

	/**
	 * The note for an email, plain text, or '' for none: only a customer
	 * email in `NOTE_EMAILS`, only with the setting on, only for an order with
	 * a valid scheduled time (never a time it wasn't scheduled for).
	 *
	 * @param mixed $order         Order.
	 * @param mixed $sent_to_admin Whether the email goes to the store.
	 * @param mixed $email         The `WC_Email`.
	 * @return string
	 */
	private static function note( $order, $sent_to_admin, $email ) {

		if ( $sent_to_admin || ! $email instanceof \WC_Email || ! in_array( $email->id, self::NOTE_EMAILS, true ) || ! Behaviour::scheduled_order_note() ) {
			return '';
		}

		$ts = OrderMeta::get_scheduled_for( $order );

		if ( null === $ts ) {
			return '';
		}

		// Only `{scheduled_for}`: the closed message's tokens map to themselves, so they stay as typed.
		$note = OrderingPolicy::fill(
			Behaviour::scheduled_order_note_text(),
			array(
				'next_open'      => '{next_open}',
				'closures_label' => '{closures_label}',
				'scheduled_for'  => OrderingPolicy::format_time( $ts ),
			)
		);

		/**
		 * The scheduled-order note in a customer email (OW-062), plain text,
		 * `{scheduled_for}` already replaced. Return '' to show none. It is
		 * escaped when printed, so markup shows as text.
		 *
		 * @param string   $note  The note.
		 * @param WC_Order $order Order.
		 * @param WC_Email $email Email.
		 */
		$note = apply_filters( 'hourgate_scheduled_order_note', $note, $order, $email );

		return is_string( $note ) ? trim( $note ) : '';
	}

	/**
	 * Add the "Scheduled for" row after the payment method (or last when there is none).
	 *
	 * @param mixed $total_rows Rows WooCommerce built, keyed (`cart_subtotal`, `payment_method`, ...).
	 * @param mixed $order      Order.
	 * @return mixed
	 */
	public static function add_totals_row( $total_rows, $order ) {

		if ( ! is_array( $total_rows ) ) {
			return $total_rows;
		}

		try {
			$rows = self::rows( $order );

			if ( ! $rows ) {
				return $total_rows;
			}

			if ( ! array_key_exists( 'payment_method', $total_rows ) ) {
				return array_merge( $total_rows, $rows );
			}

			$result = array();

			foreach ( $total_rows as $key => $row ) {
				$result[ $key ] = $row;

				if ( 'payment_method' === $key ) {
					$result = array_merge( $result, $rows );
				}
			}

			return $result;
		} catch ( \Throwable $e ) {
			OrderingPolicy::report_display( $e );

			return $total_rows;
		}
	}

	/**
	 * The admin order screen: the scheduled time and the "placed while closed" flag.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public static function admin_box( $order ) {

		try {
			$rows = self::rows( $order );

			if ( OrderMeta::is_placed_while_closed( $order ) ) {
				$rows['placed_while_closed'] = array(
					'label' => __( 'Placed while the store was closed', 'hourgate' ),
					'value' => __( 'Yes', 'hourgate' ),
				);
			}

			if ( ! $rows ) {
				return;
			}

			echo '<div class="form-field form-field-wide hourgate-order-details">';

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! isset( $row['label'], $row['value'] ) || ! is_scalar( $row['label'] ) || ! is_scalar( $row['value'] ) ) {
					continue;
				}

				echo '<p><strong>' . esc_html( (string) $row['label'] ) . '</strong> ' . esc_html( (string) $row['value'] ) . '</p>';
			}

			echo '</div>';
		} catch ( \Throwable $e ) {
			OrderingPolicy::report_display( $e );
		}
	}

	/**
	 * The rows to show for an order, keyed (`scheduled_for`), or none.
	 *
	 * @param mixed $order Order.
	 * @return array<string, array{label: string, value: string}>
	 */
	private static function rows( $order ) {

		$rows = array();
		$ts   = OrderMeta::get_scheduled_for( $order );

		if ( null !== $ts ) {
			$rows['scheduled_for'] = array(
				'label' => __( 'Scheduled for:', 'hourgate' ),
				'value' => OrderingPolicy::format_time( $ts ),
			);
		}

		/**
		 * Rows HourGate adds to an order's totals and to the admin order screen.
		 *
		 * Keyed like WooCommerce's totals rows, each `array( 'label' => string, 'value' => string )`
		 * as plain text (WooCommerce's templates and the admin box escape it). Rows that aren't
		 * well formed are skipped by the admin box.
		 *
		 * @param array    $rows  Rows, keyed.
		 * @param WC_Order $order Order.
		 */
		$filtered = apply_filters( 'hourgate_order_display_rows', $rows, $order );

		return is_array( $filtered ) ? self::clean( $filtered ) : array();
	}

	/**
	 * Keep only rows with a string label and value, so a bad filter result can't break a template.
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function clean( array $rows ) {

		$clean = array();

		foreach ( $rows as $key => $row ) {
			if ( is_array( $row ) && isset( $row['label'], $row['value'] ) && is_string( $row['label'] ) && is_string( $row['value'] ) ) {
				$clean[ $key ] = array(
					'label' => $row['label'],
					'value' => $row['value'],
				);
			}
		}

		return $clean;
	}
}
