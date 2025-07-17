<?php
/**
 * Plugin Name: RM Link to Headings
 * Plugin URI:  https://github.com/Jared-Nolt/RM-Link-to-Headings
 * Description: Displays a list of heading links that scroll to that specific header on the page. Configurable ACF repeater field and subfield names via admin UI. The heading links are shown with a shortcode.
 * Version:     1.1.0
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

        // Add filter for content processing to add IDs to headings.
        add_filter( 'the_content', array( $this, 'add_ids_to_headings' ), 10 );

        // Register the shortcode '[rm_link_to_headings]' which will display the links.
        add_shortcode( 'rm_link_to_headings', array( $this, 'rm_link_to_headings_shortcode' ) );

        // Add admin menu and settings.
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
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
     *
     * @param string $content The original post content.
     * @return string The modified post content with IDs added to headings.
     */
    public function add_ids_to_headings( $content ) {
        // Only process content for single posts (not archives, pages, etc.).
        if ( ! is_single() ) {
            return $content;
        }

        // If the content is empty, there are no headings to process.
        // Return the content as-is to avoid unnecessary DOM operations.
        if ( empty( $content ) ) {
            return $content;
        }

        $this->heading_ids = array();

        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML using '@' and set encoding.
        // LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD prevents adding <html>, <body>, <DOCTYPE> tags.
        @$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        $xpath = new DOMXPath( $dom );
        // Query for h1 to h6 elements.
        $headings = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

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

        // Get the ACF repeater field name and subfield name from plugin settings.
        $repeater_field_name = get_option( 'rm_lth_repeater_field_name', 'link_to_headings' );
        $subfield_name = get_option( 'rm_lth_subfield_name', 'blog_page_headings' );
        $list_title = get_option( 'rm_lth_list_title', 'Table of Contents' );


        // Retrieve the repeater field data using the configurable name.
        $links_data = get_field( $repeater_field_name, $post_id );

        // If no data is found, or it's not a valid array (e.g., empty repeater), return empty.
        if ( empty( $links_data ) || ! is_array( $links_data ) ) {
            return '';
        }

        $list_items = []; // Array to store generated <li> elements

        foreach ( $links_data as $row ) {
            // Safely get the heading text from the ACF repeater subfield using the configurable name.
            // Ensure it's a string and default to empty if the key is not set or value is null.
            $acf_heading_text = isset( $row[ $subfield_name ] ) ? (string) $row[ $subfield_name ] : '';
            $acf_heading_text = trim( $acf_heading_text );

            if ( ! empty( $acf_heading_text ) ) {
                $normalized_acf_heading_text = sanitize_title( $acf_heading_text );

                if ( isset( $this->heading_ids[ $normalized_acf_heading_text ] ) ) {
                    $heading_id = $this->heading_ids[ $normalized_acf_heading_text ];
                    $list_items[] = '<li><a href="#' . esc_attr( $heading_id ) . '">' . esc_html( $acf_heading_text ) . '</a></li>';
                } else {
                    // Display a message if the ACF-specified heading wasn't found in the post content.
                    $list_items[] = '<li>' . esc_html( $acf_heading_text ) . ' (Heading not found in content)</li>';
                }
            }
        }

        // Only output the container and list if there are actual list items to display.
        if ( ! empty( $list_items ) ) {
            $output = '<div class="rm-link-to-headings-container">';
            // Add the title if it's set and not empty
            if ( ! empty( $list_title ) ) {
                $output .= '<h2>' . esc_html( $list_title ) . '</h2>';
            }
            $output .= '<style>
                .rm-link-to-headings-container ul {
                    list-style: none;
                    padding: 0;
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }
            </style>';
            $output .= '<ul>';
            $output .= implode( '', $list_items ); // Join all collected list items
            $output .= '</ul>';
            $output .= '</div>';
            return $output;
        }

        // If no list items were generated, return an empty string.
        return '';
    }

    /**
     * Adds the plugin's settings page to the WordPress admin menu.
     */
    public function add_plugin_admin_menu() {
        add_options_page(
            __( 'RM Link to Headings Settings', 'rm-link-to-headings' ), // Page title
            __( 'RM Link to Headings', 'rm-link-to-headings' ),          // Menu title
            'manage_options',                                            // Capability required to access
            'rm-link-to-headings',                                       // Menu slug
            array( $this, 'plugin_settings_page_content' )               // Callback function to render the page
        );
    }

    /**
     * Renders the content of the plugin's settings page.
     */
    public function plugin_settings_page_content() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'RM Link to Headings Settings', 'rm-link-to-headings' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                // Output security fields for the registered setting.
                settings_fields( 'rm_lth_settings_group' );
                // Output settings sections and their fields.
                do_settings_sections( 'rm-link-to-headings' );
                // Output submit button.
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registers the plugin's settings and fields using the WordPress Settings API.
     */
    public function register_plugin_settings() {
        // Register a setting group.
        register_setting(
            'rm_lth_settings_group', // Option group
            'rm_lth_repeater_field_name', // Option name (for repeater field)
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'link_to_headings',
            )
        );

        register_setting(
            'rm_lth_settings_group', // Option group
            'rm_lth_subfield_name',  // Option name (for subfield)
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'blog_page_headings',
            )
        );

        // Register new setting for the list title.
        register_setting(
            'rm_lth_settings_group', // Option group
            'rm_lth_list_title',     // Option name (for list title)
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'Table of Contents',
            )
        );

        // Add a settings section.
        add_settings_section(
            'rm_lth_general_settings_section', // ID
            __( 'ACF Field Configuration', 'rm-link-to-headings' ), // Title
            array( $this, 'general_settings_section_callback' ), // Callback to render section description
            'rm-link-to-headings' // Page on which to add the section
        );

        // Add a settings field for the repeater field name.
        add_settings_field(
            'rm_lth_repeater_field_name_id', // ID
            __( 'ACF Repeater Field Name', 'rm-link-to-headings' ), // Title
            array( $this, 'repeater_field_name_callback' ), // Callback to render field input
            'rm-link-to-headings', // Page on which to add the field
            'rm_lth_general_settings_section' // Section to which to add the field
        );

        // Add a settings field for the subfield name.
        add_settings_field(
            'rm_lth_subfield_name_id', // ID
            __( 'ACF Subfield Name (within Repeater)', 'rm-link-to-headings' ), // Title
            array( $this, 'subfield_name_callback' ), // Callback to render field input
            'rm-link-to-headings', // Page on which to add the field
            'rm_lth_general_settings_section' // Section to which to add the field
        );

        // Add a settings field for the list title.
        add_settings_field(
            'rm_lth_list_title_id', // ID
            __( 'List Title', 'rm-link-to-headings' ), // Title
            array( $this, 'list_title_callback' ), // Callback to render field input
            'rm-link-to-headings', // Page on which to add the field
            'rm_lth_general_settings_section' // Section to which to add the field
        );
    }

    /**
     * Callback for the general settings section.
     */
    public function general_settings_section_callback() {
        echo '<p>' . __( 'Enter the machine names for your ACF Repeater field and its subfield that contain the heading texts, and set a title for the generated list.', 'rm-link-to-headings' ) . '</p>';
    }

    /**
     * Callback to render the input field for the ACF Repeater Field Name.
     */
    public function repeater_field_name_callback() {
        $repeater_field_name = get_option( 'rm_lth_repeater_field_name', 'link_to_headings' );
        echo '<input type="text" id="rm_lth_repeater_field_name_id" name="rm_lth_repeater_field_name" value="' . esc_attr( $repeater_field_name ) . '" class="regular-text" />';
        echo '<p class="description">' . __( 'The machine name of your ACF Repeater field (e.g., <code>link_to_headings</code>).', 'rm-link-to-headings' ) . '</p>';
    }

    /**
     * Callback to render the input field for the ACF Subfield Name.
     */
    public function subfield_name_callback() {
        $subfield_name = get_option( 'rm_lth_subfield_name', 'blog_page_headings' );
        echo '<input type="text" id="rm_lth_subfield_name_id" name="rm_lth_subfield_name" value="' . esc_attr( $subfield_name ) . '" class="regular-text" />';
        echo '<p class="description">' . __( 'The machine name of the text subfield within your Repeater that holds the heading text (e.g., <code>blog_page_headings</code>).', 'rm-link-to-headings' ) . '</p>';
    }

    /**
     * Callback to render the input field for the List Title.
     */
    public function list_title_callback() {
        $list_title = get_option( 'rm_lth_list_title', 'Table of Contents' );
        echo '<input type="text" id="rm_lth_list_title_id" name="rm_lth_list_title" value="' . esc_attr( $list_title ) . '" class="regular-text" />';
        echo '<p class="description">' . __( 'Enter a title for the list of heading links (e.g., <code>Table of Contents</code>). This title will only show if there are links to display.', 'rm-link-to-headings' ) . '</p>';
    }
}

// Instantiate the plugin class to start its functionality.
new RM_Link_To_Headings();
