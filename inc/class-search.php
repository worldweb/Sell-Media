<?php

/**
 * Search Class
 *
 * @package Sell Media
 * @author Thad Allender <support@graphpaperpress.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SellMediaSearch {

	private $settings;

	/**
	 * Init
	 */
	public function __construct() {

		// Restrict in admin area.
		if ( is_admin() ) {
			return;
		}

		$this->settings = sell_media_get_plugin_options();

		// Add a media search form shortcode
		add_shortcode( 'sell_media_search', array( $this, 'form' ) );

		// Legacy add a media search form shortcode
		add_shortcode( 'sell_media_searchform', array( $this, 'form' ) );

	}


	/**
	 * Search form
	 *
	 * @since 1.8.7
	 */
	public function form( $url = null, $used = null ) {

		$settings = sell_media_get_plugin_options();
		$html = '';

		// Show a message to admins if they don't have search page set in settings.
		if ( current_user_can( 'administrator' ) && empty( $settings->search_page ) ) {
			$html .= esc_html__( 'For search to work, you must assign your Search Page in Sell Media -> Settings.', 'sell_media' );
			return $html;
		}

		// Get the search term
		$search_term = ( isset( $_GET['keyword'] ) ) ? $_GET['keyword'] : '';

		// Get the file type
		$type = ( isset( $_GET['type'] ) ) ? $_GET['type'] : '';

		// only use this method if it hasn't already been used on the page
		static $used;
		if ( ! isset( $used ) ) {
			$used = true;

			$html .= '<div class="sell-media-search">';
			$html .= '<form role="search" method="get" id="sell-media-search-form" class="sell-media-search-form" action="' . esc_url( get_permalink( $settings->search_page ) ) . '">';
			$html .= '<div class="sell-media-search-inner cf">';

			// Visible search options wrapper
			$html .= '<div id="sell-media-search-visible" class="sell-media-search-visible cf">';

			// Input field
			$html .= '<div id="sell-media-search-query" class="sell-media-search-field sell-media-search-query">';
			$html .= '<input type="text" value="' . $search_term . '" name="keyword" id="sell-media-search-text" class="sell-media-search-text" placeholder="' . apply_filters( 'sell_media_search_placeholder', sprintf( __( 'Search for %1$s', 'sell_media' ), empty( $settings->post_type_slug ) ? 'keywords' : $settings->post_type_slug ) ) . '"/>';
			$html .= '</div>';

			// Submit button
			$html .= '<div id="sell-media-search-submit" class="sell-media-search-field sell-media-search-submit">';
			$html .= '<input type="submit" id="sell-media-search-submit-button" class="sell-media-search-submit-button" value="' . apply_filters( 'sell_media_search_button', __( 'Search', 'sell_media' ) ) . '" />';
			$html .= '</div>';

			$html .= '</div>';

			// Hidden search options wrapper
			$html .= '<div id="sell-media-search-hidden" class="sell-media-search-hidden cf">';

			// File type field
			$html .= '<div id="sell-media-search-file-type" class="sell-media-search-field sell-media-search-file-type">';
			$html .= '<label for="type">' . esc_html__( 'File Type', 'sell_media' ) . '</label>';
			$html .= '<select name="type">';
			$html .= '<option value="">' . esc_html__( 'All', 'sell_media' ) . '</option>';
			$mimes = array( 'image', 'video', 'audio' );
			foreach ( $mimes as $mime ) {
				$selected = ( $type === $mime ) ? 'selected' : '';
				$html .= '<option value="' . $mime . '" '.  $selected . '>';
				$html .= ucfirst( esc_html__( $mime, 'sell_media' ) );
				$html .= '</option>';
			}

			$html .= '</select>';
			$html .= '</div>';

			// Hidden search options wrapper
			$html .= '</div>';

			$html .= '</div>';
			$html .= '</form>';
			$html .= '</div>';

		}

		// only run the query on the actual search results page.
		if ( is_page( $settings->search_page ) && 1 === did_action( 'the_post' ) ) {

			// The search terms
			$search_terms = str_getcsv( $search_term, ' ' );

			// The file type
			$mime_type = $this->get_mimetype( $type );

			// The Query
			$args = array(
				'post_type' => 'attachment',
				'post_status' => array( 'publish', 'inherit' ),
				'post_mime_type' => $mime_type,
				'post_parent__in' => sell_media_ids(),
				'tax_query' => array(
					array(
						'taxonomy' => 'keywords',
						'field'    => 'name',
						'terms'    => $search_terms,
					),
				),
				// 'meta_query' => array(
				// 	 array(
				// 		'key' => '_sell_media_for_sale_product_id',
				// 		'compare' => 'EXISTS',
				// 	),
				// ),
			);
			$search_query = new WP_Query( $args );
			$i = 0;

			// The Loop
			if ( $search_query->have_posts() ) {

				$html .= '<p class="sell-media-search-results-text">' . sprintf( esc_html__( 'We found %1$s results for "%2$s."', 'sell_media' ), $search_query->post_count, $search_term ) . '</p>';

				$html .= '<div id="sell-media-archive" class="sell-media">';
				$html .= '<div class="' . apply_filters( 'sell_media_grid_item_container_class', 'sell-media-grid-item-container' ) . '">';

				while ( $search_query->have_posts() ) {
					$search_query->the_post();
					$i++;
					$html .= apply_filters( 'sell_media_content_loop', get_the_ID(), $i );
				}

				$html .= '</div>';
				$html .= '</div>';
				$text = esc_html__( 'Explore more from our store', 'sell_media' );
				$html .= '<p class="sell-media-search-results-text">' . $text . '</p>';
				$html .= do_shortcode( '[sell_media_filters]' );

			} else {
				if ( $search_term ) {
					$text = esc_html__( 'Sorry, no results. Explore more from our store below.', 'sell_media' );
				} else {
					$text = esc_html__( 'Search for keywords above or explore more from our store below.', 'sell_media' );
				}
				$html .= '<p class="sell-media-search-results-text">' . $text . '</p>';
				$html .= do_shortcode( '[sell_media_filters]' );
			}

			/* Restore original Post Data */
			wp_reset_postdata();
			$i = 0;

		} // end search results page check

		return $html;
	}

	/**
	 * Get the select value of the filetype field and conver it into a WP mimtype for WP_Query
	 * 
	 * @param  string 		The filetype (image, video, audio)
	 * @return array 		The WP mimetype format for each filetype
	 */
	private function get_mimetype( $filetype ) {
		if ( 'image' === $filetype ) {
			$mime = array( 'image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/tiff', 'image/x-icon' );
		} elseif ( 'video' === $filetype ) {
			$mime = array( 'video/x-ms-asf', 'video/x-ms-wmv', 'video/x-ms-wmx', 'video/x-ms-wm', 'video/avi', 'video/divx', 'video/x-flv', 'video/quicktime', 'video/mpeg', 'video/mp4', 'video/ogg', 'video/webm', 'video/x-matroska' );
		} elseif ( 'audio' === $filetype ) {
			$mime = array( 'audio/mpeg', 'audio/x-realaudio', 'audio/wav', 'audio/ogg', 'audio/midi', 'audio/x-ms-wma', 'audio/x-ms-wax', 'audio/x-matroska' );
		} else {
			$mime = '';
		}

		return $mime;
	}

}
