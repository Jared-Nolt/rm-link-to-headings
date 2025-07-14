<?php
/**
 * Plugin Name: RM Link to Headings
 * Plugin URI:  https://github.com/Jared-Nolt/RM-Link-to-Headings
 * Description: Displays a list of heading links that scroll to that specific header on the page. ACF repeater field with machine name (link_to_headings) and acf subfield as plain text with machine name (blog_page_headings). The heading links are shown with a shortcode.
 * Version:     1.0.0
 * Author:      Jared Nolt
 * Author URI:  https://rosewood.us.com/about/team/jared-nolt/
 * License:     GNU3
 * Text Domain: rm-link-to-headings
 */

// Exit if accessed directly to prevent unauthorized access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RM_Link_To_Headings Class
 * Manages the functionality of the RM Link to Headings plugin.
 */
class RM_Link_To_Headings {

    /**
     * @var array Stores a mapping of normalized heading text to their generated HTML IDs.
     * This is populated when 'the_content' filter runs.
     */
    private $heading_ids = array();

    /**
     * Constructor for the RM_Link_To_Headings class. Initializes the plugin by hooking into WordPress actions.
     */
    public function __construct() {
        // Hook into 'plugins_loaded' to ensure all plugins are loaded before our initialization.
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
    }

    /**
     * Initializes the plugin's core functionalities. Checks for ACF, adds content filter, and registers the shortcode.
     */
    public function init_plugin() {
        if ( ! function_exists( 'get_field' ) ) {
            add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
            return;
        }
        add_filter( 'the_content', array( $this, 'add_ids_to_headings' ), 10 );

        // Register the shortcode '[rm_link_to_headings]' which will display the links.
        add_shortcode( 'rm_link_to_headings', array( $this, 'rm_link_to_headings_shortcode' ) );
    }

    /**
     * Displays an admin notice if the Advanced Custom Fields (ACF) plugin is not active.
     */
    public function acf_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e( 'RM Link to Headings requires Advanced Custom Fields (ACF) plugin to be active. Please install and activate it.', 'rm-link-to-headings' ); ?></p>
        </div>
        <?php
    }

    /**
     * Adds unique IDs to all H2-H5 headings within the post content.
     *
     * @param string $content The original post content.
     * @return string The modified post content with IDs added to headings.
     */
    public function add_ids_to_headings( $content ) {
        // Only process content for single posts (not archives, pages, etc.).
        if ( ! is_single() ) {
            return $content;
        }

        $this->heading_ids = array();

        $dom = new DOMDocument();
        @$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        $xpath = new DOMXPath( $dom );
        $headings = $xpath->query( '//h2|//h3|//h4|//h5|//h6' );

        if ( $headings->length > 0 ) {
            foreach ( $headings as $heading ) {
                $heading_text = trim( $heading->textContent );
                $heading_id = $heading->getAttribute( 'id' );

                if ( empty( $heading_id ) ) {
                    $slug = sanitize_title( $heading_text );
                    $unique_slug = $slug;
                    $counter = 1;

                    while ( array_key_exists( $unique_slug, $this->heading_ids ) ) {
                        $unique_slug = $slug . '-' . $counter++;
                    }
                    $heading_id = $unique_slug;
                    $heading->setAttribute( 'id', $heading_id );
                }

                $normalized_text = sanitize_title( $heading_text );
                $this->heading_ids[ $normalized_text ] = $heading_id;
            }
        }

        $modified_content = $dom->saveHTML();

        return $modified_content;
    }

    /**
     * Shortcode callback function to display links to headings.
     * Use [rm_link_to_headings] in your post content.
     *
     * @param array $atts Shortcode attributes (not used in this version).
     * @return string HTML output of the list of links, or an empty string if no data.
     */
    public function rm_link_to_headings_shortcode( $atts ) {
        if ( ! is_single() ) {
            return '';
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return ''; // Return empty if no post ID is available.
        }

        $links_data = get_field( 'link_to_headings', $post_id );

        if ( empty( $links_data ) || ! is_array( $links_data ) ) {
            return '';
        }

        $output = '<div class="rm-link-to-headings-container">';
        $output .= '<ul>';

        foreach ( $links_data as $row ) {
            $acf_heading_text = isset( $row['blog_page_headings'] ) ? (string) $row['blog_page_headings'] : '';
            $acf_heading_text = trim( $acf_heading_text );

            if ( ! empty( $acf_heading_text ) ) {
                $normalized_acf_heading_text = sanitize_title( $acf_heading_text );

                if ( isset( $this->heading_ids[ $normalized_acf_heading_text ] ) ) {
                    $heading_id = $this->heading_ids[ $normalized_acf_heading_text ];
                    $output .= '<li><a href="#' . esc_attr( $heading_id ) . '">' . esc_html( $acf_heading_text ) . '</a></li>';
                } else {
                    $output .= '<li>' . esc_html( $acf_heading_text ) . ' (Heading not found in content)</li>';
                }
            }
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }
}

// Instantiate the plugin class to start its functionality.
new RM_Link_To_Headings();
