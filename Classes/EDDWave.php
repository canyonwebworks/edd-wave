<?php

namespace CanyonWebworks\EDDWave\Classes;

use Canyonwebworks\EDDWave\Classes\OAuth;
use EDD\Gateways\PayPal;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

final class EDDWave {

	private $settings;

	private $business_id;

	private $payment_completed_date;

	private $access_token;

	private $full_access_token;

	private $anchor_account_id = '';

	/**
	 * Single instance of the EDDWave class
	 *
	 * @var EDDWave
	 */
	protected static $_instance = null;

	/**
	 * Instantiator
	 *
	 * @return EDDWave instance of EDDWave
	 */
	public static function instance() {

		if ( ! isset( self::$_instance ) && ! ( self::$_instance instanceof EDDWave ) ) {
			self::$_instance = new EDDWave;
		}
		return self::$_instance;

	}

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->settings = (array) get_option( 'edd_wave', [] );

		$this->access_token = $this->settings['access_token'] ?? '';

		$this->full_access_token = $this->settings['full_access_token'] ?? '';

		$this->business_id = $this->settings['business_id'] ?? '';

		$this->payment_completed_date = date( 'Y-m-d' );

		add_action( 'init', [ $this, 'init' ] );

		if ( empty( $this->access_token ) && empty( $this->full_access_token ) ) {
			edd_debug_log( 'EDD + Wave warning: a Wave access token must be saved in settings to reach API.' );
			return;
		}

		// Catch PayPal payment details via the EDD Gateway API
		add_action( 'edd_after_payment_actions', [ $this, 'edd_after_payment_actions' ], 10, 3 );

		/**
		 * Catch Stripe charge details
		 * Charge.updated occurs whenever a charge description or metadata is updated, or upon an asynchronous capture.
		 */
		add_action( 'edds_stripe_event_charge.updated', [ $this, 'stripe_event_charge_updated' ] );

