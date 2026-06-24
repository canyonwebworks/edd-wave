<?php

namespace Canyonwebworks\EDDWave\Classes;

use Exception;

/**
 * Handles Wave Apps OAuth 2.0 Authorization Code Flow
 */
final class OAuth {

	private $client_id;

	private $client_secret;

	private $redirect_uri;

	public function __construct() {

		$this->settings         = (array) get_option( 'edd_wave', [] );
		$this->client_id        = $this->settings['client_id'] ?? '';
		$this->client_secret    = $this->settings['client_secret'] ?? '';
		$this->redirect_uri     = admin_url( 'admin-post.php?action=edd_wave_oauth_callback' );

	}

	/**
	 * Get the OAuth Authorization URL
	 * Redirect users here to start the connection process
	 *
	 * @return string
	 */
	public function get_authorization_url() {

		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			return false;
		}

		// Generate a secure random state string (CSRF protection)
		$state = wp_generate_password( 32, false );
		set_transient( 'edd_wave_oauth_state', $state, HOUR_IN_SECONDS );

		$params = [
			'client_id'      => $this->client_id,
			'response_type'  => 'code',
			'scope'          => 'business:read transaction:write',
			'redirect_uri'   => urlencode( $this->redirect_uri ),
			'state'          => $state,
			'approval_prompt'=> 'auto',
		];

		return add_query_arg( $params, 'https://api.waveapps.com/oauth2/authorize/' );

	}

	/**
	 * Handle the OAuth callback from Wave
	 * Call this via admin-post action
	 *
	 * @param string $code
	 * @param string $state
	 * @return array|false Returns success data or false on error
	 */
	public function handle_callback( $code, $state ) {

		if ( empty( $code ) || empty( $state ) ) {
			return false;
		}

		// Verify CSRF state parameter
		$stored_state = get_transient( 'edd_wave_oauth_state' );
		if ( $state !== $stored_state ) {
			edd_debug_log( 'EDD Wave OAuth: State mismatch. Possible CSRF attack.' );
			return false;
		}

		// Clear the transient after use
		delete_transient( 'edd_wave_oauth_state' );

		// Exchange auth code for tokens
		try {
			$token_data = $this->exchange_code_for_token( $code );

			if ( is_wp_error( $token_data ) ) {
				edd_debug_log( 'EDD Wave OAuth: Token exchange failed: ' . $token_data->get_error_message() );
				return false;
			}

			// Store tokens in plugin settings
			$this->save_tokens( $token_data );
			return true;

		} catch ( Exception $e ) {
			edd_debug_log( 'EDD Wave OAuth Exception: ' . $e->getMessage() );
			return false;
		}

	}

	/**
	 * Exchange authorization code for access token
	 *
	 * @param string $code
	 * @return array|WP_Error
	 */
	private function exchange_code_for_token( $code ) {

		$body = http_build_query( [
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'code'          => $code,
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => $this->redirect_uri,
		] );

		$response = wp_remote_post( 'https://api.waveapps.com/oauth2/token/', [
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => $body,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && isset( $body['access_token'] ) ) {
			return $body;
		}

		$error_msg = $body['message'] ?? 'Unknown error';
		return new \WP_Error( 'wave_oauth_failed', $error_msg );

	}

	/**
	 * Save tokens to plugin options
	 *
	 * @param array $data Response from OAuth endpoint
	 */
	private function save_tokens( $data ) {

		// Update main settings option
		$this->settings['access_token']     = $data['access_token'];
		$this->settings['refresh_token']    = $data['refresh_token'] ?? '';
		$this->settings['token_expires_at'] = time() + (int) ( $data['expires_in'] ?? 3600 );
		$this->settings['business_id']      = $data['businessId'] ?? '';
		$this->settings['user_id']          = $data['userId'] ?? '';

		update_option( 'edd_wave', $this->settings );

	}

	/**
	 * Refresh expired access token
	 *
	 * @return bool True if refresh successful, false otherwise
	 */
	public function refresh_token() {
		if ( empty( $this->settings['refresh_token'] ) ) {
			return false;
		}

		$body = http_build_query( [
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'refresh_token' => $this->settings['refresh_token'],
			'grant_type'    => 'refresh_token',
			'redirect_uri'  => $this->redirect_uri,
		] );

		$response = wp_remote_post( 'https://api.waveapps.com/oauth2/token/', [
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => $body,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			edd_debug_log( 'EDD Wave Token Refresh Error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 403 === $code ) {
			// Per docs: 403 means subscription is no longer active
			edd_debug_log( 'EDD Wave OAuth: 403 Forbidden - Business subscription may be inactive.' );
			return false;
		}

		if ( 200 === $code && isset( $body['access_token'] ) ) {
			$this->save_tokens( $body );
			return true;
		}

		edd_debug_log( 'EDD Wave Token Refresh Failed: ' . print_r( $body, true ) );
		return false;
	}

	/**
	 * Check if current token is valid or needs refreshing
	 *
	 * @return bool
	 */
	public function has_valid_token() {

		if ( empty( $this->settings['access_token'] ) ) {
			return false;
		}

		// If stored expiry time hasn't passed yet, assume valid
		if ( time() < ( $this->settings['token_expires_at'] ?? 0 ) ) {
			return true;
		}

		// Try to refresh before saying it's invalid
		return $this->refresh_token();
	}

}