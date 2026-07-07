<?php
/**
 * Blank-canvas full-page reading template.
 *
 * Self-contained document: never loads theme templates, but keeps
 * wp_head()/wp_footer() so caching, analytics, and security plugins work.
 *
 * @package Bindr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bindr_post     = get_queried_object();
$bindr_fullpage = bindr_plugin()->get( 'fullpage' );
$bindr_logo_id  = (int) bindr_get_setting( 'fullpage_logo_id' );
$bindr_fallback = Bindr_Fullpage::fallback_url();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
	<style>
		/* Kill theme layout inside our own document; the viewer CSS handles the rest. */
		html, body { margin: 0 !important; padding: 0 !important; height: 100%; overflow: hidden; }
		body > *:not(.bindr-fullpage) { display: none !important; }
		body > .bindr-fullpage { display: flex !important; }
	</style>
</head>
<body <?php body_class( 'bindr-fullpage-body' ); ?>>
<?php wp_body_open(); ?>
<div class="bindr-fullpage">
	<header class="bindr-fullpage__bar">
		<a class="bindr-fullpage__back" href="<?php echo esc_url( $bindr_fallback ); ?>" data-bindr-back>
			<span class="bindr-fullpage__back-arrow" aria-hidden="true">&larr;</span>
			<?php esc_html_e( 'Back', 'wp-bindr' ); ?>
		</a>
		<h1 class="bindr-fullpage__title"><?php echo esc_html( get_the_title( $bindr_post ) ); ?></h1>
		<?php if ( $bindr_logo_id ) : ?>
			<span class="bindr-fullpage__logo">
				<?php echo wp_get_attachment_image( $bindr_logo_id, 'medium', false, array( 'alt' => get_bloginfo( 'name' ) ) ); ?>
			</span>
		<?php else : ?>
			<span class="bindr-fullpage__logo bindr-fullpage__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
		<?php endif; ?>
	</header>
	<main class="bindr-fullpage__stage">
		<?php echo $bindr_fullpage->render_viewer( $bindr_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Viewer markup is escaped internally. ?>
	</main>
</div>
<script>
	// Same-origin referrer → real history back; otherwise the fallback href stays.
	( function () {
		var back = document.querySelector( '[data-bindr-back]' );
		if ( back && document.referrer && document.referrer.indexOf( window.location.origin + '/' ) === 0 ) {
			back.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				window.history.back();
			} );
		}
	} )();
</script>
<?php wp_footer(); ?>
</body>
</html>