		/**
		 * Catch Stripe refund details
		 * Refund.created occurs when a refund is created
		 */
		add_action( 'edds_stripe_event_refund.created', [ $this, 'stripe_event_refund_created' ] );

	}

	/**
	 * Fire up the admin settings class
	 *
	 * @return void
	 */
	public function init() {

		require_once __DIR__ . '/OAuth.php';

		if ( is_admin() ) {
			if ( current_user_can( 'manage_shop_settings' ) ) {
				require_once EDD_WAVE_PLUGIN_DIR . '/Classes/Settings.php';
				new Settings;

				// Register OAuth handlers
				add_action( 'admin_post_edd_wave_oauth_connect', [ $this, 'oauth_connect_redirect' ] );
				add_action( 'admin_post_edd_wave_oauth_callback', [ $this, 'oauth_callback_handler' ] );
				add_action( 'admin_post_nopriv_edd_wave_oauth_callback', [ $this, 'oauth_callback_handler' ] ); // Just in case

			}
			load_plugin_textdomain( 'edd-wave', false, 'edd-wave/lang' );

		}

	}

	/**
	 * Gather data after PayPal payment complete
	 * Runs off CRON (but not our CRON)
	 *
	 * @param int $payment_id
	 * @param object $payment
	 * @param object $customer
	 *
	 * @return void
	 */
	public function edd_after_payment_actions( int $payment_id, object $payment, object $customer ) {

		$payment_method = edd_get_payment_gateway( $payment_id ); // e.g. 'stripe' or 'paypal_commerce'
		if ( 'paypal_commerce' !== $payment_method && 'paypal' !== $payment_method ) {
			// Only continue if it's a PayPal payment
			return;
		}

		$paypal_order_id = edd_get_payment_meta( $payment_id, 'paypal_order_id', true );

		try {
			$api = new PayPal\API();
			// https://developer.paypal.com/docs/api/orders/v2/#orders_get
			$response = $api->make_request( 'v2/checkout/orders/' . urlencode( $paypal_order_id ), [], [], 'GET' );

			if ( empty( $response->id ) || 'completed' !== strtolower( $response->status ) ) {
				throw new Exception( 'Error: $response->id not found or $response->status not "COMPLETED"' );
			}

			if ( ! $response->purchase_units ) {
				throw new Exception( 'Error: Bad (unusable) response from PayPal API.' );
				return;
			}

			if ( ! $payment->downloads ) {
				throw new Exception( 'Error: EDD payment strangely doesn\'t seem to include any downloads.' );
				return;
			}

		} catch ( Exception $e ) {
			edd_debug_log( 'EDD + Wave threw exception: ' . $e->getMessage() );
			return;

		}

		$this->anchor_account_id = $this->settings['paypal_anchor_account_id'];

		$line_items = $this->get_line_items( $payment );

		if ( isset( $response->create_time ) ) {
			$this->payment_completed_date = date( 'Y-m-d', strtotime( $response->create_time ) );
		}

		/**
		 * Cart (download) details
		 * get array of lineItems for Wave API inputMoneyTransactionCreate
		 *
		 */
		foreach( $response->purchase_units as $purchase_unit ) {

			$net = floatval( $purchase_unit->payments->captures[0]->seller_receivable_breakdown->net_amount->value );

			$merchant_fee = $purchase_unit->payments->captures[0]->seller_receivable_breakdown->paypal_fee->value ?? null;

			if ( $merchant_fee ) {

				/**
				 * Add merchant fees (debit) to line items
				 */
				$line_items[] = [
					'accountId' => $this->settings['paypal_fees_account_id'],
					'amount'    => $merchant_fee,
					'balance'   => 'DEBIT'
				];

			}

		}

		if ( empty( $line_items ) ) {
			edd_debug_log( 'Wave + EDD error: no data collected to send to Wave!' );
			return;
		}

		// PayPal method ID passed:
		$this->create_wave_transaction( $payment, $line_items, $net );

	}

	/**
	 * Listen for Stripe charge updated event, which will have all the charge data we
	 * need to proceed. Any earlier and we might be missing data such as merchant fee.
	 *
	 * @param object $event
	 *
	 */
	public function stripe_event_charge_updated( $event ) {

		$charge = $event->data->object;

		if ( 'charge' !== $charge->object
			|| ! $charge->captured
			|| $charge->disputed
			|| $charge->refunded
			|| $charge->amount_refunded > 0
		) {
			return;
		}

		$this->payment_completed_date = date( 'Y-m-d', $charge->created );
		$this->anchor_account_id = $this->settings['stripe_anchor_account_id'];

		try {
			$balance_transaction = edds_api_request( 'BalanceTransaction', 'retrieve', $charge->balance_transaction );
		} catch ( Exception $e ) {
			edd_debug_log( 'Wave + EDD error: API Balance Transaction fetch failed. Aborting' );
			return;
		}

		$net = floatval( $balance_transaction->net / 100 ); // Comes from Stripe as integer

		// Get EDD Payment ID from Stripe Charge ID
		$payment_id = edd_get_purchase_id_by_transaction_id( $charge->id );
		$payment = new \EDD_Payment( $payment_id );

		// Get EDD order line items array
		$line_items = $this->get_line_items( $payment );
		if ( empty( $line_items ) ) {
			edd_debug_log( 'Wave + EDD error: no data collected to send to Wave!' );
			return;
		}

		// Add merchant fees (debit) to line items
		$merchant_fee = floatval( $balance_transaction->fee / 100 );
		if ( ! empty( $merchant_fee ) ) {
			$line_items[] = [
				'accountId' => $this->settings['stripe_fees_account_id'],
				'amount'    => $merchant_fee,
				'balance'   => 'DEBIT'
			];
		} else {
			edd_debug_log( 'Wave + EDD: Because we are missing the merchant fee, it will be impossible to send a balanced transaction to Wave. Aborting.' );
			return;
		}

		$this->create_wave_transaction( $payment, $line_items, $net );

	}

	/**
	 * Listen for Stripe charge updated event, which will have all the charge data we
	 * need to proceed. Any earlier and we might be missing data such as merchant fee.
	 *
	 * @param object $event
	 *
	 */
	public function stripe_event_refund_created( $event ) {

		// https://docs.stripe.com/api/refunds/object
		$refund = $event->data->object;

		if ( 'refund' !== $refund->object || 'succeeded' !== $refund->status ) {
			return;
		}

		$this->payment_completed_date = date( 'Y-m-d', $refund->created );
		$this->anchor_account_id = $this->settings['stripe_anchor_account_id'];

		// Get EDD Payment ID from Stripe Charge ID
		$payment_id = edd_get_purchase_id_by_transaction_id( $refund->charge );
		$payment = new \EDD_Payment( $payment_id );

		$line_items = [];
		if ( isset( $payment->cart_details ) && 1 === count( $payment->cart_details ) ) {
			// EDD price variation ID
			$price_id = $payment->cart_details[0]['item_number']['options']['price_id'] ?? '';
			$line_items['accountId'] = $this->get_wave_account( $payment->cart_details[0]['id'], $price_id );
		} else {
			// Get default income account
			$wave_accounts   = eddwave()->get_wave_accounts( 'INCOME' );
			$default_account = '';
			foreach ( $wave_accounts['data']['business']['accounts']['edges'] as $edge ) {
				if ( 'uncategorized income' === strtolower( $edge['node']['name'] ) ) {
					$default_account = $edge['node']['id'];
				}
			}
			if ( empty( $default_account ) ) {
				edd_debug_log( 'EDD + Wave error: Could not get Wave account ID to apply refund to.' );
				return;
			}
			$line_items['accountId'] = $default_account;
		}

		$line_items['amount'] = $net = (float) $refund->amount / 100;
		$line_items['balance'] = 'DEBIT';

		$this->create_wave_transaction( $payment, $line_items, $net );

	}

	/**
	 * Start a lineItem array for the Wave Apps moneyTransactionCreate API mutation
	 *
	 * @param object $payment EDD Payment
	 *
	 * @return array
	 */
	private function get_line_items( $payment ) {

		$line_items = [];

		if ( empty( $this->payment_completed_date ) && isset( $payment->completed_date ) ) {
			$this->payment_completed_date = date( 'Y-m-d', strtotime( $payment->completed_date ) );
		}

		// Get an array of line items "lineItems" for Wave API moneyTransactionCreate
		foreach ( $payment->cart_details as $item ) {

			if ( ! isset( $item['id'] ) ) {
				edd_debug_log( 'EDD + Wave: A cart item was skipped in the foreach() loop, due to missing item ID.' );
				continue;
			}

			if ( isset( $item['item_number']['options']['is_upgrade'] ) && true === $item['item_number']['options']['is_upgrade'] ) {
				edd_debug_log( 'EDD + Wave: EDD Software License upgrade purchase not logged in Wave for payment ID: ' . $payment->ID );
				continue;
			}

			// EDD price variation ID
			$price_id = $item['item_number']['options']['price_id'] ?? '';

			// Product (credit)
			if ( ! empty( $item['rate'] ) ) {

				// Favorable currency Exchange
				if ( 1 > $item['rate'] ) {

					$line_items[] = [
						'accountId' => $this->get_wave_account( $item['id'], $price_id ),
						'amount'    => $item['subtotal'] / $item['rate'],
						'balance'   => 'CREDIT'
					];

				} else if ( 1 < $item['rate'] ) {

					$line_items[] = [
						'accountId' => $this->get_wave_account( $item['id'], $price_id ),
						'amount'    => $item['subtotal'] * $item['rate'],
						'balance'   => 'CREDIT'
					];

				} else { // unlikely but possible: we have a perfect 1:1 exchange

					if ( isset( $item['subtotal'] ) ) {
						$line_items[] = [
							'accountId' => $this->get_wave_account( $item['id'], $price_id ),
							'amount'    => $item['subtotal'],
							'balance'   => 'CREDIT'
						];
					}

				}

			// 1:1 rate (no currency exchange)
			} else {

				if ( isset( $item['subtotal'] ) ) {
					$line_items[] = [
						'accountId' => $this->get_wave_account( $item['id'], $price_id ),
						'amount'    => $item['subtotal'],
						'balance'   => 'CREDIT'
					];
				}

			}

			// Fees (credit)
			if ( ! empty( $item['fees'] ) ) {
				foreach ( $item['fees'] as $fee ) {
					$line_items[] = [
						'accountId' => $this->settings['purchase_fees'],
						'amount'    => $fee['amount'],
						'balance'   => 'CREDIT',
					];
				}
			}

			// Tax (debit)
			if ( isset( $item['tax'] ) && 0 < $item['tax'] ) {
				$line_items[] = [
					'accountId' => $this->settings['tax_liability'],
					'amount'    => $item['tax'],
					'balance'   => 'CREDIT',
				];
			}

			// Discounts (debit)
			if ( isset( $item['discount'] ) && 0 < $item['discount'] ) {
				$line_items[] = [
					'accountId' => $this->settings['expense_discounts'],
					'amount'    => $item['discount'],
					'balance'   => 'DEBIT',
				];
			}

			// Note: Merchant fees still need to be added in order to balance transaction

		}

		return $line_items;

	}

	/**
	 * Create a Wave Transaction
	 *
	 * @param  object $payment
	 * @param  array  $line_items
	 * @param  float  $net
	 *
	 * @return void
	 */
	public function create_wave_transaction( object $payment, array $line_items, $net ) {

		// Gather data to send
		$data = wp_json_encode([

			'query' => 'mutation ($inputMoneyTransactionCreate: MoneyTransactionCreateInput!) { moneyTransactionCreate(input: $inputMoneyTransactionCreate) { didSucceed, inputErrors { code, message, path } } }',
			'variables' => [

				'inputMoneyTransactionCreate' => [

					'businessId'  => $this->business_id,
					'externalId'  => strval( $payment->ID ),
					'date'        => $this->payment_completed_date,
					'description' => apply_filters( 'edd_wave_transaction_description', 'EDD #' . $payment->ID . ' from ' . $payment->first_name . ' ' . $payment->last_name, $payment ),
					'notes'       => apply_filters( 'edd_wave_transaction_notes', 'Email: ' . $payment->email, $payment ),

					/**
					 * ANCHOR
					 * The bank/credit card account from which the transaction takes place is the Anchor
					 * The "anchor" is an asset (cash and bank) or liability (credit card / LoC) : in our case, the merchant account
					 * https://web.archive.org/web/20200811134512/https://community.waveapps.com/discussion/6415/what-exactly-is-the-the-anchor-account-in-a-moneytransaction
					 * Direction: DEPOSIT is effectively a withdrawal on an expense account
					 */
					'anchor' => [
						'accountId' => $this->anchor_account_id,
						'amount'    => $net,
						'direction' => 'DEPOSIT'
					],
					'lineItems' => $line_items

				] // end 'inputMoneyTransactionCreate'

			] // end 'variables'

		]); // end $data

		$createdPayment = $this->http_request( $data );

		if ( $createdPayment && true == $createdPayment['data']['moneyTransactionCreate']['didSucceed'] ) {
			return; // we are successful/finished, no need to log error
		}

		edd_debug_log( 'EDD + Wave: Payment not created at Wave.' );

	}

	/**
	 * Make HTTP request to Wave API
	 *
	 * @param  string $data HTTP request body
	 *
	 * @return array|boolean
	 */
	private function http_request( string $data ) {

		$access_token = ! empty( $this->settings['access_token'] ) ? $this->settings['access_token'] : $this->full_access_token;

		$headers = [
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type' => 'application/json',
		];

		$response = wp_remote_post( 'https://gql.waveapps.com/graphql/public', [
				'timeout'   => 20, // timeout set low because we are trying to not disrupt checkout flow
				'headers'   => $headers,
				'body'      => $data,
			]
		);

		if ( is_wp_error( $response ) ) {
			edd_debug_log( 'EDD + Wave: HTTP request error' . print_r( $response->get_error_message(), true ) );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code ) {
			return json_decode( $response['body'], true );
		} else {
			edd_debug_log( 'EDD + Wave: HTTP status code was ' . $response_code );
		}
		return false;

	}

	/**
	 * Get Wave account from product name
	 *
	 * @param string $id Numeric
	 * @param string $price_id Numeric, for EDD variable pricing
	 *
	 * @return boolean|string Wave ID
	 */
	protected function get_wave_account( string $id, string $price_id ) {

		if ( ! is_numeric( $id ) ) {
			return false;
		}

		if ( empty( $price_id ) ) {
			// Price ID is only going to be empty for EDD downloads without variation pricing
			return get_post_meta( $id, '_wave_income_account', true );
		} else {
			$_variable_income_account = get_post_meta( $id, '_wave_variable_income_account', true );
			if ( ! empty( $_variable_income_account ) ) {
				// $price_id will be the array key for the price variation
				return $_variable_income_account[$price_id];
			}
		}

		return $this->get_uncategorized_account();

	}

	/**
	 * Get businesses from Wave Apps
	 *
	 * @return array
	 */
	private function get_businesses() {

		$data = wp_json_encode([ 'query' => 'query { businesses { edges { node { id name } } } }' ]);

		return $this->http_request( $data );

	}

	/**
	 * Public wrapper for to get businesses from Wave Apps
	 *
	 * @return array
	 */
	public function get_wave_businesses() {

		return $this->get_businesses();

	}

	/**
	 * Get accounts (by type) from Wave Apps
	 *
	 * @param string $types ASSET, EQUITY, EXPENSE, INCOME, LIABILITY
	 *     Could also be JSON-formatted array, e.g. [ 'INCOME', 'EXPENSE' ]
	 *
	 * @return object|bool
	 */
	private function get_accounts( string $type = "INCOME" ) {

		if ( empty( $this->business_id ) ) {
			return false;
		}

		$data = wp_json_encode( [
			'query' => 'query ($businessId: ID!, $page: Int!, $pageSize: Int!, $types: [AccountTypeValue!] ) {
				business(id: $businessId) {
					id
					accounts(page: $page, pageSize: $pageSize, types: $types) {
						pageInfo { currentPage totalPages totalCount }
						edges {
							node {
								id
								name
								type { name value }
								subtype { name value }
								isArchived
							}
						}
					}
				}
			}',
			'variables' => [
				'businessId'    => $this->business_id,
				'types'         => $type,
				'page'          => 1,
				'pageSize'      => 500,
			]
		]);

		return $this->http_request( $data );

	}

	/**
	 * Public wrapper to get Wave accounts
	 *
	 * @param string $types ASSET, EQUITY, EXPENSE, INCOME, LIABILITY
	 *     Could also be JSON-formatted array, e.g. [ 'INCOME', 'EXPENSE' ]
	 *
	 * @return object|bool
	 */
	public function get_wave_accounts( string $type = "INCOME" ) {

		return $this->get_accounts( $type );

	}

	/**
	 * Get default (uncategorized) Wave income account ID
	 *
	 * @return string|bool
	 */
	protected function get_uncategorized_account() {

		$wave_accounts = $this->get_accounts( 'INCOME' );

		if ( ! isset( $wave_accounts['data']['business']['accounts']['edges'] ) ) {
			edd_debug_log( 'EDD Wave: Failed to fetch accounts from Wave.' );
			return false;
		}

		$target_id = false;

		foreach ( $wave_accounts['data']['business']['accounts']['edges'] as $edge ) {
			$node = $edge['node'];

			if ( strtolower( trim( $node['name'] ) ) === 'uncategorized income' ) {
				$target_id = $node['id'];
				break;
			}
		}
		return $target_id;

	}

	public function oauth_connect_redirect() {

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( 'Unauthorized' );
		}

		$oauth = new OAuth();
		$url   = $oauth->get_authorization_url();

		if ( $url ) {
			wp_redirect( $url );
			exit;
		} else {
			wp_die( 'OAuth not configured properly. Please check Client ID and Secret in settings.' );
		}

	}

	public function oauth_callback_handler() {

		if ( ! isset( $_GET['code'], $_GET['state'] ) ) {
			wp_die( 'Invalid OAuth response' );
		}

		$oauth = new OAuth();
		$success = $oauth->handle_callback( sanitize_text_field( $_GET['code'] ), sanitize_text_field( $_GET['state'] ) );

		if ( $success ) {
			add_settings_error( 'edd_wave', 'oauth_success', 'Connected successfully!', 'success' );
			wp_redirect( admin_url( 'edit.php?post_type=download&page=wave-edd&settings-updated=true' ) );
		} else {
			add_settings_error( 'edd_wave', 'oauth_fail', 'Connection failed. See logs.', 'error' );
			wp_redirect( admin_url( 'edit.php?post_type=download&page=wave-edd&settings-updated=true' ) );
		}
		exit;
	}

}