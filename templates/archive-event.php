<?php
/**
 * The template for displaying events archive
 *
 * @package eventslisting
 */

get_header(); ?>
<main class="wrap">
<h1><?php esc_html_e( 'Events', 'eventslisting' ); ?></h1>
<?php if ( have_posts() ) : ?>
<ul class="el-event-archive">
	<?php
	while ( have_posts() ) :
		the_post();
		$date         = get_post_meta( $post_id, '_event_date', true );
		$event_type   = get_post_meta( $post_id, '_event_type', true );
		$location     = get_post_meta( $post_id, '_event_location', true );
		$external_url = get_post_meta( $post_id, '_event_external_url', true );
		?>
	<li class="el-event-item">
		<a href="<?php the_permalink(); ?>" class="el-event-title"><?php the_title(); ?></a>
		<div class="el-event-meta">
		<span class="el-event-date"><?php echo esc_html( (string) $date ); ?></span>
				<?php if ( 'physical' === $event_type && $location ) : ?>
		<span class="el-event-location"><?php echo esc_html( (string) $location ); ?></span>
				<?php endif; ?>
				<?php if ( $external_url ) : ?>
				<span class="el-event-link">
					<a href="<?php echo esc_url( (string) $external_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Event Site', 'eventslisting' ); ?>
					</a>
				</span>
			<?php endif; ?>
		</div>
		<div class="el-event-actions">
		<a class="button" href="<?php echo esc_url( Events_Listing_Plugin::google_calendar_link( get_the_ID() ) ); ?>" 
			target="_blank" rel="noopener">
				<?php esc_html_e( 'Add to Google Calendar', 'eventslisting' ); ?>
		</a>
		</div>
	</li>
	<?php endwhile; ?>
</ul>
<?php else : ?>
<p><?php esc_html_e( 'No upcoming events.', 'eventslisting' ); ?></p>
<?php endif; ?>
</main>
<?php get_footer(); ?>
