<?php
/**
 * Template part for displaying posts.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Shapely
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class('post-snippet mb64'.( is_single() ? ' content': "")); ?>>
	<header class="entry-header nolist">
		<?php

		Jay_posted_on();	?><!-- post-meta -->

	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php
            if( !is_single() ){
                the_excerpt();
            }
            else{
                the_content( sprintf(
                    /* translators: %s: Name of current post. */
                    wp_kses( __( 'Continue reading %s <span class="meta-nav">&rarr;</span>', 'shapely' ), array( 'span' => array( 'class' => array() ) ) ),
                    the_title( '<span class="screen-reader-text">"', '"</span>', false )
                ) );

                echo '<hr>';
            }

		?>
	</div><!-- .entry-content -->

</article><!-- #post-## -->