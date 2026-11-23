<?php
/**
 * Login button preview class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @author    Jonathan Daggerhart <jonathan@daggerhart.com>
 * @copyright 2015-2023 daggerhart
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Login_Preview class.
 *
 * Shows the real login page, WooCommerce My Account page or shortcode output, as a logged out
 * visitor would see them, with the login button settings not yet saved on the settings page.
 * Only administrators can open a preview, with a nonce from the settings page.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 */
class OpenID_Connect_Generic_Login_Preview {

	/**
	 * The query variable that names the view to preview.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'openid-connect-preview';

	/**
	 * The query variable holding the settings to preview.
	 *
	 * @var string
	 */
	const SETTINGS_QUERY_VAR = 'openid-connect-preview-settings';

	/**
	 * The nonce action for preview requests.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'openid-connect-generic-login-preview';

	/**
	 * The views that can be previewed.
	 *
	 * @var string[]
	 */
	const VIEWS = array( 'login', 'myaccount', 'shortcode' );

	/**
	 * Plugin settings object.
	 *
	 * @var OpenID_Connect_Generic_Option_Settings
	 */
	private $settings;

	/**
	 * The view being previewed.
	 *
	 * @var string
	 */
	private $view;

	/**
	 * The class constructor.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 * @param string                                 $view     The view being previewed.
	 */
	public function __construct( $settings, $view ) {
		$this->settings = $settings;
		$this->view     = $view;
	}

	/**
	 * Start a preview if this request is a valid one from an administrator.
	 *
	 * This runs before the login form is registered, so the previewed settings apply to the whole request.
	 *
	 * @param OpenID_Connect_Generic_Option_Settings $settings A plugin settings object instance.
	 *
	 * @return OpenID_Connect_Generic_Login_Preview|null The preview, or null if this request is not one.
	 */
	public static function maybe_start( $settings ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is checked below.
		if ( empty( $_GET[ self::QUERY_VAR ] ) ) {
			return null;
		}

		$view  = sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! in_array( $view, self::VIEWS, true ) || ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		// An old settings page's nonce has expired. Say so, rather than show the page with the saved settings.
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die(
				esc_html__( 'This preview has expired. Reload the settings page to preview again.', 'daggerhart-openid-connect-generic' ),
				esc_html__( 'Preview expired', 'daggerhart-openid-connect-generic' ),
				array( 'response' => 403 )
			);
		}

		$preview_settings = isset( $_GET[ self::SETTINGS_QUERY_VAR ] ) && is_array( $_GET[ self::SETTINGS_QUERY_VAR ] )
			? wp_unslash( $_GET[ self::SETTINGS_QUERY_VAR ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized in apply_settings().
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$preview = new self( $settings, $view );
		$preview->apply_settings( $preview_settings );
		$preview->start();

		return $preview;
	}

	/**
	 * Get the URL that previews a view, or an empty string if the view isn't available on this site.
	 *
	 * @param string $view One of the VIEWS.
	 *
	 * @return string
	 */
	public static function get_url( $view ) {
		switch ( $view ) {
			case 'login':
				$url = wp_login_url();
				break;

			case 'myaccount':
				$page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'myaccount' ) : 0;
				$url     = $page_id > 0 ? get_permalink( $page_id ) : '';
				break;

			case 'shortcode':
				$url = home_url( '/' );
				break;

			default:
				$url = '';
		}

		if ( empty( $url ) ) {
			return '';
		}

		return add_query_arg(
			array(
				self::QUERY_VAR => $view,
				'_wpnonce'      => wp_create_nonce( self::NONCE_ACTION ),
			),
			$url
		);
	}

	/**
	 * Apply the settings being previewed to this request only. They are never saved.
	 *
	 * @param array $preview_settings The login button settings from the settings page form.
	 *
	 * @return void
	 */
	public function apply_settings( $preview_settings ) {
		$choices = array(
			'login_type'        => array( 'button', 'auto', 'shortcode' ),
			'woocommerce_login' => array( '', 'start', 'end', 'auto' ),
		);
		foreach ( $choices as $key => $values ) {
			if ( isset( $preview_settings[ $key ] ) && in_array( $preview_settings[ $key ], $values, true ) ) {
				$this->settings->{ $key } = $preview_settings[ $key ];
			}
		}

		if ( isset( $preview_settings['login_button_text'] ) && is_string( $preview_settings['login_button_text'] ) ) {
			$this->settings->login_button_text = sanitize_text_field( $preview_settings['login_button_text'] );
		}

		if ( isset( $preview_settings['login_button_icon'] ) && is_string( $preview_settings['login_button_icon'] ) ) {
			$this->settings->login_button_icon = esc_url_raw( trim( $preview_settings['login_button_icon'] ) );
		}

		// Auto Login would leave the page for the identity provider. On the login page, visitors see
		// the same page, with the button, after logging out. On My Account, show WooCommerce's own form.
		if ( 'auto' === $this->settings->login_type ) {
			$this->settings->login_type = 'button';
		}
		if ( 'auto' === $this->settings->woocommerce_login ) {
			$this->settings->woocommerce_login = '';
		}

		// The preview is shown as a logged out visitor, which Enforce Privacy would send to the login page.
		$this->settings->enforce_privacy = false;
	}

	/**
	 * Show this request as a logged out visitor would see it, and keep it inert and uncached.
	 *
	 * Even wp-login.php differs for a logged in user: it fills in their user name.
	 *
	 * @return void
	 */
	public function start() {
		// The nonce has been checked, so the rest of the request can run as a logged out visitor.
		wp_set_current_user( 0 );

		if ( ! headers_sent() ) {
			nocache_headers();
		}

		add_action( 'wp_head', array( $this, 'print_preview_styles' ) );
		add_action( 'login_head', array( $this, 'print_preview_styles' ) );

		if ( 'shortcode' === $this->view ) {
			add_action( 'template_redirect', array( $this, 'render_shortcode_page' ), -1 );
		}
	}

	/**
	 * Stop links and forms in the preview from being used.
	 *
	 * @return void
	 */
	public function print_preview_styles() {
		echo '<style id="openid-connect-preview">a,button,input,select,textarea,label,summary{pointer-events:none!important}</style>';
	}

	/**
	 * Render the login button shortcode on its own, styled by the theme.
	 *
	 * @return void
	 */
	public function render_shortcode_page() {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<?php wp_head(); ?>
		</head>
		<body <?php body_class( 'openid-connect-preview' ); ?>>
			<main class="wp-site-blocks site-main">
				<div class="entry-content" style="padding: 2em;">
					<?php echo do_shortcode( '[openid_connect_generic_login_button]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shortcode escapes its own output. ?>
				</div>
			</main>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		$this->end_request();
	}

	/**
	 * End the request after rendering a preview page.
	 *
	 * @codeCoverageIgnore
	 *
	 * @return void
	 */
	protected function end_request() {
		exit;
	}
}
