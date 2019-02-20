<?php
/**
 * Plugin Name: Simple Form
 * Plugin URI:  https://github.com/donliggett/wp-simple-form
 * Description: A simple form plugin for Wordpress.
 * Version:     0.1.0
 * Author:      Don Liggett
 * Author URI:  http://donliggett.github.io/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-simple-form
 * Domain Path: /languages
 */

require_once( __DIR__ . '/includes/class-PHPFormBuilder.php' );
require_once( __DIR__ . '/admin/class-WPSimpleFormAdmin.php' );

// Main Plugin Class
if ( ! class_exists( 'WPSimpleForm' ) ) {
    class WPSimpleForm {
        public function __construct() {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_shortcode( 'wpsimpleform', array( $this, 'form' ) );
            add_action( 'admin_post_nopriv_wpsf_contact_form', array( $this, 'form_handler' ) );
            add_action( 'admin_post_wpsf_contact_form', array( $this, 'form_handler' ) );
        }
        
        public function enqueue_scripts() {
            wp_enqueue_style( 'wpsimpleform', plugins_url( '/public/css/style.css', __FILE__ ), array(), 0.1 );
        }
        
        public function form($atts) {
            global $post;
            
            $atts = shortcode_atts(
            array(
              'add_honeypot' => false,
            ), $atts, 'wpsimpleform' );
          
        // Instantiate form class
        $form = new PHPFormBuilder();
        
        // Set form options
        $form->set_att( 'action', esc_url( admin_url( 'admin-post.php' ) ) );
        $form->set_att( 'add_honeypot', $atts['add_honeypot'] );
        
        // Add form inputs
        $form->add_input( 'action', array(
              'type' => 'hidden',
              'value' => 'wpsf_contact_form',
              ), 'action' );
            
        $form->add_input( 'wp_nonce', array(
            'type' => 'hidden',
            'value' => wp_create_nonce( 'submit_wp_simple_form' ),
            ), 'wp_nonce' );
            
        $form->add_input( 'redirect_id', array(
            'type' => 'hidden',
            'value' => $post->ID,
            ), 'redirect_id' );
            
        $form->add_input( __( 'Name', 'wp-simple-form' ), array(
            'type' => 'text',
            'placeholder' => __( 'Enter your name', 'wp-simple-form' ),
            'required' => true,
            ), 'name' );
            
        $form->add_input( __( 'Email', 'wp-simple-form' ), array(
            'type' => 'email',
            'placeholder' => __( 'Enter your email address', 'wp-simple-form' ),
            'required' => true,
            ), 'email' );
            
        $form->add_input( __( 'Website', 'wp-simple-form' ), array(
            'type' => 'url',
            'placeholder' => __( 'Enter your website URL', 'wp-simple-form' ),
            'required' => false,
            ), 'website' );
            
        $form->add_input( __( 'Message', 'wp-simple-form' ), array(
            'type' => 'textarea',
            'placeholder' => __( 'Enter your message', 'wp-simple-form' ),
            'required' => true,
            ), 'message' );
            
        // Shortcodes should not output data directly
        ob_start(); 
        
        // Status message
        $status = filter_input( INPUT_GET, 'status', FILTER_VALIDATE_INT );
        
        if ( $status == 1 ) {
            printf( '<div class="wp-simpleform message success"><p>%s</p></div>', __( 'Submitted successfully!', 'wp-simple-form' ) );
        }
        
        // Build the form
        $form->build_form();
        
        // Return and clean buffer contents
        return ob_get_clean();
        }
        
        public function form_handler() {
            $post = $_POST;
            
            // Verify nonce
            if ( ! isset( $post['wp_nonce'] ) || ! wp_verify_nonce( $post['wp_nonce'], 'submit_wp_simple_form') ) {
                wp_die( __( "Cheatin' uh?", 'wp-simple-form' ) );
            }
            
            // Verify required fields
            $required_fields = array( 'name', 'email', 'message' );
            
            foreach ( $required_fields as $field ) {
                if ( empty( $post[$field] ) ) {
                    wp_die( __( "Name, email and message fields are required.", 'wp-simple-form' ) );
                }
            }
            
            // Build post arguments
            $postarr = array(
                'post_author' => 1,
                'post_title' => sanitize_text_field( $post['name'] ),
                'post_content' => sanitize_textarea_field( $post['message'] ),
                'post_type' => 'wpsf_contact_form',
                'post_status' => 'publish',
                'meta_input' => array(
                    'submission_email' => sanitize_email( $post['email'] ),
                    'submission_website' => sanitize_text_field( $post['website'] ),
                )
            );
            
            // Insert the post
            $postid = wp_insert_post( $postarr, true );

            if ( is_wp_error( $postid ) ) {
                wp_die( __( "There was problem with your submission. Please try again.", 'wp-simple-form' ) );
            }
            
            // Send emails to admins
            $to = array();
            $post_edit_url = sprintf( '%s?post=%s&action=edit', admin_url( 'post.php' ), $postid );
            $admins = get_users( array( 'role' => 'administrator' ) );
            
            foreach ( $admins as $admin ) {
                $to[] = $admin->user_email;
            }
            
            // Build the email
            $subject = __( 'New feedback!', 'wp-simple-form' );
            $message = sprintf( '<p>%s</p>', __( 'Here are the details:', 'wp-simple-form' ) ) ;
            $message .= sprintf( '<p>%s: %s<br>', __( 'Name', 'wp-simple-form' ), sanitize_text_field( $post['name'] ) );
            $message .= sprintf( '<p>%s: %s<p>', __( 'Name', 'wp-simple-form' ), sanitize_textarea_field( $post['message'] ) );
            $message .= sprintf( '<p>%s: <a href="%s">%s</a>', __( 'View/edit the full message here', 'wp-simple-form' ), $post_edit_url, $post_edit_url );
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            // Send the email
            wp_mail( $to, $subject, $message, $headers );
            
            // Redirect back to page
            wp_redirect( add_query_arg( 'status', '1', get_permalink( $post['redirect_id'] ) ) );
        }
    }
}

$wpsimpleform = new WPSimpleForm;