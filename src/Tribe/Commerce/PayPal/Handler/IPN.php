<?php

class Tribe__Tickets__Commerce__PayPal__Handler__IPN implements Tribe__Tickets__Commerce__PayPal__Handler__Interface {

	/**
	 * Set up hooks for IPN transaction communication
	 *
	 * @since TBD
	 */
	public function hook() {
		add_action( 'template_redirect', array( $this, 'check_response' ) );
	}

	/**
	 * Checks the request to see if payment data was communicated
	 *
	 * @since TBD
	 */
	public function check_response() {
		if ( empty( $_POST ) || ! isset( $_POST['txn_id'], $_POST['payer_email'] ) ) {
			return;
		}

		if ( ! $this->validate_transaction() ) {
			return;
		}

		/** @var Tribe__Tickets__Commerce__PayPal__Main $paypal */
		$paypal  = tribe( 'tickets.commerce.paypal' );
		/** @var Tribe__Tickets__Commerce__PayPal__Gateway $gateway */
		$gateway = tribe( 'tickets.commerce.paypal.gateway' );

		$data = wp_unslash( $_POST );

		$results = $gateway->parse_transaction( $data );

		$gateway->set_transaction_data( $results );

		$payment_status = trim( strtolower( $data['payment_status'] ) );

		/** @var Tribe__Tickets__Commerce__PayPal__Stati $stati */
		$stati = tribe( 'ticket.commerce.paypal.stati' );

		if ( $stati->is_complete_transaction_status( $payment_status ) ) {
			// since the purchase has completed, reset the invoice number
			$gateway->reset_invoice_number();
		}

		$paypal->generate_tickets( $payment_status, false );
	}

	/**
	 * Validates a PayPal transaction ensuring that it is authentic
	 *
	 * @since TBD
	 *
	 * @param string $transaction
	 *
	 * @return bool
	 */
	public function validate_transaction( $transaction = null ) {
		/**
		 * Allows short-circuiting the validation of a transaction with the PayPal server.
		 *
		 * Returning a non `null` value in  this will prevent any request for validation to
		 * the PayPal server from being sent.
		 *
		 * @since TBD
		 *
		 * @param bool       $validated
		 * @param array|null $transaction The transaction data if available; the transaction data
		 *                                might be in the $_POST superglobal.
		 */
		$validated = apply_filters( 'tribe_tickets_commerce_paypal_validate_transaction', null, $transaction );

		if ( null !== $validated ) {
			return $validated;
		}

		$gateway = tribe( 'tickets.commerce.paypal.gateway' );

		$body        = wp_unslash( $_POST );
		$body['cmd'] = '_notify-validate';

		$args = array(
			'body'        => $body,
			'httpversion' => '1.1',
			'timeout'     => 60,
			'compress'    => false,
			'decompress'  => false,
			'user-agent'  => 'EventTickets/' . Tribe__Tickets__Main::VERSION,
		);

		$response = wp_safe_remote_post( $gateway->get_cart_url(), $args );

		if (
			! is_wp_error( $response )
			&& 200 <= $response['response']['code']
			&& 300 > $response['response']['code']
			&& strstr( $response['body'], 'VERIFIED' )
		) {
			return true;
		}

		return false;
	}
}