<?php


/**
 * Class Tribe__Ticket__Cache__Transient_Cache
 *
 * Stores and return costly site-wide information.
 */
class Tribe__Tickets__Cache__Transient_Cache extends Tribe__Tickets__Cache__Abstract_Cache implements Tribe__Tickets__Cache__Cache_Interface {


	/**
	 * Resets all caches.
	 */

	public function reset_all() {
		foreach ( $this->keys as $key ) {
			delete_transient( __CLASS__ . $key );
		}
	}

	/**
	 * Returns array of post IDs of posts that have no tickets assigned.
	 *
	 * Please note that the list is aware of supported types.
	 *
	 * @return array
	 */
	public function posts_without_tickets() {
		$ids = get_transient( __CLASS__ . __METHOD__ );

		if ( false === $ids ) {
			$ids = $this->fetch_posts_without_tickets();

			set_transient( __CLASS__ . __METHOD__, $ids, $this->expiration );
		}

		return $ids;
	}

	/**
	 * Returns array of post IDs of posts that have at least one ticket assigned.
	 *
	 * Please note that the list is aware of supported types.
	 *
	 * @return array
	 */
	public function posts_with_tickets() {
		$ids = get_transient( __CLASS__ . __METHOD__ );

		if ( false === $ids ) {
			$ids = $this->fetch_posts_with_tickets();

			set_transient( __CLASS__ . __METHOD__, $ids, $this->expiration );
		}

		return $ids;
	}
}
