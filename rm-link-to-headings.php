<?php
/**
 * Plugin Name: RM Link to Headings
 * Plugin URI:  https://github.com/Jared-Nolt/RM-Link-to-Headings
 * Description: Displays a list of heading links with a shortcode. The link scrolls to that specific header on the page. ACF repeater field with machine name (link_to_headings) and acf subfield as plain text with machine name (blog_page_headings).
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
     * Constructor for the RM_Link_To_Headings class.
     * Initializes the plugin by hooking into WordPress actions.
     */
    public function __construct() {
        // Hook into 'plugins_loaded' to ensure all plugins are loaded before our initialization.
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
    }

    /**
     * Initializes the plugin's core functionalities.
     * Checks for ACF, adds content filter, and registers the shortcode.
     */
    public function init_plugin() {
        // Check if Advanced Custom Fields (ACF) plugin is active.
        // The 'get_field' function is a reliable way to check for ACF's presence.
        if ( ! function_exists( 'get_field' ) ) {
            // If ACF is not active, display an admin notice and stop further initialization.
            add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
            return;
        }

        // Add a filter to 'the_content' to automatically add unique IDs to H1-H6 headings.
        // This ensures that the shortcode has anchors to link to.
        // Priority 10 is standard, meaning it runs after default WordPress content processing.
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
     * Adds unique IDs to all H1-H6 headings within the post content.
     * This function is hooked into 'the_content' filter and only runs on single posts.
     * It uses DOMDocument to parse and modify the HTML content.
     *
     * @param string $content The original post content.
     * @return string The modified post content with IDs added to headings.
     */
    public function add_ids_to_headings( $content ) {
        // Only process content for single posts (not archives, pages, etc.).
        if ( ! is_single() ) {
            return $content;
        }

        // Reset the heading_ids array for each post content processing.
        // This is crucial to ensure correct mapping for different posts.
        $this->heading_ids = array();

        // Create a new DOMDocument to parse the HTML content.
        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML using '@' and set encoding.
        // LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD prevents adding <html>, <body>, <DOCTYPE> tags.
        @$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        // Create a DOMXPath object to query for heading elements.
        $xpath = new DOMXPath( $dom );
        // Query for all h1, h2, h3, h4, h5, h6 elements.
        $headings = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

        // If headings are found in the content.
        if ( $headings->length > 0 ) {
            foreach ( $headings as $heading ) {
                // Get the plain text content of the heading.
                $heading_text = trim( $heading->textContent );
                // Get any existing 'id' attribute on the heading.
                $heading_id = $heading->getAttribute( 'id' );

                // If the heading does not already have an ID.
                if ( empty( $heading_id ) ) {
                    // Generate a slug from the heading text.
                    $slug = sanitize_title( $heading_text );
                    $unique_slug = $slug;
                    $counter = 1;

                    // Ensure the generated slug is unique within the current post's headings.
                    // This handles cases where multiple headings have the same text.
                    while ( array_key_exists( $unique_slug, $this->heading_ids ) ) {
                        $unique_slug = $slug . '-' . $counter++;
                    }
                    $heading_id = $unique_slug;
                    // Set the generated ID as an attribute on the heading element.
                    $heading->setAttribute( 'id', $heading_id );
                }

                // Store the mapping: normalized heading text => ID.
                // We normalize the text (e.g., lowercase, hyphens instead of spaces) for consistent lookup.
                $normalized_text = sanitize_title( $heading_text );
                $this->heading_ids[ $normalized_text ] = $heading_id;
            }
        }

        // Save the modified DOM back to HTML string.
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
        // Only display the links on single posts.
        if ( ! is_single() ) {
            return '';
        }

        // Get the current post ID.
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return ''; // Return empty if no post ID is available.
        }

        // Retrieve the repeater field data named 'link_to_headings' for the current post.
        // This assumes the ACF repeater field is named 'link_to_headings' and its subfield
        // containing the heading text is also named 'blog_page_headings'.
        $links_data = get_field( 'link_to_headings', $post_id );

        // If no data is found, or it's not a valid array (e.g., empty repeater), return empty.
        if ( empty( $links_data ) || ! is_array( $links_data ) ) {
            return '';
        }

        // Start building the HTML output for the links.
        $output = '<div class="rm-link-to-headings-container">';
        $output .= '<ul>';

        // Loop through each row in the ACF repeater field.
        foreach ( $links_data as $row ) {
            // Safely get the heading text from the ACF repeater subfield.
            // Ensure it's a string and default to empty if the key is not set or value is null.
            $acf_heading_text = isset( $row['blog_page_headings'] ) ? (string) $row['blog_page_headings'] : '';
            $acf_heading_text = trim( $acf_heading_text );

            // Only proceed if the heading text is not empty after trimming.
            if ( ! empty( $acf_heading_text ) ) {
                // Normalize the text from ACF to match how we stored heading IDs.
                $normalized_acf_heading_text = sanitize_title( $acf_heading_text );

                // Try to find the corresponding heading ID from our stored mapping.
                if ( isset( $this->heading_ids[ $normalized_acf_heading_text ] ) ) {
                    $heading_id = $this->heading_ids[ $normalized_acf_heading_text ];
                    // Append a list item with a link to the found heading ID.
                    // esc_attr for the href attribute, esc_html for the link text.
                    $output .= '<li><a href="#' . esc_attr( $heading_id ) . '">' . esc_html( $acf_heading_text ) . '</a></li>';
                } else {
                    // Display a message if the ACF-specified heading wasn't found in the post content.
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
