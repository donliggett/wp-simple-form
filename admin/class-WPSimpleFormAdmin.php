<?php
if ( ! class_exists( 'WPSimpleFormAdmin' ) ) {
    class WPSimpleFormAdmin {
        public function __construct() {
            add_action( 'init', array( $this, 'create_post_type' ) );
            add_filter( 'manage_wpsf_contact_form_posts_columns', array( $this, 'columns_head' ) );
            add_action( 'manage_wpsf_contact_form_posts_custom_column', array( $this, 'columns_content' ), 10, 2 );
        }
        
        public function create_post_type() {
            register_post_type( 'wpsf_contact_form',
                array(
                    'labels' => array(
                        'name' => __( 'Feedback' ),
                        'singular_name' => __( 'Feedback' ),
                    ),
                    'public' => false,
                    'has_archive' => false,
                    'show_ui' => true,
                    'show_in_nav_menus' => false,
                    'menu_position' => 25,
                    'menu_icon' => 'dashicons-testimonial',
                    'supports' => array( 'title', 'editor', 'custom-fields' ),
                )
            );
        }
        
        public function columns_head( $defaults ) {
            unset( $defaults['date'] );
            $defaults['submission_email'] = __( 'Email', 'wp-simple-form' );
            $defaults['submission_website'] = __( 'Website', 'wp-simple-form' );
            $defaults['submission_excerpt'] = __( 'Excerpt', 'wp-simple-form' );
            $defaults['date'] = 'Date';
            
            return $defaults;
        }
        
        public function columns_content( $column_name, $postid ) {
            if ( $column_name == 'submission_email' ) {
                if ( ! empty( $submission_email = get_post_meta( $postid, 'submission_email', true ) ) ) {
                    printf( '<a href="mailto:%s">%s</a>', $submission_email, $submission_email );
                }
            }
            
            if ( $column_name == 'submission_website' ) {
                if ( ! empty( $submission_website = get_post_meta( $postid, 'submission_website', true ) ) ) {
                    printf( '<a href="%s">%s</a>', $submission_website, $submission_website );
                }
            }
            
            if ( $column_name == 'submission_excerpt' ) {
                echo get_the_excerpt( $postid );
            }
        }
    }
}

$wpsimpleform_admin = new WPSimpleFormAdmin;