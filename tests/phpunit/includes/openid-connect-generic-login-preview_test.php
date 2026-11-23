<?php
/**
 * Class OpenID_Connect_Generic_Login_Preview_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Login button preview test case.
 */
class OpenID_Connect_Generic_Login_Preview_Test extends WP_UnitTestCase {

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	/**
	 * Create settings with saved login button values.
	 *
	 * @return OpenID_Connect_Generic_Option_Settings
	 */
	private function create_settings() {
		$settings = new OpenID_Connect_Generic_Option_Settings( array() );
		$settings->login_type        = 'button';
		$settings->login_button_text = 'Saved text';
		$settings->login_button_icon = '';
		$settings->woocommerce_login = 'end';
		$settings->enforce_privacy   = 1;
		return $settings;
	}

	/**
	 * Log in as a user with a role.
	 *
	 * @param string $role The role.
	 *
	 * @return int The user ID.
	 */
	private function log_in( $role ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Get one of the plugin's private properties.
	 *
	 * @param OpenID_Connect_Generic $plugin The plugin.
	 * @param string                 $name   The property name.
	 *
	 * @return mixed
	 */
	private function get_plugin_property( $plugin, $name ) {
		$property = new ReflectionProperty( OpenID_Connect_Generic::class, $name );
		$property->setAccessible( true );
		return $property->getValue( $plugin );
	}

	/**
	 * Set up the request a preview frame makes.
	 *
	 * @param string      $view             The view.
	 * @param array       $preview_settings The settings to preview.
	 * @param string|null $nonce            The nonce, or null for a valid one.
	 *
	 * @return void
	 */
	private function set_preview_request( $view, $preview_settings = array(), $nonce = null ) {
		$_GET = array(
			OpenID_Connect_Generic_Login_Preview::QUERY_VAR          => $view,
			OpenID_Connect_Generic_Login_Preview::SETTINGS_QUERY_VAR => $preview_settings,
			'_wpnonce'                                               => null === $nonce ? wp_create_nonce( OpenID_Connect_Generic_Login_Preview::NONCE_ACTION ) : $nonce,
		);
	}

	/**
	 * Requests that aren't previews are left alone.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_no_preview_without_query_var() {
		$this->log_in( 'administrator' );
		$settings = $this->create_settings();

		$this->assertNull( OpenID_Connect_Generic_Login_Preview::maybe_start( $settings ) );
		$this->assertSame( 'Saved text', $settings->login_button_text );
	}

	/**
	 * Only administrators can preview settings, even with a valid nonce for their own account.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_no_preview_for_non_administrators() {
		$this->log_in( 'editor' );
		$settings = $this->create_settings();
		$this->set_preview_request( 'login', array( 'login_button_text' => 'Changed' ) );

		$this->assertNull( OpenID_Connect_Generic_Login_Preview::maybe_start( $settings ) );
		$this->assertSame( 'Saved text', $settings->login_button_text );
	}

	/**
	 * Logged out visitors can't preview settings.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_no_preview_for_logged_out_visitors() {
		wp_set_current_user( 0 );
		$settings = $this->create_settings();
		$this->set_preview_request( 'login', array( 'login_button_text' => 'Changed' ) );

		$this->assertNull( OpenID_Connect_Generic_Login_Preview::maybe_start( $settings ) );
		$this->assertSame( 'Saved text', $settings->login_button_text );
	}

	/**
	 * An administrator needs the settings page's nonce, so other sites can't change what they see.
	 * Without it, such as from a settings page left open too long, the preview says it has expired.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_no_preview_without_valid_nonce() {
		$this->log_in( 'administrator' );
		$settings = $this->create_settings();

		foreach ( array( 'not-a-nonce', wp_create_nonce( 'another-action' ) ) as $nonce ) {
			$this->set_preview_request( 'login', array( 'login_button_text' => 'Changed' ), $nonce );
			try {
				OpenID_Connect_Generic_Login_Preview::maybe_start( $settings );
				$this->fail( 'The preview should say it has expired.' );
			} catch ( WPDieException $e ) {
				$this->assertStringContainsString( 'This preview has expired.', $e->getMessage() );
			}
			$this->assertSame( 'Saved text', $settings->login_button_text );
			$this->assertTrue( is_user_logged_in() );
		}
	}

	/**
	 * Unknown views are not previews.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_no_preview_for_unknown_view() {
		$this->log_in( 'administrator' );
		$settings = $this->create_settings();
		$this->set_preview_request( 'wp-admin', array( 'login_button_text' => 'Changed' ) );

		$this->assertNull( OpenID_Connect_Generic_Login_Preview::maybe_start( $settings ) );
		$this->assertSame( 'Saved text', $settings->login_button_text );
	}

	/**
	 * A valid preview shows the form's login button settings, cleaned, for this request only.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_preview_applies_login_button_settings_without_saving() {
		$this->log_in( 'administrator' );
		update_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME, array( 'login_button_text' => 'Saved text' ) );
		$settings = $this->create_settings();
		$this->set_preview_request(
			'login',
			array(
				'login_type'        => 'shortcode',
				'login_button_text' => '<b>Log in</b> here',
				'login_button_icon' => 'https://example.com/icon.svg"); background: red',
				'woocommerce_login' => 'start',
			)
		);

		$preview = OpenID_Connect_Generic_Login_Preview::maybe_start( $settings );

		$this->assertInstanceOf( OpenID_Connect_Generic_Login_Preview::class, $preview );
		$this->assertSame( 'shortcode', $settings->login_type );
		$this->assertSame( 'Log in here', $settings->login_button_text );
		$this->assertStringNotContainsString( '"', $settings->login_button_icon );
		$this->assertStringNotContainsString( ' ', $settings->login_button_icon );
		$this->assertSame( 'start', $settings->woocommerce_login );
		$this->assertSame( array( 'login_button_text' => 'Saved text' ), get_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME ) );
	}

	/**
	 * Only the login button settings can be previewed, and only with known choices.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_preview_ignores_other_settings_and_unknown_choices() {
		$this->log_in( 'administrator' );
		$settings = $this->create_settings();
		$settings->client_id      = 'saved-client';
		$settings->password_login = 'roles';
		$this->set_preview_request(
			'login',
			array(
				'login_type'        => 'everything',
				'woocommerce_login' => 'middle',
				'client_id'         => 'attacker-client',
				'password_login'    => '',
			)
		);

		OpenID_Connect_Generic_Login_Preview::maybe_start( $settings );

		$this->assertSame( 'button', $settings->login_type );
		$this->assertSame( 'end', $settings->woocommerce_login );
		$this->assertSame( 'saved-client', $settings->client_id );
		$this->assertSame( 'roles', $settings->password_login );
	}

	/**
	 * Auto Login is previewed without leaving for the identity provider: the login page as visitors
	 * see it after logging out, and WooCommerce's own My Account form.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_preview_of_auto_login_stays_on_page() {
		$this->log_in( 'administrator' );
		$settings = $this->create_settings();
		$this->set_preview_request(
			'login',
			array(
				'login_type'        => 'auto',
				'woocommerce_login' => 'auto',
			)
		);

		OpenID_Connect_Generic_Login_Preview::maybe_start( $settings );

		$this->assertSame( 'button', $settings->login_type );
		$this->assertSame( '', $settings->woocommerce_login );
	}

	/**
	 * Previews run as a logged out visitor, without Enforce Privacy sending them away.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_preview_runs_logged_out() {
		foreach ( OpenID_Connect_Generic_Login_Preview::VIEWS as $view ) {
			$this->log_in( 'administrator' );
			$settings = $this->create_settings();
			$this->set_preview_request( $view );

			OpenID_Connect_Generic_Login_Preview::maybe_start( $settings );

			$this->assertFalse( is_user_logged_in(), "The {$view} preview is logged out." );
			$this->assertFalse( (bool) $settings->enforce_privacy );
		}
	}

	/**
	 * The shortcode preview shows the login button on its own page, with the previewed text.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_shortcode_preview_renders_button() {
		$this->log_in( 'administrator' );
		$plugin   = OpenID_Connect_Generic::instance();
		$settings = $this->get_plugin_property( $plugin, 'settings' );
		OpenID_Connect_Generic_Login_Form::register( $settings, $this->get_plugin_property( $plugin, 'client_wrapper' ), $this->get_plugin_property( $plugin, 'client' ) );

		$preview = new class( $settings, 'shortcode' ) extends OpenID_Connect_Generic_Login_Preview {
			/**
			 * Don't end the test run.
			 *
			 * @return void
			 */
			protected function end_request() {}
		};
		$preview->apply_settings( array( 'login_button_text' => 'Preview text' ) );
		$preview->start();

