<?php

namespace Bgcw\Email;

use Bgcw\GiftCard\Repository;

defined( 'ABSPATH' ) || exit;

class GiftCardDeliveryEmail extends \WC_Email {

	/**
	 * Gift card data object.
	 *
	 * @var object|null
	 */
	public $gift_card = null;

	/**
	 * Order object.
	 *
	 * @var \WC_Order|null
	 */
	public $order = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'bgcw_gift_card_delivery';
		$this->customer_email = true;
		$this->title          = __( 'Gift Card Delivery', 'beltoft-gift-cards' );
		$this->description    = __( 'Sent to the recipient when a gift card is purchased and created.', 'beltoft-gift-cards' );

		$this->template_html  = 'emails/gift-card-delivery.php';
		$this->template_plain = 'emails/plain/gift-card-delivery.php';
		$this->template_base  = BGCW_PATH . 'templates/';

		$this->placeholders = [
			'{sender_name}' => '',
			'{amount}'      => '',
			'{site_title}'  => $this->get_blogname(),
		];

		parent::__construct();

		// Trigger on gift card creation (must be after parent constructor).
		add_action( 'bgcw_gift_card_created', [ $this, 'trigger' ], 10, 2 );
	}

	/**
	 * Get default email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'You received a {amount} gift card from {sender_name}!', 'beltoft-gift-cards' );
	}

	/**
	 * Get default email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( "You've received a gift card!", 'beltoft-gift-cards' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param int            $gift_card_id Gift card ID.
	 * @param \WC_Order|null $order        Order object (null for manual creation).
	 * @return bool
	 */
	public function trigger( $gift_card_id, $order = null ) {
		$this->setup_locale();

		$gc = Repository::find( $gift_card_id );
		if ( ! $gc ) {
			$this->restore_locale();
			return false;
		}

		/**
		 * Filter whether the gift card email should be sent immediately.
		 *
		 * Returning false allows Pro to defer delivery (e.g. scheduled delivery).
		 *
		 * @param bool            $should_send  Whether to send now.
		 * @param int             $gift_card_id Gift card ID.
		 * @param \WC_Order|null  $order        Order object.
		 */
		if ( ! apply_filters( 'bgcw_should_send_email_now', true, $gift_card_id, $order ) ) {
			$this->restore_locale();
			return false;
		}

		$this->gift_card = $gc;
		$this->order     = $order;
		$this->recipient = $gc->recipient_email;

		$this->placeholders['{sender_name}'] = $gc->sender_name ?: __( 'Someone special', 'beltoft-gift-cards' );
		$this->placeholders['{amount}']      = html_entity_decode( wp_strip_all_tags( wc_price( $gc->initial_amount, [ 'currency' => $gc->currency ] ) ), ENT_QUOTES, 'UTF-8' );

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			$this->restore_locale();
			return false;
		}

		$result = $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();

		return $result;
	}

	/**
	 * Get HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		/**
		 * Filter the HTML email template path.
		 *
		 * @param string      $template  Template relative path.
		 * @param object|null $gift_card Gift card data object.
		 */
		$template = apply_filters( 'bgcw_email_template_html', $this->template_html, $this->gift_card );

		return $this->render_template( $template, false );
	}

	/**
	 * Get plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return $this->render_template( $this->template_plain, true );
	}

	/**
	 * Render a template with shared variables.
	 *
	 * @param string $template   Template file.
	 * @param bool   $plain_text Whether plain text.
	 * @return string
	 */
	private function render_template( $template, $plain_text ) {
		$gift_card = $this->get_gift_card_for_render();

		return wc_get_template_html(
			$template,
			[
				'gift_card'     => $gift_card,
				'order'         => $this->order,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => $plain_text,
				'email'         => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get gift card for rendering. Falls back to dummy for previews.
	 *
	 * @return object
	 */
	private function get_gift_card_for_render() {
		if ( $this->gift_card ) {
			return $this->gift_card;
		}

		// Preview mode: create dummy data.
		return (object) [
			'id'              => 0,
			'code'            => 'GIFT-ABCD-EFGH-JKLM',
			'initial_amount'  => '50.00',
			'balance'         => '50.00',
			'currency'        => get_woocommerce_currency(),
			'sender_name'     => __( 'John Doe', 'beltoft-gift-cards' ),
			'sender_email'    => 'john@example.com',
			'recipient_name'  => __( 'Jane Doe', 'beltoft-gift-cards' ),
			'recipient_email' => 'jane@example.com',
			'message'         => __( 'Happy Birthday! Enjoy this gift card.', 'beltoft-gift-cards' ),
			'order_id'        => 0,
			'status'          => 'active',
			'expires_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			'created_at'      => current_time( 'mysql' ),
		];
	}

	/**
	 * Initialise settings form fields.
	 */
	public function init_form_fields() {
		$placeholder_text = sprintf(
			/* translators: %s: list of available placeholders */
			__( 'Available placeholders: %s', 'beltoft-gift-cards' ),
			'<code>{sender_name}, {amount}, {site_title}</code>'
		);

		$this->form_fields = [
			'enabled'    => [
				'title'   => __( 'Enable/Disable', 'beltoft-gift-cards' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'beltoft-gift-cards' ),
				'default' => 'yes',
			],
			'subject'    => [
				'title'       => __( 'Subject', 'beltoft-gift-cards' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			],
			'heading'    => [
				'title'       => __( 'Email heading', 'beltoft-gift-cards' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			],
			'email_type' => [
				'title'       => __( 'Email type', 'beltoft-gift-cards' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'beltoft-gift-cards' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}
}
