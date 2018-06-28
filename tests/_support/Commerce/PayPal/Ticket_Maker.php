<?php

namespace Tribe\Tickets\Test\Commerce\PayPal;


trait Ticket_Maker {

	/**
	 * Generates a PayPal ticket for a post.
	 *
	 * @param       int $post_id   The ID of the post this ticket should be related to.
	 * @param       int $price
	 * @param array     $overrides An array of values to override the default and random generation arguments.
	 *
	 * @return int The generated ticket post ID.
	 */
	protected function create_ticket( $post_id, $price, array $overrides = [] ) {
		$factory = $this->factory ?? $this->factory();

		$meta_input = isset( $overrides['meta_input'] ) && \is_array( $overrides['meta_input'] )
			? $overrides['meta_input']
			: array();

		unset( $overrides['meta_input'] );

		$ticket_id = $factory->post->create( array_merge(
				[
					'post_title'   => "Test PayPal ticket for {$post_id}",
					'post_content' => "Test PayPal ticket description for {$post_id}",
					'post_excerpt' => "Ticket PayPal ticket excerpt for {$post_id}",
					'post_type'    => tribe( 'tickets.commerce.paypal' )->ticket_object,
					'meta_input'   => array_merge( [
						'_tribe_tpp_for_event'                           => $post_id,
						'_price'                                         => $price,
						'_stock'                                         => 100,
						'_capacity'                                      => 100,
						'_manage_stock'                                  => 'yes',
						'_ticket_start_date'                             => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
						'_ticket_end_date'                               => date( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
						\Tribe__Tickets__Global_Stock::TICKET_STOCK_MODE => 'own',
					], $meta_input ),
				], $overrides )
		);

		return $ticket_id;
	}
}