		ob_start();
		$preview->render_shortcode_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'openid-connect-login-button', $output );
		$this->assertStringContainsString( 'Preview text', $output );
		$this->assertStringContainsString( 'id="openid-connect-preview"', $output );
	}

	/**
	 * Preview URLs carry the view and a nonce, and My Account needs WooCommerce.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_preview_urls() {
		$this->log_in( 'administrator' );

		$url = OpenID_Connect_Generic_Login_Preview::get_url( 'login' );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->assertStringStartsWith( wp_login_url(), $url );
		$this->assertSame( 'login', $query[ OpenID_Connect_Generic_Login_Preview::QUERY_VAR ] );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], OpenID_Connect_Generic_Login_Preview::NONCE_ACTION ) );

		$this->assertStringStartsWith( home_url( '/' ), OpenID_Connect_Generic_Login_Preview::get_url( 'shortcode' ) );
		$this->assertSame( '', OpenID_Connect_Generic_Login_Preview::get_url( 'myaccount' ), 'WooCommerce is not active in tests.' );
		$this->assertSame( '', OpenID_Connect_Generic_Login_Preview::get_url( 'unknown' ) );
	}

	/**
	 * Plugin upgrades, which can save settings, never run during a preview.
	 *
	 * @group LoginPreviewTests
	 */
	public function test_plugin_skips_upgrade_during_preview() {
		$this->log_in( 'administrator' );
		update_option( 'openid-connect-generic-plugin-version', '3.0.0' );
		$logger = new OpenID_Connect_Generic_Option_Logger();

		$this->set_preview_request( 'login', array( 'login_button_text' => 'Preview text' ) );
		$plugin = new OpenID_Connect_Generic( $this->create_settings(), $logger );
		$plugin->init();
		$this->assertSame( '3.0.0', get_option( 'openid-connect-generic-plugin-version' ) );

		$this->log_in( 'administrator' );
		$_GET   = array();
		$plugin = new OpenID_Connect_Generic( $this->create_settings(), $logger );
		$plugin->init();
		$this->assertSame( OpenID_Connect_Generic::VERSION, get_option( 'openid-connect-generic-plugin-version' ) );
	}
}
