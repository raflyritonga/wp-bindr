<?php
/**
 * Settings page (Settings API).
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings → Flipbooks.
 */
class Bindr_Settings {

	const OPTION = 'bindr_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		/**
		 * Filter default plugin settings.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters(
			'bindr_default_settings',
			array(
				'default_display'        => 'double',
				'default_background'     => '#404040',
				'default_allow_download' => 0,
				'fallback_url'           => '',
				'fullpage_logo_id'       => 0,
				'retention_days'         => 90,
				'delete_on_uninstall'    => 0,
			)
		);
	}

	/**
	 * Hook registration.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add Settings → Flipbooks.
	 */
	public function add_page() {
		add_options_page(
			__( 'Flipbook Settings', 'wp-bindr' ),
			__( 'Flipbooks', 'wp-bindr' ),
			'manage_options',
			'bindr-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register setting, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'bindr_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'bindr_viewer', __( 'Viewer Defaults', 'wp-bindr' ), '__return_false', 'bindr-settings' );
		add_settings_field( 'default_display', __( 'Default page display', 'wp-bindr' ), array( $this, 'field_display' ), 'bindr-settings', 'bindr_viewer' );
		add_settings_field( 'default_background', __( 'Default background color', 'wp-bindr' ), array( $this, 'field_background' ), 'bindr-settings', 'bindr_viewer' );
		add_settings_field( 'default_allow_download', __( 'Downloads', 'wp-bindr' ), array( $this, 'field_download' ), 'bindr-settings', 'bindr_viewer' );

		add_settings_section( 'bindr_fullpage', __( 'Full-Page Reading Mode', 'wp-bindr' ), '__return_false', 'bindr-settings' );
		add_settings_field( 'fallback_url', __( 'Back button fallback URL', 'wp-bindr' ), array( $this, 'field_fallback_url' ), 'bindr-settings', 'bindr_fullpage' );
		add_settings_field( 'fullpage_logo_id', __( 'Top bar logo', 'wp-bindr' ), array( $this, 'field_logo' ), 'bindr-settings', 'bindr_fullpage' );

		add_settings_section( 'bindr_data', __( 'Analytics & Data', 'wp-bindr' ), array( $this, 'section_data_intro' ), 'bindr-settings' );
		add_settings_field( 'retention_days', __( 'Raw event retention', 'wp-bindr' ), array( $this, 'field_retention' ), 'bindr-settings', 'bindr_data' );
		add_settings_field( 'delete_on_uninstall', __( 'Uninstall', 'wp-bindr' ), array( $this, 'field_uninstall' ), 'bindr-settings', 'bindr_data' );
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$clean = array(
			'default_display'        => ( isset( $input['default_display'] ) && 'single' === $input['default_display'] ) ? 'single' : 'double',
			'default_background'     => isset( $input['default_background'] ) ? (string) sanitize_hex_color( $input['default_background'] ) : $defaults['default_background'],
			'default_allow_download' => ! empty( $input['default_allow_download'] ) ? 1 : 0,
			'fallback_url'           => isset( $input['fallback_url'] ) ? esc_url_raw( $input['fallback_url'] ) : '',
			'fullpage_logo_id'       => isset( $input['fullpage_logo_id'] ) ? absint( $input['fullpage_logo_id'] ) : 0,
			'retention_days'         => isset( $input['retention_days'] ) ? max( 7, min( 3650, absint( $input['retention_days'] ) ) ) : $defaults['retention_days'],
			'delete_on_uninstall'    => ! empty( $input['delete_on_uninstall'] ) ? 1 : 0,
		);
		if ( '' === $clean['default_background'] ) {
			$clean['default_background'] = $defaults['default_background'];
		}
		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flipbook Settings', 'wp-bindr' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'bindr_settings_group' );
				do_settings_sections( 'bindr-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Data section intro.
	 */
	public function section_data_intro() {
		echo '<p>' . esc_html__( 'Analytics are stored only in your own database. No cookies are set and no personal data is kept — visitors are counted with a salted hash that changes every day.', 'wp-bindr' ) . '</p>';
	}

	/**
	 * Default display field.
	 */
	public function field_display() {
		$value = bindr_get_setting( 'default_display' );
		?>
		<label>
			<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[default_display]" value="double" <?php checked( $value, 'double' ); ?> />
			<?php esc_html_e( 'Double page (book spread)', 'wp-bindr' ); ?>
		</label><br />
		<label>
			<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[default_display]" value="single" <?php checked( $value, 'single' ); ?> />
			<?php esc_html_e( 'Single page', 'wp-bindr' ); ?>
		</label>
		<?php
	}

	/**
	 * Default background field.
	 */
	public function field_background() {
		?>
		<input type="text" class="bindr-color-field" name="<?php echo esc_attr( self::OPTION ); ?>[default_background]" value="<?php echo esc_attr( bindr_get_setting( 'default_background' ) ); ?>" data-default-color="#404040" />
		<?php
	}

	/**
	 * Default download field.
	 */
	public function field_download() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_allow_download]" value="1" <?php checked( (int) bindr_get_setting( 'default_allow_download' ), 1 ); ?> />
			<?php esc_html_e( 'Allow PDF download by default (can be changed per flipbook)', 'wp-bindr' ); ?>
		</label>
		<?php
	}

	/**
	 * Fallback URL field.
	 */
	public function field_fallback_url() {
		?>
		<input type="url" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[fallback_url]" value="<?php echo esc_attr( bindr_get_setting( 'fallback_url' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
		<p class="description"><?php esc_html_e( 'Where the back button goes when a reader opens a flipbook directly (for example from a shared link). Defaults to the homepage.', 'wp-bindr' ); ?></p>
		<?php
	}

	/**
	 * Logo field (media picker).
	 */
	public function field_logo() {
		$logo_id  = (int) bindr_get_setting( 'fullpage_logo_id' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		?>
		<div data-bindr-logo-picker>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[fullpage_logo_id]" value="<?php echo esc_attr( (string) $logo_id ); ?>" data-bindr-logo-id />
			<p data-bindr-logo-preview <?php echo $logo_url ? '' : 'hidden'; ?>>
				<img src="<?php echo esc_url( (string) $logo_url ); ?>" alt="" style="max-height:48px;" />
			</p>
			<button type="button" class="button" data-bindr-logo-select><?php esc_html_e( 'Select logo', 'wp-bindr' ); ?></button>
			<button type="button" class="button" data-bindr-logo-remove <?php echo $logo_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'wp-bindr' ); ?></button>
			<p class="description"><?php esc_html_e( 'Optional logo shown in the top bar of full-page reading mode.', 'wp-bindr' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Retention field.
	 */
	public function field_retention() {
		?>
		<input type="number" min="7" max="3650" name="<?php echo esc_attr( self::OPTION ); ?>[retention_days]" value="<?php echo esc_attr( (string) bindr_get_setting( 'retention_days' ) ); ?>" /> <?php esc_html_e( 'days', 'wp-bindr' ); ?>
		<p class="description"><?php esc_html_e( 'Raw reading events older than this are deleted automatically. Daily summaries are kept forever and power the dashboard.', 'wp-bindr' ); ?></p>
		<?php
	}

	/**
	 * Uninstall field.
	 */
	public function field_uninstall() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[delete_on_uninstall]" value="1" <?php checked( (int) bindr_get_setting( 'delete_on_uninstall' ), 1 ); ?> />
			<?php esc_html_e( 'Delete all flipbooks, settings, and analytics data when the plugin is uninstalled', 'wp-bindr' ); ?>
		</label>
		<?php
	}

	/**
	 * Color picker + media picker on our settings page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_bindr-settings' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		$asset = include BINDR_PLUGIN_DIR . 'build/admin-settings/index.asset.php';
		wp_enqueue_script(
			'bindr-admin-settings',
			BINDR_PLUGIN_URL . 'build/admin-settings/index.js',
			array_merge( $asset['dependencies'], array( 'jquery', 'wp-color-picker' ) ),
			$asset['version'],
			true
		);
	}
}
