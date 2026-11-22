<?php
/**
 * Class OpenID_Connect_Generic_Option_Settings_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin WordPress options handling class test case.
 */
class OpenID_Connect_Generic_Option_Settings_Test extends WP_UnitTestCase {

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		parent::tearDown();

	}

	/**
	 * Test plugin option settings get_option_name() method.
	 *
	 * @group ClientTests
	 */
	public function test_plugin_option_settings_get_option_name() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	/**
	 * Test that a saved list replaces its default instead of being merged with it.
	 *
	 * @group OptionSettingsTests
	 */
	public function test_saved_list_replaces_default_list() {
		$defaults = array(
			'login_type'           => 'button',
			'password_login_roles' => array( 'subscriber', 'customer' ),
		);

		update_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME, array( 'password_login_roles' => array( 'editor' ) ) );
		$settings = new OpenID_Connect_Generic_Option_Settings( $defaults );
		$this->assertSame( array( 'editor' ), $settings->password_login_roles );
		$this->assertSame( 'button', $settings->login_type, 'Missing settings should still get their default' );

		update_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME, array( 'password_login_roles' => array() ) );
		$settings = new OpenID_Connect_Generic_Option_Settings( $defaults );
		$this->assertSame( array(), $settings->password_login_roles );

		delete_option( OpenID_Connect_Generic_Option_Settings::OPTION_NAME );
		$settings = new OpenID_Connect_Generic_Option_Settings( $defaults );
		$this->assertSame( array( 'subscriber', 'customer' ), $settings->password_login_roles );
	}

}
