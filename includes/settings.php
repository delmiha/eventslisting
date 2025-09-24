<?php
/**
 * The plugin's setting section
 *
 * @package eventslisting
 */

?>
<?php
if ( class_exists( 'Events_Listing_Plugin' ) ) {
	add_action(
		'admin_menu',
		static function () {
			add_submenu_page(
				'edit.php?post_type=' . Events_Listing_Plugin::CPT,
				__( 'Events Settings', 'eventslisting' ),
				__( 'Settings', 'eventslisting' ),
				'manage_options',
				'eventslisting-settings',
				'eventslisting_render_settings_page'
			);
		}
	);

	add_action(
		'admin_init',
		static function () {
			register_setting(
				'eventslisting_settings_group',
				'eventslisting_settings',
				array(
					'sanitize_callback' => 'eventslisting_sanitize_settings',
					'type'              => 'array',
					'show_in_rest'      => false,
				)
			);

			add_settings_section(
				'eventslisting_api_section',
				__( 'Google Maps', 'eventslisting' ),
				static function () {
					echo '<p>' . esc_html__(
						'Add your Google Maps API key to enable richer map integrations.',
						'eventslisting'
					) . '</p>';
				},
				'eventslisting-settings'
			);

			add_settings_field(
				'eventslisting_google_maps_api_key',
				__( 'API Key', 'eventslisting' ),
				static function () {
					$opts = get_option( 'eventslisting_settings', array() );
					$val  = isset( $opts['google_maps_api_key'] ) ? (string) $opts['google_maps_api_key'] : '';
					echo '<input 
									type="text" class="regular-text"
									name="eventslisting_settings[google_maps_api_key]"
									value="' . esc_attr( $val ) . '"
									placeholder="AIza..." />';
					echo '<p class="description">' . esc_html__(
						'This key will be used by themes or custom code to load the Google Maps JavaScript API.',
						'eventslisting'
					) . '</p>';
				},
				'eventslisting-settings',
				'eventslisting_api_section'
			);
		}
	);

	/**
	 * Settings page renderer.
	 */
	function eventslisting_render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Events Settings', 'eventslisting' ); ?></h1>
		<form method="post" action="options.php">
		<?php
		settings_fields( 'eventslisting_settings_group' );
		do_settings_sections( 'eventslisting-settings' );
		submit_button();
		?>
		</form>
	</div>
		<?php
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array<string,mixed> $input Raw settings.
	 * @return array<string,string>
	 */
	function eventslisting_sanitize_settings( $input ): array {
		// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.parameterNameFound
		$clean = array();
		if ( isset( $input['google_maps_api_key'] ) ) {
			$clean['google_maps_api_key'] = sanitize_text_field( (string) $input['google_maps_api_key'] );
		}
		return $clean;
	}

	/**
	 * Helper to get the Google Maps API key when needed.
	 *
	 * @return string
	 */
	function eventslisting_get_google_maps_api_key(): string {
		$opts = get_option( 'eventslisting_settings', array() );
		return isset( $opts['google_maps_api_key'] ) ? (string) $opts['google_maps_api_key'] : '';
	}

	/**
	 * (Optional) Enqueue Google Maps JS automatically on single physical events if a key exists.
	 * Themes can rely on this handle: `google-maps-js`.
	 */
	add_action(
		'wp_enqueue_scripts',
		static function () {
			if ( ! is_singular( Events_Listing_Plugin::CPT ) ) {
				return;
			}
			$api_key = eventslisting_get_google_maps_api_key();
			if ( '' === $api_key ) {
				return;
			}
			$post_id = (int) get_the_ID();
			$type    = (string) get_post_meta( $post_id, '_event_type', true );
			$loc     = (string) get_post_meta( $post_id, '_event_location', true );
			if ( 'physical' !== $type || '' === $loc ) {
				return;
			}
			wp_enqueue_script(
				'google-maps-js',
				add_query_arg(
					'key',
					rawurlencode( $api_key ),
					'https://maps.googleapis.com/maps/api/js'
				),
				array(),
				1,
				true
			);
		},
		20
	);
}
