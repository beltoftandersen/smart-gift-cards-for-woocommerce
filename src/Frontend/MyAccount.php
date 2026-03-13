<?php

namespace Bgcw\Frontend;

use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;
use Bgcw\Cart\CartHandler;

defined( 'ABSPATH' ) || exit;

class MyAccount {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'add_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'menu_item' ] );
		add_action( 'woocommerce_account_gift-cards_endpoint', [ __CLASS__, 'render' ] );
		add_filter( 'the_title', [ __CLASS__, 'endpoint_title' ], 10, 2 );
	}

	/**
	 * Register the rewrite endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'gift-cards', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add "Gift Cards" to My Account menu after Orders.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function menu_item( $items ) {
		$new_items = [];

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( $key === 'orders' ) {
				$new_items['gift-cards'] = __( 'Gift Cards', 'beltoft-gift-cards' );
			}
		}

		return $new_items;
	}

	/**
	 * Set page title for the endpoint.
	 *
	 * @param string $title Page title.
	 * @param int    $id    Post ID.
	 * @return string
	 */
	public static function endpoint_title( $title, $id = null ) {
		global $wp_query;

		if ( isset( $wp_query->query_vars['gift-cards'] )
			&& ! is_admin()
			&& is_main_query()
			&& in_the_loop()
			&& is_account_page()
		) {
			return __( 'Gift Cards', 'beltoft-gift-cards' );
		}
		return $title;
	}

	const PER_PAGE = 20;

	/**
	 * Render the Gift Cards page in My Account.
	 */
	public static function render() {
		$user_id = get_current_user_id();
		$email   = wp_get_current_user()->user_email;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
		$current_page = max( 1, isset( $_GET['gc_page'] ) ? absint( $_GET['gc_page'] ) : 1 );

		// SQL-level UNION, dedup, sort, and pagination.
		$result       = Repository::get_for_account( $user_id, $email, $current_page, self::PER_PAGE );
		$all_cards    = $result['cards'];
		$total_cards  = $result['total'];
		$total_pages  = max( 1, (int) ceil( $total_cards / self::PER_PAGE ) );
		$current_page = min( $current_page, $total_pages );

		// Batch-load all transactions in a single query.
		$card_ids    = array_map( function ( $gc ) { return $gc->id; }, $all_cards );
		$all_tx      = ! empty( $card_ids ) ? TransactionRepository::get_by_gift_card_ids( $card_ids ) : [];
		?>

		<div class="bgcw-myaccount">
			<?php if ( ! empty( $all_cards ) ) : ?>
				<table class="woocommerce-orders-table bgcw-cards-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Code', 'beltoft-gift-cards' ); ?></th>
							<th><?php esc_html_e( 'Initial Amount', 'beltoft-gift-cards' ); ?></th>
							<th><?php esc_html_e( 'Balance', 'beltoft-gift-cards' ); ?></th>
							<th><?php esc_html_e( 'Status', 'beltoft-gift-cards' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'beltoft-gift-cards' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_cards as $gc ) : ?>
							<tr class="bgcw-card-row" data-card-id="<?php echo esc_attr( $gc->id ); ?>">
								<td>
									<code><?php echo esc_html( CartHandler::mask_code( $gc->code ) ); ?></code>
									<button type="button" class="bgcw-toggle-transactions" title="<?php esc_attr_e( 'Show transactions', 'beltoft-gift-cards' ); ?>">&#9660;</button>
								</td>
								<td><?php echo wp_kses_post( wc_price( $gc->initial_amount, [ 'currency' => $gc->currency ] ) ); ?></td>
								<td><strong><?php echo wp_kses_post( wc_price( $gc->balance, [ 'currency' => $gc->currency ] ) ); ?></strong></td>
								<td>
									<?php
									$status_labels = [
										'active'   => __( 'Active', 'beltoft-gift-cards' ),
										'disabled' => __( 'Disabled', 'beltoft-gift-cards' ),
										'expired'  => __( 'Expired', 'beltoft-gift-cards' ),
										'redeemed' => __( 'Redeemed', 'beltoft-gift-cards' ),
									];
									$status_label = $status_labels[ $gc->status ] ?? ucfirst( $gc->status );
									?>
									<span class="bgcw-status bgcw-status--<?php echo esc_attr( $gc->status ); ?>">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</td>
								<td>
									<?php
									if ( empty( $gc->expires_at ) ) {
										esc_html_e( 'Never', 'beltoft-gift-cards' );
									} else {
										echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $gc->expires_at ) ) );
									}
									?>
								</td>
							</tr>
							<tr class="bgcw-transactions-row" style="display: none;" data-card-id="<?php echo esc_attr( $gc->id ); ?>">
								<td colspan="5">
									<?php
									$transactions = $all_tx[ $gc->id ] ?? [];
									if ( ! empty( $transactions ) ) :
									?>
										<table class="bgcw-transactions-table">
											<thead>
												<tr>
													<th><?php esc_html_e( 'Date', 'beltoft-gift-cards' ); ?></th>
													<th><?php esc_html_e( 'Type', 'beltoft-gift-cards' ); ?></th>
													<th><?php esc_html_e( 'Amount', 'beltoft-gift-cards' ); ?></th>
													<th><?php esc_html_e( 'Balance', 'beltoft-gift-cards' ); ?></th>
													<th><?php esc_html_e( 'Note', 'beltoft-gift-cards' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $transactions as $tx ) : ?>
													<tr>
														<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tx->created_at ) ) ); ?></td>
														<?php
$tx_labels = [
	'debit'  => __( 'Debit', 'beltoft-gift-cards' ),
	'credit' => __( 'Credit', 'beltoft-gift-cards' ),
	'refund' => __( 'Refund', 'beltoft-gift-cards' ),
];
$tx_label = $tx_labels[ $tx->type ] ?? ucfirst( $tx->type );
?>
														<td><span class="bgcw-tx-type bgcw-tx-type--<?php echo esc_attr( $tx->type ); ?>"><?php echo esc_html( $tx_label ); ?></span></td>
														<td>
															<?php
															$prefix = $tx->type === 'debit' ? '-' : '+';
															echo esc_html( $prefix ) . wp_kses_post( wc_price( $tx->amount, [ 'currency' => $gc->currency ] ) );
															?>
														</td>
														<td><?php echo wp_kses_post( wc_price( $tx->balance_after, [ 'currency' => $gc->currency ] ) ); ?></td>
														<td><?php echo esc_html( $tx->note ); ?></td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									<?php else : ?>
										<p><?php esc_html_e( 'No transactions yet.', 'beltoft-gift-cards' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $total_pages > 1 ) : ?>
					<nav class="woocommerce-pagination bgcw-pagination">
						<?php
						$base_url = wc_get_endpoint_url( 'gift-cards', '', wc_get_page_permalink( 'myaccount' ) );
						for ( $i = 1; $i <= $total_pages; $i++ ) :
							$url = add_query_arg( 'gc_page', $i, $base_url );
							if ( $i === $current_page ) :
								?>
								<span class="page-numbers current"><?php echo esc_html( $i ); ?></span>
							<?php else : ?>
								<a class="page-numbers" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $i ); ?></a>
								<?php
							endif;
						endfor;
						?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'You don\'t have any gift cards yet.', 'beltoft-gift-cards' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
