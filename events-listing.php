<?php
/**
 * Plugin Name:       EventsListing
 * Plugin URI:        https://wordpress.org/plugins/eventslisting
 * Description:       Events plugin with custom post type
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Delyan Mihaylov
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eventslisting
 * Domain Path:       /languages
 *
 * @package EventsListing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * On activation/deactivation, flush rewrite rules to register/unregister CPT rules.
 */
register_activation_hook(
	__FILE__,
	static function () {
		// Instantiate to ensure CPT is registered before flushing.
		new Events_Listing_Plugin();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);


/**
 * Main plugin class.
 */
final class Events_Listing_Plugin {
	/** Post type slug */
	public const CPT = 'event';

	/**
	 * Construct Events Listing
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'pre_get_posts', array( $this, 'order_archive_by_event_date' ) );
		add_shortcode( 'event_register', array( $this, 'registration_form_shortcode' ) );

		add_filter(
			'template_include',
			function ( $template ) {
				if ( is_singular( 'event' ) ) {
					return $this->events_locate_template(
						array( 'single-event.php' ),
						plugin_dir_path( __FILE__ ) . 'templates/single-event.php'
					);
				}
				if ( is_post_type_archive( 'event' ) ) {
					return $this->events_locate_template(
						array( 'archive-event.php' ),
						plugin_dir_path( __FILE__ ) . 'templates/archive-event.php'
					);
				}
				return $template;
			},
			99
		);

		add_action(
			'plugins_loaded',
			function () {
				load_plugin_textdomain(
					'eventslisting',
					false,
					dirname( plugin_basename( __FILE__ ) ) . '/languages'
				);
			}
		);
	}

	/**
	 * Register Events custom post type.
	 */
	public function register_cpt(): void {
		$labels = array(
			'name'               => __( 'Events', 'eventslisting' ),
			'singular_name'      => __( 'Event', 'eventslisting' ),
			'add_new'            => __( 'Add New', 'eventslisting' ),
			'add_new_item'       => __( 'Add New Event', 'eventslisting' ),
			'edit_item'          => __( 'Edit Event', 'eventslisting' ),
			'new_item'           => __( 'New Event', 'eventslisting' ),
			'view_item'          => __( 'View Event', 'eventslisting' ),
			'view_items'         => __( 'View Events', 'eventslisting' ),
			'search_items'       => __( 'Search Events', 'eventslisting' ),
			'not_found'          => __( 'No events found.', 'eventslisting' ),
			'not_found_in_trash' => __( 'No events found in Trash.', 'eventslisting' ),
			'all_items'          => __( 'All Events', 'eventslisting' ),
			'archives'           => __( 'Event Archives', 'eventslisting' ),
			'item_published'     => __( 'Event published.', 'eventslisting' ),
			'item_updated'       => __( 'Event updated.', 'eventslisting' ),
		);

		register_post_type(
			self::CPT,
			array(
				'label'        => __( 'Events', 'eventslisting' ),
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array(
					'slug' => 'events',
				),
				'supports'     => array( 'title', 'editor', 'excerpt' ),
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
			)
		);
	}

	/**
	 * Helper to fetch meta with default value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	private function get_meta( int $post_id, string $key, $default_value = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		return ( '' !== $value ) ? $value : $default_value;
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'se_event_details',
			__( 'Event Details', 'eventslisting' ),
			array( $this, 'render_event_details_mb' ),
			self::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'se_event_media',
			__( 'Event Media', 'eventslisting' ),
			array( $this, 'render_event_media_mb' ),
			self::CPT,
			'side',
			'default'
		);
	}

	/**
	 * Render details meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_event_details_mb( $post ): void {
		// phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		wp_nonce_field( 'se_save_event_meta', 'se_event_meta_nonce' );

		$event_date   = esc_attr( (string) $this->get_meta( $post->ID, '_event_date' ) );
		$event_type   = esc_attr( (string) $this->get_meta( $post->ID, '_event_type', 'physical' ) );
		$location     = esc_attr( (string) $this->get_meta( $post->ID, '_event_location' ) );
		$ext_url      = esc_url( (string) $this->get_meta( $post->ID, '_event_external_url' ) );
		$max_att      = (int) $this->get_meta( $post->ID, '_event_max_attendees', 50 );
		$fields_count = (int) $this->get_meta( $post->ID, '_event_reg_fields_count', 3 );
		$cur_count    = (int) $this->get_meta( $post->ID, '_event_current_count', 0 );
		?>
		<style>
			.se-event-field { margin: 8px 0; }
			.se-event-inline { display:flex; gap:8px; align-items:center; }
		</style>
		<div class="se-event-field">
			<label for="_event_date">
				<strong><?php esc_html_e( 'Event Date', 'eventslisting' ); ?></strong>
			</label>
			<br />
			<input type="text" id="_event_date" name="_event_date"
				value="<?php echo esc_attr( $event_date ); ?>"
				class="regular-text se-event-datepicker" 
				placeholder="YYYY-MM-DD" />
		</div>
		<div class="se-event-field">
			<label><strong><?php esc_html_e( 'Event Type', 'eventslisting' ); ?></strong></label><br />
			<label class="se-event-inline">
				<input type="radio" name="_event_type" value="physical" <?php checked( $event_type, 'physical' ); ?> />
				<?php esc_html_e( 'Physical', 'eventslisting' ); ?>
			</label>
			<label class="se-event-inline">
				<input type="radio" name="_event_type" value="online" <?php checked( $event_type, 'online' ); ?> /> 
					<?php esc_html_e( 'Online', 'eventslisting' ); ?>
			</label>
		</div>
		<div class="se-event-field" id="se-event-location-wrap">
			<label for="_event_location">
				<strong><?php esc_html_e( 'Location (shown only if Physical)', 'eventslisting' ); ?></strong>
			</label>
			<br />
			<input 
				type="text" 
				id="_event_location" 
				name="_event_location" 
				value="<?php echo esc_attr( $location ); ?>" 
				class="regular-text" 
				placeholder="123 Main St, City" />
		</div>
		<div class="se-event-field">
			<label for="_event_external_url">
				<strong><?php esc_html_e( 'External URL (event site / livestream)', 'eventslisting' ); ?></strong>
			</label>
			<br />
			<input type="url" id="_event_external_url" 
				name="_event_external_url" 
				value="<?php echo esc_url( $ext_url ); ?>" 
				class="regular-text" placeholder="https://example.com/event" />
		</div>
		<hr />
		<div class="se-event-field se-event-inline">
			<label for="_event_reg_fields_count">
				<strong><?php esc_html_e( 'Registration Fields ("X" fields)', 'eventslisting' ); ?></strong>
			</label>
			<input type="number"
				id="_event_reg_fields_count"
				name="_event_reg_fields_count"
				value="<?php echo esc_attr( (string) $fields_count ); ?>"
				min="1" max="20" />
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s is the shortcode */
						__(
							'Use shortcode %s inside the Event content to render the registration form.',
							'eventslisting'
						),
						'<code>[event_register]</code>'
					)
				);
				?>
			</p>
		</div>
		<hr />
		<div class="se-event-field se-event-inline">
			<label for="_event_max_attendees">
				<strong><?php esc_html_e( 'Max Attendees', 'eventslisting' ); ?></strong>
			</label>
			<input type="number" 
				id="_event_max_attendees" 
				name="_event_max_attendees" 
				value="<?php echo esc_attr( (string) $max_att ); ?>"
				min="1" />
			<span><?php esc_html_e( 'Current:', 'eventslisting' ); ?>
				<code><?php echo esc_html( (string) $cur_count ); ?></code>
			</span>
		</div>		
		<script>
			(function(){
				function toggleLocation(){
					let type = document.querySelector('input[name="_event_type"]:checked').value;
					let displayStyle = (type === 'physical') ? 'block' : 'none';
					document.getElementById('se-event-location-wrap').style.display = displayStyle;
				}
				document.querySelectorAll('input[name="_event_type"]').forEach(
					function(r){ r.addEventListener('change', toggleLocation); }
				);
				toggleLocation();
			})();
		</script>
		<?php
	}

	/**
	 * Render media meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_event_media_mb( $post ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		$video_url  = esc_url( (string) $this->get_meta( $post->ID, '_event_video_url' ) );
		$banner_id  = (int) $this->get_meta( $post->ID, '_event_banner_id' );
		$banner_src = $banner_id ? wp_get_attachment_image_src( $banner_id, 'medium' )[0] : '';
		?>
		<p>
			<label for="_event_video_url">
				<strong><?php esc_html_e( 'Video/Trailer URL', 'eventslisting' ); ?></strong>
			</label>
			<br />
			<input type="url"
				id="_event_video_url" 
				name="_event_video_url" 
				class="widefat" 
				value="<?php echo esc_url( $video_url ); ?>" 
				placeholder="https://youtu.be/..." />
		</p>
		<p><strong><?php esc_html_e( 'Banner Image', 'eventslisting' ); ?></strong></p>
		<div id="se-event-banner-preview">
		<?php
		if ( $banner_src ) {
			echo '<img alt="" src="' . esc_url( $banner_src ) . '" 
				style="max-width:100%;height:auto;display:block;" />';
		}
		?>
		</div>
		<input type="hidden" 
			id="_event_banner_id" 
			name="_event_banner_id" 
			value="<?php echo esc_attr( (string) $banner_id ); ?>" />
		<p>
			<button type="button" class="button" id="se-event-banner-upload">
				<?php esc_html_e( 'Choose Image', 'eventslisting' ); ?>
			</button>
			<button type="button" class="button" id="se-event-banner-remove">
				<?php esc_html_e( 'Remove', 'eventslisting' ); ?>
			</button>
		</p>
		<script>
			jQuery(function($){
				let frame;
				$('#se-event-banner-upload').on('click', function(e){
					e.preventDefault();
					if(frame){ frame.open(); return; }
					frame = wp.media(
						{
							title: '<?php echo esc_js( __( 'Select Banner', 'eventslisting' ) ); ?>',
							button: { text: '<?php echo esc_js( __( 'Use this image', 'eventslisting' ) ); ?>' },
							multiple:false
						}
					);
					frame.on('select', function(){
						let att = frame.state().get('selection').first().toJSON();
						let attUrl = (att.sizes.medium ? att.sizes.medium.url : att.url);
						$('#_event_banner_id').val(att.id);
						$('#se-event-banner-preview').html(
							'<img alt="" style="max-width:100%;height:auto;display:block;" src="'+attUrl+'">'
						);
					});
					frame.open();
				});
				$('#se-event-banner-remove').on('click', function(){
					$('#_event_banner_id').val('');
					$('#se-event-banner-preview').empty();
				});
			});
		</script>
		<?php
	}

	/**
	 * Admin assets (date picker + media).
	 *
	 * @param string $hook Current admin page.
	 */
	public function admin_assets( string $hook ): void {
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style(
				'jquery-ui-css',
				'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
				array(),
				'1.13.2'
			);
			wp_add_inline_script(
				'jquery-ui-datepicker',
				'jQuery(function($){ $(".se-event-datepicker").datepicker({ dateFormat:"yy-mm-dd" }); });'
			);
			wp_enqueue_media();
		}
	}

	/**
	 * Front-end styles.
	 */
	public function frontend_assets(): void {
		$handle = 'eventslisting';
		$src    = plugins_url( '/assets/css/eventslisting.css', __FILE__ );
		wp_register_style( $handle, $src, array(), '1.1.0' );
		wp_enqueue_style( $handle );
	}

	/**
	 * Save meta fields.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( int $post_id ): void {
		// Verify nonce.
		if (
			! isset( $_POST['se_event_meta_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['se_event_meta_nonce'] ) ),
				'se_save_event_meta'
			)
		) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		// Autosave?
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$map = array(
			'_event_date'             => 'text',
			'_event_type'             => 'text',
			'_event_location'         => 'text',
			'_event_external_url'     => 'url',
			'_event_max_attendees'    => 'int',
			'_event_video_url'        => 'url',
			'_event_reg_fields_count' => 'int',
			'_event_banner_id'        => 'int',
		);

		foreach ( $map as $key => $type ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}
			$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			switch ( $type ) {
				case 'int':
					$value = max( 0, (int) $value );
					break;
				case 'url':
					$value = esc_url_raw( (string) $value );
					break;
				default:
					$value = sanitize_text_field( (string) $value );
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Order event archives by event date (ascending).
	 *
	 * @param WP_Query $query Query.
	 */
	public function order_archive_by_event_date( $query ): void {
		if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( self::CPT ) ) {
			$query->set( 'meta_key', '_event_date' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'order', 'ASC' );
			$query->set( 'meta_type', 'DATE' );
		}
	}

	/**
	 * Generate a Google Calendar link for an event.
	 *
	 * @param int $post_id Post ID.
	 * @return string URL.
	 */
	public static function google_calendar_link( int $post_id ): string {
		$title      = rawurlencode( (string) get_the_title( $post_id ) );
		$event_date = (string) get_post_meta( $post_id, '_event_date', true ); // YYYY-MM-DD.
		if ( empty( $event_date ) ) {
			return '';
		}
		$start    = gmdate( 'Ymd', strtotime( $event_date ) );
		$end      = gmdate( 'Ymd', strtotime( $event_date . ' +1 day' ) );
		$details  = rawurlencode( wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ) );
		$location = rawurlencode( (string) get_post_meta( $post_id, '_event_location', true ) );

		$url = add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => $title,
				'dates'    => $start . '/' . $end,
				'details'  => $details,
				'location' => $location,
			),
			'https://calendar.google.com/calendar/render'
		);
		return (string) esc_url_raw( $url );
	}



	/**
	 * Shortcode: [event_register] — renders a basic registration form with X inputs.
	 * Handles capacity logic by comparing current vs max attendees.
	 *
	 * @return string HTML output.
	 */
	public function registration_form_shortcode(): string {
		if ( ! is_singular( self::CPT ) ) {
			return '';
		}

		$post_id = (int) get_the_ID();
		$max     = (int) get_post_meta( $post_id, '_event_max_attendees', true );
		$current = (int) get_post_meta( $post_id, '_event_current_count', true );
		$fields  = max( 1, (int) get_post_meta( $post_id, '_event_reg_fields_count', true ) );

		$is_full = ( $max > 0 && $current >= $max );

		$notice = '';
		// Handle submission.
		if (
			'POST' === $_SERVER['REQUEST_METHOD'] &&
			isset( $_POST['se_event_register_nonce'] ) &&
			wp_verify_nonce(
				sanitize_text_field(
					wp_unslash( $_POST['se_event_register_nonce'] )
				),
				'se_event_register'
			)
			) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $is_full ) {
				++$current;
				update_post_meta( $post_id, '_event_current_count', $current );
				$notice = '<div class="se-event-success">'
					. esc_html__( "Thanks! You're registered.", 'eventslisting' ) .
				'</div>';
			} else {
				$notice = '<div class="se-event-full">'
					. esc_html__( 'Registration is closed — maximum reached.', 'eventslisting' ) .
				'</div>';
			}
		}

		ob_start();
		?>
	<div class="se-event-register">
		<?php echo wp_kses_post( $notice ); ?>
		<?php if ( $is_full ) : ?>
		<div class="se-event-full">
			<?php
			esc_html_e(
				'Registration is closed — we\'ve reached the maximum number of attendees.',
				'eventslisting'
			);
			?>
		</div>
	<?php else : ?>
	<form method="post" class="se-event-register-form">
		<?php wp_nonce_field( 'se_event_register', 'se_event_register_nonce' ); ?>
		<?php for ( $i = 1; $i <= $fields; $i++ ) : ?>
	<p>
		<label>
			<?php
				echo esc_html(
					sprintf(
					/* translators: %s is the number */
						__( 'Field %d', 'eventslisting' ),
						$i
					)
				);
			?>
			<br />
			<input type="text" name="se_field_<?php echo esc_attr( (string) $i ); ?>" required />
		</label>
	</p>
	<?php endfor; ?>
	<p>
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Register', 'eventslisting' ); ?></button>
	</p>
	</form>
	<?php endif; ?>
	</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Locate plugin templates for archive and single post.
	 *
	 * @param string $candidates Text.
	 * @param string $plugin_fallback Text.
	 * @return string
	 */
	private function events_locate_template( $candidates, $plugin_fallback ) {
		$theme_file = locate_template( $candidates );
		return $theme_file ? $theme_file : $plugin_fallback;
	}

	/**
	 * Echo a minimal ICS for all-day event.
	 *
	 * @param int $post_id Post ID.
	 */
	public function output_ics( int $post_id ): void {
		$title   = wp_strip_all_tags( (string) get_the_title( $post_id ) );
		$desc    = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
		$date    = (string) get_post_meta( $post_id, '_event_date', true );
		$loc     = wp_strip_all_tags( (string) get_post_meta( $post_id, '_event_location', true ) );
		$uid     = $post_id . '@' . (string) wp_parse_url( (string) home_url(), PHP_URL_HOST );
		$dtstart = gmdate( 'Ymd', strtotime( $date ) );
		$dtend   = gmdate( 'Ymd', strtotime( $date . ' +1 day' ) );

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="event-' . (int) $post_id . '.ics"' );
		echo 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//eventslisting//EN
CALSCALE:GREGORIAN
'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo 'BEGIN:VEVENT
UID:' . esc_html( $this->ics_escape( $uid ) ) . '
SUMMARY:' . esc_html( $this->ics_escape( $title ) ) . '
DESCRIPTION:' . esc_html( $this->ics_escape( $desc ) ) . '
LOCATION:' . esc_html( $this->ics_escape( $loc ) ) . '
DTSTART;VALUE=DATE:' . esc_html( $dtstart ) . '
DTEND;VALUE=DATE:' . esc_html( $dtend ) . '
END:VEVENT
END:VCALENDAR'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Escape text for ICS.
	 *
	 * @param string $str Text.
	 * @return string
	 */
	private function ics_escape( string $str ): string {
		return (string) preg_replace(
			'/([\\;,])/',
			'\$1',
			str_replace(
				'
',
				'\n',
				$str
			)
		);
	}
}

new Events_Listing_Plugin();

require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
