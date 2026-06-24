<?php

namespace CanyonWebworks\EDDWave\Classes;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

class Settings {

	private $settings;

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->settings = get_option( 'edd_wave', [] );

		// Add link to EDD Wave settings from WP plugins page
		add_filter( 'plugin_action_links_edd-wave/edd-wave.php', [ $this, 'add_settings_link' ] );

		// Add link to EDD Wave settings from the admin menu
		// add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		// Add link to EDD Wave settings from the EDD "Downloads" admin submenu
		add_action( 'admin_menu', [ $this, 'admin_submenu' ] );

		// Enqueue JavaScript that runs the admin settings mapped tables
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 10 );

		// Save settings. Admin-side post including action value "edd_wave_settings" fires this hook
		add_action( 'admin_post_edd_wave_settings', [ $this, 'save_settings' ] );

	}

	/**
	 * Enqueue admin-end scripts
	 *
	 * @param string $hook
	 */
	public function admin_enqueue_scripts( $hook ) {

		if ( 'download_page_wave-edd' !== $hook && 'toplevel_page_edd-wave' !== $hook ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'edd-wave', plugins_url( 'assets/js/admin' . $suffix . '.js', EDD_WAVE_PLUGIN_FILE ), ['jquery'], EDD_WAVE_VERSION, true );
		wp_enqueue_style( 'edd-wave', plugins_url( 'assets/css/admin' . $suffix . '.css', EDD_WAVE_PLUGIN_FILE ), [], EDD_WAVE_VERSION, 'screen' );

	}

	/**
	 * Add settings link to WP plugin listing
	 *
	 * @param array $links
	 * @return array
	 */
	public function add_settings_link( $links ) {

		$action_links = [
			'settings' => '<a href="' . admin_url( 'edit.php?post_type=download&page=wave-edd' ) . '" aria-label="' . esc_attr__( 'View EDD Wave settings', 'edd-wave' ) . '">' . esc_html__( 'Settings', 'edd-wave' ) . '</a>',
		];
		return array_merge( $action_links, $links );

	}

	/**
	 * Add settings to the WP Admin menu (under Settings)
	 *
	 * @return void
	 */
	public function admin_menu() {

		// Being cute but also stealing the Wave Apps icon
		$wiz_icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDI3LjIuMCwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDYuMDAgQnVpbGQgMCkgIC0tPgo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IgoJIHZpZXdCb3g9IjAgMCA0MiA0MiIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDIgNDI7IiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPHN0eWxlIHR5cGU9InRleHQvY3NzIj4KCS5zdDB7ZmlsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7ZmlsbDojMDg0Rjk5O30KCS5zdDF7ZmlsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7ZmlsbDojNUVCN0ZGO30KCS5zdDJ7ZmlsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7ZmlsbDojMTQ3OUZCO30KPC9zdHlsZT4KPHBhdGggY2xhc3M9InN0MCIgZD0iTTQsMzFjMi40LDAuOCw0LjktMC41LDUuNy0yLjdsMi43LTguN2MwLjctMi4yLTAuNi00LjctMy01LjVMOS4yLDE0Yy0yLjQtMC44LTQuOSwwLjUtNS43LDIuN2wtMi43LDguNwoJYy0wLjcsMi4yLDAuNiw0LjcsMyw1LjVMNCwzMXoiLz4KPHBhdGggY2xhc3M9InN0MSIgZD0iTTMyLjUsNi4xQzMzLDQuOSwzNCwzLjksMzUuMSwzLjRjMC4xLDAsMC4xLTAuMSwwLjItMC4xYzAsMCwwLjEsMCwwLjEsMGMwLjktMC40LDEuOS0wLjQsMi45LTAuMWwwLjIsMC4xCgljMi40LDAuOCwzLjcsMy41LDIuOCw2YzAsMC02LjUsMjIuMS02LjUsMjIuMWMtMS43LDUuMS03LjEsNy43LTExLjgsNi40YzAsMC0xLjMtMC40LTEuOC0wLjdDMjUuOCwzNi4xLDMxLjYsOC4zLDMyLjUsNi4xeiIvPgo8cGF0aCBjbGFzcz0ic3QyIiBkPSJNNC44LDMzLjljMSwwLjMsMiwwLjMsMywwLjFjMC4yLDAsMC4zLTAuMSwwLjUtMC4xYzEuOS0wLjYsMy42LTIuMiw0LjMtNC4zbDUuMS0xNi44YzAuOC0yLjYsMy41LTQsNS44LTMuMwoJbDAuMiwwLjFjMi40LDAuOCwzLjcsMy41LDIuOCw2YzAsMC00LjgsMTUuOS01LjUsMTcuM2MtNC45LDktMTQuMiw0LjYtMTYuOSwwLjhjMC4yLDAuMSwwLjMsMC4xLDAuNSwwLjJMNC44LDMzLjl6Ii8+Cjwvc3ZnPgo=';

		add_menu_page( 'EDD Wave', 'EDD Wave', 'edit_posts', 'edd-wave', [ $this, 'options_page' ], $wiz_icon, '151' );

	}

	/**
	 * Add settings to the WP Admin menu (under EDD "Download" Settings)
	 *
	 * @return void
	 */
	public function admin_submenu() {

		add_submenu_page(
			'edit.php?post_type=download',
			'EDD + Wave',
			'Wave',
			'manage_shop_settings',
			'wave-edd',
			[ $this, 'options_page' ],
			10
		);
	}

	/**
	 * Output a Wordpress settings page
	 *
	 * @return void
	 */
	public function options_page() { ?>

		<div class="wrap">
			<h1>EDD Wave Settings</h1>

			<div class="white-bx-rnd">
				<p><?php echo sprintf( __( 'Don\'t know where to start? <a href="%s" target="_blank" rel="noopener">Start with a Wave account and API Application</a>', 'edd-wave' ), 'https://developer.waveapps.com/hc/en-us/articles/360020948171-Create-a-Wave-Account-and-Test-Businesses' ); ?>.</p>

				<p>Also: <em>please</em> make a <a href="https://paypal.me/canyonwebworks" target="_blank" rel="noopener">$1-5 donation to Canyon Webworks</a> to support this work.</p>
			</div>

			<form id="edd-wave-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" class="regular-text ltr" name="edd_wave_settings_nonce" value="<?php echo wp_create_nonce( 'edd-wave-settings' ); ?>"/>
				<input type="hidden" name="action" value="edd_wave_settings">

				<fieldset>
					<h2>Authentication</h2>
					<div style="column-gap:2em;display:flex;flex-wrap:wrap;justify-content:space-between;">
						<div class="white-bx-rnd" style="flex: 1 1 0">
							<?php
							$client_id = $this->settings['client_id'] ?? ''; ?>
							<p>
								<label for="client_id"><strong>Wave Client ID</strong></label><br />
								<input id="client_id" type="text" class="regular-text ltr" name="client_id" value="<?php esc_attr_e( $client_id ) ?? ''; ?>">
							</p>

							<?php $client_secret = $this->settings['client_secret'] ?? ''; ?>
							<p>
								<label for="client_secret"><strong>Wave Client Secret</strong></label><br />
								<input id="client_secret" type="text" class="regular-text ltr" name="client_secret" value="<?php esc_attr_e( $client_secret ) ?? ''; ?>">
							</p>

							<a href="<?php echo admin_url('admin-post.php?action=edd_wave_oauth_connect&nonce=' . wp_create_nonce( 'edd_wave_connect' ) ); ?>" class="button button-primary">
								<?php _e( 'Connect to Wave Apps', 'edd-wave' ); ?>
							</a>
							<p class="description">
								<?php _e('Click to authorize this site with your Wave account.', 'edd-wave'); ?>
							</p>
						</div>

						<div style="flex: 1 1 0">

							<?php if ( isset( $this->settings['api_key'] ) ) {
								$this->settings['full_access_token'] = $this->settings['api_key'];
								unset( $this->settings['api_key'] );
								update_option( 'edd_wave', $this->settings, false );
							}

							$full_access_token = $this->settings['full_access_token'] ?? ''; ?>
							<p>
								<label for="full_access_token"><strong>Wave Full Access Token</strong></label><br />
								<input id="full_access_token" type="text" class="regular-text ltr" name="full_access_token" value="<?php esc_attr_e( $full_access_token ) ?? ''; ?>">
								<br><?php esc_html_e( 'It is not secure and therefore inadvisable to use a full access key except in a development environment.', 'edd-wave' ); ?>
								<br><?php esc_html_e( 'This option might benefit users without a paid Wave account, but Canyon Webworks assumes no responsibility for consequences of saving and using a Full Access Token here.', 'edd-wave' ); ?> <?php esc_html_e( 'Please use a Wave Client ID + Client Secret instead.', 'edd-wave' ); ?>
							</p>

						</div>
					</div>
					<?php if ( empty( $full_access_token ) && empty( $client_id ) && empty( $client_secret ) ) { ?>
						<p><?php _e( 'A Wave Client ID + Wave Client Secret -or- Wave Full Access Token must be saved here to continue.', 'edd-wave' ); ?></p>
						<?php
						submit_button( null, '', 'edd-wave-submit' );
					} ?>
				</fieldset>

				<?php if ( empty( $full_access_token ) && empty( $client_id ) && empty( $client_secret ) ) { ?>
				<!-- </form>
			</div> -->
				<?php // return;
				}

				$this->output_business_account_select();
				$this->output_income_account_selects();
				$this->output_product_mapping_table();
				$this->output_expenses_settings_table();
				$this->output_tax_settings_table();

				submit_button( null, '', 'edd-wave-submit' ); ?>

			</form>
		</div>

	<?php }

	protected function output_business_account_select() { ?>

		<h2>Business Account</h2>

		<?php // Get list of businesses from Wave Apps API
		$businesses = eddwave()->get_wave_businesses();
		if ( ! $businesses || isset( $businesses['errors'] ) ) { ?>
			<div>
				<?php esc_html_e( 'Error fetching businesses for this token: ', 'edd-wave' ); ?>
				<strong><?php esc_html_e( $businesses['errors'][0]['message'] ); ?> </strong>
				<br><?php esc_html_e( 'Verify you are authenticated for this domain, and try re-loading the page', 'edd-wave' );
				submit_button( null, '', 'edd-wave-submit' ); ?>
			</div>
			<?php return;
		} ?>

		<div style="column-gap:2em;display:flex;flex-wrap:wrap;justify-content:flex-start;">
			<div class="white-bx-rnd">
				<p>
					<select name="business_id" id="business_id">
						<?php
						$business_id = $this->settings['business_id'] ?? '';
						$option_html_output = '<option value="">&mdash; Select &mdash;</option>';
						foreach ( $businesses['data']['businesses']['edges'] as $edge ) {
							$option_html_output .= '<option value="' . $edge['node']['id'] . '"' . selected( $edge['node']['id'], $business_id ) . '>' . $edge['node']['name'] . '</option>';
						}
						echo $option_html_output;
						?>
					</select>
					<br>
					<label for="business_id"><strong>Wave Business ID</strong></label>
				</p>
			</div>
		</div>

	<?php }

	protected function output_income_account_selects() { ?>

		<h2>Income Accounts</h2>

		<?php $wave_asset_accounts = eddwave()->get_wave_accounts( 'ASSET' );
		if ( ! $wave_asset_accounts
			 || isset( $wave_asset_accounts['errors'] )
			 || ! is_array( $wave_asset_accounts['data']['business']['accounts']['edges'] )
		) { ?>
			<p>
				<?php if ( isset( $wave_asset_accounts['errors'] ) ) {
					esc_html_e( 'Error fetching Wave income accounts: ', 'edd-wave' ); ?>
					<strong><?php esc_html_e( $wave_asset_accounts['errors'][0]['message'] ); ?> </strong>
					<br><?php esc_html_e( 'Verify you have selected the correct Wave business account above and try re-loading the page.', 'edd-wave' ); ?>
					<?php submit_button( null, '', 'edd-wave-submit' );
				} else {
					esc_html_e( 'You must be connected to Wave API to retrieve accounts.', 'edd-wave' );
				} ?>
			</p>
			<?php
			return;
		} ?>

		<div style="column-gap:2em;display:flex;flex-wrap:wrap;justify-content:flex-start;">

		<?php $paypal_anchor_account_id = $this->settings['paypal_anchor_account_id'] ?? '';
		if ( ! empty( $paypal_anchor_account_id ) ) { ?>
			<div>
				<h2>PayPal Account</h2>
				<div class="white-bx-rnd">
					<p>
						<label for="paypal_anchor_account_id">PayPal Anchor Account ID</label><br />

						<select name="paypal_anchor_account_id" id="paypal_anchor_account_id">
							<?php
							$option_html_output = '<option value="">&mdash; Select &mdash;</option>';
							foreach ( $wave_asset_accounts['data']['business']['accounts']['edges'] as $edge ) {
								if ( $edge['node']['isArchived'] ) {
									continue;
								}
								$option_html_output .= '<option value="' . $edge['node']['id'] . '"' . selected( $edge['node']['id'], $paypal_anchor_account_id ) . '>' . $edge['node']['name'] . '</option>';
							}
							echo $option_html_output;
							?>
						</select>
					</p>
				</div>
			</div>
		<?php }

		// STRIPE
		$stripe_anchor_account_id = $this->settings['stripe_anchor_account_id'] ?? '';
		if ( ! empty( $stripe_anchor_account_id ) ) { ?>
			<div>
				<h2>Stripe Account</h2>
				<div class="white-bx-rnd">
					<p>
						<label for="stripe_anchor_account_id">Stripe Anchor Account ID</label><br />

						<select name="stripe_anchor_account_id" id="stripe_anchor_account_id">
							<?php $option_html_output = '<option value="">&mdash; Select &mdash;</option>';
							foreach ( $wave_asset_accounts['data']['business']['accounts']['edges'] as $edge ) {
								$option_html_output .= '<option value="' . $edge['node']['id'] . '"' . selected( $edge['node']['id'], $stripe_anchor_account_id ) . '>' . $edge['node']['name'] . '</option>';
							}
							echo $option_html_output;
							?>
						</select>
					</p>
				</div>
			</div>
		<?php } ?>
		</div>

	<?php
	}

	/**
	 * Display income mapped settings table
	 * 
	 * @return void
	 */
	protected function output_product_mapping_table() { ?>

		<h2>Product Mapping</h2>
		<?php $wave_accounts = eddwave()->get_wave_accounts( 'INCOME' );
		 if ( ! isset( $wave_accounts['data']['business']['accounts']['edges'] ) || ! is_array( $wave_accounts['data']['business']['accounts']['edges'] ) ) {
			esc_html_e( 'You must be connected to Wave API to retrieve products.', 'edd-wave' );
			return;
		} ?>

		<div class="white-bx-rnd d-inline-block">
			<p>
				<?php esc_html_e( 'Match your products to your Chart of Account income items below.', 'edd-wave' ); ?>
			</p>
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Local EDD Product', 'edd-wave' ); ?></th>
						<th>&nbsp;</th>
						<th><?php esc_html_e( 'Wave Income Account', 'edd-wave' ); ?></th>
					</tr>
				</thead>

				<tbody>

				<?php

				// Create HTML <option>s containing Wave business income account ID -> names
				$option_html_output = '';
				$default_account = '';

				foreach ( $wave_accounts['data']['business']['accounts']['edges'] as $edge ) {
					// Determine ID of "Uncategorized Income" account
					if ( 'uncategorized income' === strtolower( $edge['node']['name'] ) ) {
						$default_account = $edge['node']['id'];
					}
					$option_html_output .= '<option value="' . $edge['node']['id'] . '">' . $edge['node']['name'] . '</option>';
				}

				// Now let's get all the EDD products
				$args = [
					'post_type'         => 'download',
					'post_status'       => 'publish',
					'order'             => 'ASC',
					'orderby'           => 'title',
					'numberposts'       => -1,
					'no_found_rows'     => true, // don't include pagination (faster)
				];
				$edd_downloads = get_posts( $args );

				foreach ( $edd_downloads as $index => $download ) {

					$wave_income_account = get_post_meta( $download->ID, '_wave_income_account', true );
					if ( empty( $wave_income_account ) ) {
						$wave_income_account = $default_account;
					}

					echo '<tr id="edd-wave-row-' . $index . '">';
					echo '<td class="edd-wave-local-value">' . $download->post_title;
					echo '<input type="hidden" name="edd_wave_income[' . $download->ID . '][' . "parent" . ']" value="' . $wave_income_account . '">';
					echo '</td>';
					echo '<td><span class="dashicons dashicons-arrow-right-alt"></span></td>';
					echo '<td class="edd-wave-remote-value"><select name="edd_wave_income_select-' . $index . '" class="edd-wave-select" data-selected="' . $wave_income_account . '">' . $option_html_output . '</select></td>';
					echo '</tr>';

					// Add more rows if EDD product also has variable pricing
					if ( edd_has_variable_prices( $download->ID ) ) {

						$_post_meta_variable_income_account = get_post_meta( $download->ID, '_wave_variable_income_account', true ) ?? '';
						$prices = edd_get_variable_prices( $download->ID );
						foreach ( $prices as $key => $price ) {

							$_key = $_post_meta_variable_income_account[$key] ?? '';
							if ( empty( $_key ) ) {
								$_key = $default_account;
							}

							echo '<tr id="edd-wave-row-' . $index . '-' . $key . '">';
							echo '<td class="edd-wave-local-value">' . $download->post_title . ' - ' . $price["name"];
							echo '<input type="hidden" name="edd_wave_income[' . $download->ID . '][' . $key . ']" value="' . $_key . '">';
							echo '</td>';
							echo '<td><span class="dashicons dashicons-arrow-right-alt"></span></td>';
							echo '<td class="edd-wave-remote-value"><select name="edd_wave_income_select-' . $key . '" class="edd-wave-select" data-selected="' . $_key . '">' . $option_html_output . '</select></td>';
							echo '</tr>';

						}
					}
				}
				?>
				</tbody>
			</table>
		</div>

		<?php
	}

	/**
	 * Display expenses mapped settings table
	 *
	 * @return void
	 */
	protected function output_expenses_settings_table() { ?>

		<h2>Expense Mapping</h2>

		<?php $expense_accounts = eddwave()->get_wave_accounts( 'EXPENSE' );
		if ( ! isset( $expense_accounts['data']['business']['accounts']['edges'] )
			|| ! is_array( $expense_accounts['data']['business']['accounts']['edges'] )
		) {
			esc_html_e( 'You must be connected to Wave API to retrieve expenses.', 'edd-wave' );
			return;
		} ?>

		<div class="white-bx-rnd d-inline-block">

			<p><?php esc_html_e( 'Match your fees, etc. to your Chart of Account expense items below.', 'edd-wave' ); ?></p>
			<table>
				<thead>
				<tr>
					<th><?php esc_html_e( 'Local Expenses', 'edd-wave' ); ?></th>
					<th>&nbsp;</th>
					<th><?php esc_html_e( 'Wave Expense Account', 'edd-wave' ); ?></th>
				</tr>
				</thead>
				<tbody>

				<?php

				// Create HTML <option>s containing Wave business expense account ID -> names
				$option_html_output = '<option value="">&mdash; Select &mdash;</option>';
				foreach ( $expense_accounts['data']['business']['accounts']['edges'] as $edge ) {
					if ( $edge['node']['isArchived'] ) {
						continue;
					}
					$option_html_output .= '<option value="' . $edge['node']['id'] . '">' . $edge['node']['name'] . '</option>';
				}
				$expense_discounts = $this->settings['expense_discounts'] ?? ''
				?>

				<tr id="edd-wave-row">
					<td class="edd-wave-local-value"><?php esc_html_e( 'Discounts', 'edd-wave' ); ?><input type="hidden" name="expense_discounts" value="<?php esc_attr_e( $expense_discounts ); ?>"></td>
					<td><span class="dashicons dashicons-arrow-right-alt"></span></td>
					<?php
					echo '<td class="edd-wave-remote-value">';
					echo '<select name="expense_discounts_select" class="edd-wave-select" data-selected="' . $expense_discounts . '">';
					echo $option_html_output;
					echo '</select>';
					echo '</td>';
					?>
				</tr>

				<?php if ( is_plugin_active( 'edd-stripe/edd-stripe.php' ) ) {

					$stripe_fees_account_id = $this->settings['stripe_fees_account_id'] ?? '';
					$stripe_refund_account_id = $this->settings['stripe_refund_account_id'] ?? '';
					?>

					<tr id="edd-wave-row">
						<td class="edd-wave-local-value"><?php esc_html_e( 'Stripe Merchant Account Fees', 'edd-wave' ); ?><input type="hidden" name="stripe_fees_account_id" value="<?php esc_attr_e( $stripe_fees_account_id ); ?>"></td>
						<td><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<?php
						echo '<td class="edd-wave-remote-value">';
						echo '<select name="stripe_fees_select" class="edd-wave-select" data-selected="' . $stripe_fees_account_id . '">';
						echo $option_html_output;
						echo '</select>';
						echo '</td>';
						?>
					</tr>

					<tr id="edd-wave-row">
						<td class="edd-wave-local-value">Refunds<input type="hidden" name="stripe_refund_account_id" value="<?php esc_attr_e( $stripe_refund_account_id ); ?>"></td>
						<td><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<?php
						echo '<td class="edd-wave-remote-value">';
						echo '<select name="stripe_refund_select" class="edd-wave-select" data-selected="' . $stripe_refund_account_id . '">';
						echo $option_html_output;
						echo '</select>';
						echo '</td>';
						?>
					</tr>

				<?php } ?>

				<?php if ( is_plugin_active( 'edd-paypal-commerce-pro/edd-paypal-commerce-pro.php' ) ) {
					$paypal_fees_account_id = $this->settings['paypal_fees_account_id'] ?? '';
					?>

					<tr id="edd-wave-row">
						<td class="edd-wave-local-value"><?php esc_html_e( 'PayPal Merchant Account Fees', 'edd-wave' ); ?><input type="hidden" name="paypal_fees_account_id" value="<?php esc_attr_e( $paypal_fees_account_id ); ?>"></td>
						<td><span class="dashicons dashicons-arrow-right-alt"></span></td>
						<?php
						echo '<td class="edd-wave-remote-value">';
						echo '<select name="paypal_fees_select" class="edd-wave-select" data-selected="' . $paypal_fees_account_id . '">';
						echo $option_html_output;
						echo '</select>';
						echo '</td>';
						?>
					</tr>

				<?php } ?>

				</tbody>
			</table>
		</div>
		<?php

	}

	protected function output_tax_settings_table() { ?>

		<h2>Tax Mapping</h2>
		<?php
		$liability_accounts = eddwave()->get_wave_accounts( 'LIABILITY' );
		if ( ! isset( $liability_accounts['data']['business']['accounts']['edges'] )
			 || ! is_array( $liability_accounts['data']['business']['accounts']['edges'] )
		) {
			esc_html_e( 'You must be connected to Wave API to retrieve liability accounts.', 'edd-wave' );
			return;
		} ?>

		<div class="white-bx-rnd d-inline-block">
			<p><?php esc_html_e( 'Need better tax mapping? Consider hiring Canyon Webworks to build it, or submit a strong pull request.', 'edd-wave' ); ?></p>
			<?php
			$default_account = '';
				// Create HTML <option>s containing Wave liability accounts
				$option_html_output = '<option value="">&mdash; Select &mdash;</option>';
				foreach ( $liability_accounts['data']['business']['accounts']['edges'] as $edge ) {
					if ( $edge['node']['isArchived'] ) {
						continue;
					}
					if ( 'accounts payable' === strtolower( $edge['node']['name'] ) ) { // not an ideal default, but better than blank
						$default_account = $edge['node']['id'];
					}
					$option_html_output .= '<option value="' . $edge['node']['id'] . '">' . $edge['node']['name'] . '</option>';
				}
				$liability_account_id = $this->settings['tax_liability'] ?? $default_account; ?>
				<select name="liability_select" class="edd-wave-select" data-selected="<?php esc_attr_e( $liability_account_id ); ?>">
					<?php echo $option_html_output; ?>
				</select>
			</div>
		<?php 
	}

	/**
	 * Save EDD Wave admin settings
	 *
	 */
	public function save_settings() {

		// Quick nonce check
		if ( ! wp_verify_nonce( $_POST['edd_wave_settings_nonce'], 'edd-wave-settings' ) ) {
			wp_die( 'EDD + Wave: bad nonce' );
		}

		// One more check, then save settings
		if ( isset( $_POST['edd-wave-submit'] ) ) {

			// Get existing settings
			$settings = get_option( 'edd_wave', [] );

			if ( isset( $_POST['business_id'] ) ) {
				$settings['business_id'] = sanitize_text_field( $_POST['business_id'] );
			}
			if ( isset( $_POST['client_id'] ) ) {
				$settings['client_id'] = sanitize_text_field( $_POST['client_id'] );
			}
			if ( isset( $_POST['client_secret'] ) ) {
				$settings['client_secret'] = sanitize_text_field( $_POST['client_secret'] );
			}
			if ( isset( $_POST['full_access_token'] ) ) {
				$settings['full_access_token'] = sanitize_text_field( $_POST['full_access_token'] );
			}
			if ( isset( $_POST['paypal_anchor_account_id'] ) ) {
				$settings['paypal_anchor_account_id'] = sanitize_text_field( $_POST['paypal_anchor_account_id'] );
			}
			if ( isset( $_POST['paypal_fees_account_id'] ) ) {
				$settings['paypal_fees_account_id'] = sanitize_text_field( $_POST['paypal_fees_account_id'] );
			}
			if ( isset( $_POST['stripe_anchor_account_id'] ) ) {
				$settings['stripe_anchor_account_id'] = sanitize_text_field( $_POST['stripe_anchor_account_id'] );
			}
			if ( isset( $_POST['stripe_fees_account_id'] ) ) {
				$settings['stripe_fees_account_id'] = sanitize_text_field( $_POST['stripe_fees_account_id'] );
			}
			if ( isset( $_POST['stripe_refund_account_id'] ) ) {
				$settings['stripe_refund_account_id'] = sanitize_text_field( $_POST['stripe_refund_account_id'] );
			}
			if ( isset( $_POST['expense_discounts'] ) ) {
				$settings['expense_discounts'] = sanitize_text_field( $_POST['expense_discounts'] );
			}
			if ( isset( $_POST['liability_select'] ) ) {
				$settings['tax_liability'] = sanitize_text_field( $_POST['liability_select'] );
			}

			if ( isset( $_POST['edd_wave_income'] ) ) {

				$variable_accounts = [];

				foreach( $_POST['edd_wave_income'] as $index => $income_account_array ) { // $index is the EDD parent download ID

					foreach( $income_account_array as $key => $income_account ) { // $key 0 is the parent, any other $keys are variable prices

						$account = sanitize_text_field( $income_account );

						if ( $key === 'parent' ) {
							update_post_meta( $index, '_wave_income_account', $account );
						} else {
							if ( ! empty( $account ) ) {
								$variable_accounts[$key] = $account;
							} else {
								if ( isset( $variable_accounts[$key] ) ) {
									unset( $variable_accounts[ $key ] );
								}
							}
						}
					}

					if ( ! empty( $variable_accounts ) ) {
						// EDD variable priced product
						update_post_meta( $index, '_wave_variable_income_account', $variable_accounts );
						unset( $variable_accounts );
					}

				}

			}
			update_option( 'edd_wave', $settings, false );

		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=download&page=wave-edd' ) );
		exit;

	}

}