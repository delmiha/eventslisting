<?php
/**
 * The template for displaying single event
 *
 * @package eventslisting
 */

	get_header();
	the_post();
	$gm_api_key = get_option( 'eventslisting_settings' )['google_maps_api_key'];
?>
<main id="primary" class="site-main">
		<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="entry-content"><?php the_content(); ?></div>
				<?php if ( 'physical' === get_post_meta( get_the_ID(), '_event_type', true ) ) : ?>
				<iframe
	width="600"
	height="450"
	style="border:0"
	loading="lazy"
	allowfullscreen
	referrerpolicy="no-referrer-when-downgrade"
	src="https://www.google.com/maps/embed/v1/place?key=<?php echo rawurlencode( $gm_api_key ); ?>
		&q=<?php echo rawurlencode( get_post_meta( get_the_ID(), '_event_location', true ) ); ?>">
</iframe>
<?php endif; ?>
		</article>
</main>
<?php get_footer(); ?>
