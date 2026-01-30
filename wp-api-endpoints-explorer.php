<?php
/**
 * Plugin Name:       WP API Endpoints Explorer
 * Plugin URI:        https://github.com/Pushpa2611
 * Description:       Provides secure JWT authentication for the WordPress REST API and a powerful admin dashboard to explore, manage, and export API endpoints.
 * Version:           1.0
 * Author:            Pushpasharmila S
 * Author URI:        https://github.com/Pushpa2611
 * License:           GPLv2 or later
 * Text Domain:       wp-api-endpoints-explorer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ======================================================
 * 🔐 Activation Check – JWT Secret Key
 * ====================================================== */
function wp_api_endpoints_explorer_activation_check() {

    if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) ) {

        deactivate_plugins( plugin_basename( __FILE__ ) );

        wp_die(
            '<h1>Plugin Activation Failed</h1>
            <p><strong>WP API Endpoints Explorer</strong> requires a JWT secret key.</p>
            <p>Please add the following line to your <code>wp-config.php</code> file:</p>
            <pre style="background:#f6f7f7; padding:12px; border-radius:4px;">
define(\'JWT_AUTH_SECRET_KEY\', AUTH_KEY);
            </pre>
            <p>After adding this, activate the plugin again.</p>',
            'Missing JWT Secret Key',
            [ 'back_link' => true ]
        );
    }
}
register_activation_hook( __FILE__, 'wp_api_endpoints_explorer_activation_check' );

/* ======================================================
 * 📦 Composer / JWT Library Check
 * ====================================================== */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    if ( ! class_exists( 'Firebase\\JWT\\JWT' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error">
                <p><strong>WP API Endpoints Explorer:</strong> firebase/php-jwt library is missing.</p>
            </div>';
        });
        return;
    }
}

/* ======================================================
 * 📂 Include Core Classes
 * ====================================================== */
require_once __DIR__ . '/includes/class-wp-api-endpoints-explorer-jwt.php';
require_once __DIR__ . '/includes/class-wp-api-endpoints-explorer-admin.php';

/* ======================================================
 * 🧩 ACF → REST API Integration (UNCHANGED)
 * ====================================================== */
function explorer_register_acf_fields_to_rest_api() {

    $options = get_option( 'explorer_settings' );
    $is_acf_enabled = isset( $options['enable_acf'] ) && $options['enable_acf'];

    if ( $is_acf_enabled && function_exists( 'get_fields' ) ) {

        $post_types = get_post_types(
            [ 'show_in_rest' => true, 'public' => true ],
            'objects'
        );

        foreach ( $post_types as $post_type ) {
            register_rest_field(
                $post_type->name,
                'acf',
                [
                    'get_callback' => 'explorer_acf_get_callback',
                    'update_callback' => null,
                    'schema' => null,
                ]
            );
        }
    }
}
add_action( 'rest_api_init', 'explorer_register_acf_fields_to_rest_api', 20 );

function explorer_acf_get_callback( $object ) {
    $acf_data = get_fields( $object['id'] );
    return ! empty( $acf_data ) ? $acf_data : [];
}

/* ======================================================
 * 🚀 Initialize Plugin
 * ====================================================== */
new WP_API_Endpoints_Explorer_JWT();
new WP_API_Endpoints_Explorer_Admin();
