<?php
/**
 * Handles the admin page to list content types and endpoints, and manage plugin settings.
 */
class WP_API_Endpoints_Explorer_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        // ⭐ NEW: Register encrypted API endpoint
        add_action( 'rest_api_init', [ $this, 'register_encrypted_api_route' ] );

        // Export routes – added here
        add_action( 'rest_api_init', [ $this, 'register_export_routes' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * ⭐ NEW: Register encrypted API route
     */
    public function register_encrypted_api_route() {
        register_rest_route( 'endpoints-explorer/v1', '/encrypted/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_encrypted_post' ],
            'permission_callback' => '__return_true', // Allow public OR restrict as needed
        ]);
    }

    public function enqueue_admin_assets( $hook ) {

    // Load ONLY on this plugin page
    if ( $hook !== 'toplevel_page_wp-api-endpoints-explorer' ) {
        return;
    }

    wp_enqueue_style(
        'wp-api-endpoints-explorer-admin',
        plugin_dir_url( __DIR__ ) . 'assets/css/admin.css',
        [],
        '1.0'
    );

    wp_enqueue_script(
        'wp-api-endpoints-explorer-admin',
        plugin_dir_url( __DIR__ ) . 'assets/js/admin.js',
        [],
        '1.0',
        true
    );

    wp_localize_script(
        'wp-api-endpoints-explorer-admin',
        'WPApiExplorer',
        [
            'restBase' => rest_url( 'endpoints-explorer/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
        ]
    );
}

    /**
     * ⭐ NEW: Register export documentation routes (admin only)
     */
    public function register_export_routes() {
        register_rest_route( 'endpoints-explorer/v1', '/openapi', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_openapi_export' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'endpoints-explorer/v1', '/postman', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_postman_export' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * ⭐ NEW: Encrypt content using AES-256-CBC
     */
    private function encrypt_content( $data, $secret_key ) {
        $iv        = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $data, 'aes-256-cbc', $secret_key, 0, $iv );
        return base64_encode( json_encode([
            'iv'        => base64_encode( $iv ),
            'encrypted' => $encrypted
        ]) );
    }

    /**
     * ⭐ NEW: Encrypted post content endpoint callback
     */
    public function get_encrypted_post( WP_REST_Request $request ) {
        $post_id = $request['id'];
        $post    = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Post not found', [ 'status' => 404 ] );
        }
        $content   = apply_filters( 'the_content', $post->post_content );
        $secret    = AUTH_KEY;
        $encrypted = $this->encrypt_content( $content, $secret );
        return [
            'id'                => $post_id,
            'encrypted_content' => $encrypted
        ];
    }

    /**
     * Registers plugin settings.
     */
    public function register_settings() {
        register_setting( 'wp-api-explorer-settings-group', 'explorer_settings' );

        add_settings_section(
            'wp-api-explorer-main-section',
            __( 'Endpoint Settings', 'wp-api-endpoints-explorer' ),
            null,
            'wp-api-endpoints-explorer'
        );

        add_settings_field(
            'enable_acf_in_api',
            __( 'Enable ACF in REST API', 'wp-api-endpoints-explorer' ),
            [ $this, 'render_enable_acf_checkbox' ],
            'wp-api-endpoints-explorer',
            'wp-api-explorer-main-section'
        );

        add_settings_field(
            'enable_posts_endpoints',
            __( 'Enable Posts Endpoints', 'wp-api-endpoints-explorer' ),
            [ $this, 'render_posts_endpoints_checkbox' ],
            'wp-api-endpoints-explorer',
            'wp-api-explorer-main-section'
        );

        add_settings_field(
            'enable_pages_endpoints',
            __( 'Enable Pages Endpoints', 'wp-api-endpoints-explorer' ),
            [ $this, 'render_pages_endpoints_checkbox' ],
            'wp-api-endpoints-explorer',
            'wp-api-explorer-main-section'
        );

        add_settings_field(
            'enable_custom_post_types_endpoints',
            __( 'Enable Custom Post Types Endpoints', 'wp-api-endpoints-explorer' ),
            [ $this, 'render_custom_post_types_endpoints_checkbox' ],
            'wp-api-endpoints-explorer',
            'wp-api-explorer-main-section'
        );

        add_settings_field(
            'enable_public_api_visibility',
            __( 'Enable Public API Visibility', 'wp-api-endpoints-explorer' ),
            [ $this, 'render_public_api_visibility_checkbox' ],
            'wp-api-explorer-main-section'
        );

        add_settings_field(
            'enable_woocommerce_overview',
            __( 'Enable WooCommerce Endpoints Overview', 'wp-api-endpoints-explorer' ),
            [ $this, 'render_enable_woocommerce_overview_checkbox' ],
            'wp-api-endpoints-explorer',
            'wp-api-explorer-main-section'
        );
    }

    // Renderer methods remain unchanged
    public function render_enable_woocommerce_overview_checkbox() {
        $options = get_option( 'explorer_settings', [] );
        $checked = ! empty( $options['enable_woocommerce_overview'] ) ? 'checked' : '';
        echo '<input type="checkbox" name="explorer_settings[enable_woocommerce_overview]" value="1" ' . $checked . ' />';
        echo '<p class="description">' . esc_html__( 'Display WooCommerce-related endpoints (Products, Coupons, Orders) in the API Explorer overview. Only visible if WooCommerce is installed and active.', 'wp-api-endpoints-explorer' ) . '</p>';
    }

    public function render_public_api_visibility_checkbox() {
        $options    = get_option( 'explorer_settings' );
        $is_enabled = isset( $options['enable_public_api_visibility'] ) ? 'checked' : '';
        if ( ! $is_enabled ) $is_enabled = 'checked';
        echo '<input type="checkbox" name="explorer_settings[enable_public_api_visibility]" value="1" ' . $is_enabled . ' />';
        echo '<p class="description">' . esc_html__( '🔐 This endpoint is visible in the API Explorer. However, access requires a valid JWT token. The /wp-json root and its endpoints remain hidden from unauthorized users.' ) . '</p>';
    }

    public function render_posts_endpoints_checkbox() {
        $options    = get_option( 'explorer_settings' );
        $is_enabled = isset( $options['enable_posts_endpoints'] ) ? 'checked' : '';
        echo '<input type="checkbox" name="explorer_settings[enable_posts_endpoints]" value="1" ' . $is_enabled . ' />';
        echo '<p class="description">' . esc_html__( 'Enable or disable all Posts endpoints in the REST API. When disabled, these endpoints will return a 403 error even with a valid token.', 'wp-api-endpoints-explorer' ) . '</p>';
    }

    public function render_pages_endpoints_checkbox() {
        $options    = get_option( 'explorer_settings' );
        $is_enabled = isset( $options['enable_pages_endpoints'] ) ? 'checked' : '';
        echo '<input type="checkbox" name="explorer_settings[enable_pages_endpoints]" value="1" ' . $is_enabled . ' />';
        echo '<p class="description">' . esc_html__( 'Enable or disable all Pages endpoints in the REST API. When disabled, these endpoints will return a 403 error even with a valid token.', 'wp-api-endpoints-explorer' ) . '</p>';
    }

    public function render_custom_post_types_endpoints_checkbox() {
        $options    = get_option( 'explorer_settings' );
        $is_enabled = isset( $options['enable_custom_post_types_endpoints'] ) ? 'checked' : '';
        echo '<input type="checkbox" name="explorer_settings[enable_custom_post_types_endpoints]" value="1" ' . $is_enabled . ' />';
        echo '<p class="description">' . esc_html__( 'Enable or disable all Custom Post Type endpoints in the REST API. When disabled, these endpoints will return a 403 error even with a valid token.', 'wp-api-endpoints-explorer' ) . '</p>';
    }

    public function render_enable_acf_checkbox() {
        $options    = get_option( 'explorer_settings' );
        $is_enabled = isset( $options['enable_acf'] ) ? 'checked' : '';
        echo '<input type="checkbox" name="explorer_settings[enable_acf]" value="1" ' . $is_enabled . ' />';
        echo '<p class="description">' . esc_html__( 'Exposes ACF fields for all public post types (pages, posts, etc.) to the REST API.', 'wp-api-endpoints-explorer' ) . '</p>';
    }

    public function add_admin_menu_page() {
        add_menu_page(
            __( 'REST API Explorer', 'wp-api-endpoints-explorer' ),
            __( 'API Explorer', 'wp-api-endpoints-explorer' ),
            'manage_options',
            'wp-api-endpoints-explorer',
            [ $this, 'render_admin_page' ],
            'dashicons-rest-api'
        );
    }

    // ───────────────────────────────────────────────
    // NEW HELPER: Render nice endpoint line with badge + copy button
    // ───────────────────────────────────────────────
    private function render_endpoint_line( $method, $url, $label = '' ) {
        $method = strtoupper( $method );
        $colors = [
            'GET'    => '#2e7d32',
            'POST'   => '#1976d2',
            'PUT'    => '#f57c00',
            'PATCH'  => '#f57c00',
            'DELETE' => '#d32f2f',
        ];
        $color = $colors[ $method ] ?? '#555';

        $escaped_url = esc_url_raw( $url );

        ?>
        <div style="display:flex; align-items:center; gap:12px; margin:0.7em 0; flex-wrap:wrap;">
            <span style="
                background:<?php echo $color; ?>;
                color:white;
                font-weight:bold;
                padding:5px 11px;
                border-radius:5px;
                font-size:0.92em;
                min-width:60px;
                text-align:center;
                text-transform:uppercase;
            ">
                <?php echo $method; ?>
            </span>
            <?php if ( $label ): ?>
                <strong style="min-width:90px; color:#333;"><?php echo esc_html( $label ); ?>:</strong>
            <?php endif; ?>
            <code style="font-size:1.03em; background:#f5f5f5; padding:6px 10px; border-radius:4px; word-break:break-all; flex:1; font-family:monospace;">
                <?php echo esc_html( $url ); ?>
            </code>
            <button type="button" class="button button-small copy-endpoint-btn"
                    data-clipboard-text="<?php echo $escaped_url; ?>"
                    title="<?php esc_attr_e( 'Copy URL', 'wp-api-endpoints-explorer' ); ?>">
                Copy
            </button>
        </div>
        <?php
    }

    // ───────────────────────────────────────────────
    // NEW: Collect active endpoint groups based on settings
    // ───────────────────────────────────────────────
    private function get_active_endpoint_groups() {
        $options = get_option( 'explorer_settings', [] );
        $groups  = [];

        $public_visible = ! empty( $options['enable_public_api_visibility'] );

        if ( $public_visible ) {
            $groups['auth'] = [
                'title'     => 'Authentication',
                'endpoints' => [
                    [ 'method' => 'POST', 'path' => '/endpoints-explorer/v1/token',   'desc' => 'Obtain JWT access token' ],
                    [ 'method' => 'POST', 'path' => '/endpoints-explorer/v1/refresh', 'desc' => 'Refresh JWT access token' ],
                ]
            ];

            $groups['encrypted'] = [
                'title'     => 'Encrypted Content',
                'endpoints' => [
                    [ 'method' => 'GET', 'path' => '/endpoints-explorer/v1/encrypted/{id}', 'desc' => 'Get AES-256 encrypted post content' ],
                ]
            ];
        }

        if ( ! empty( $options['enable_posts_endpoints'] ) ) {
            $groups['posts'] = [
                'title'     => 'Posts',
                'endpoints' => [
                    [ 'method' => 'GET',    'path' => '/wp/v2/posts',        'desc' => 'List posts' ],
                    [ 'method' => 'GET',    'path' => '/wp/v2/posts/{id}',   'desc' => 'Retrieve a post' ],
                    [ 'method' => 'POST',   'path' => '/wp/v2/posts',        'desc' => 'Create a post' ],
                    [ 'method' => 'PUT',    'path' => '/wp/v2/posts/{id}',   'desc' => 'Update a post' ],
                    [ 'method' => 'PATCH',  'path' => '/wp/v2/posts/{id}',   'desc' => 'Partial update a post' ],
                    [ 'method' => 'DELETE', 'path' => '/wp/v2/posts/{id}',   'desc' => 'Delete a post' ],
                ]
            ];
        }

        if ( ! empty( $options['enable_pages_endpoints'] ) ) {
            $groups['pages'] = [
                'title'     => 'Pages',
                'endpoints' => [
                    [ 'method' => 'GET',    'path' => '/wp/v2/pages',        'desc' => 'List pages' ],
                    [ 'method' => 'GET',    'path' => '/wp/v2/pages/{id}',   'desc' => 'Retrieve a page' ],
                    [ 'method' => 'POST',   'path' => '/wp/v2/pages',        'desc' => 'Create a page' ],
                    [ 'method' => 'PUT',    'path' => '/wp/v2/pages/{id}',   'desc' => 'Update a page' ],
                    [ 'method' => 'DELETE', 'path' => '/wp/v2/pages/{id}',   'desc' => 'Delete a page' ],
                ]
            ];
        }

        if ( ! empty( $options['enable_custom_post_types_endpoints'] ) ) {
            $post_types = get_post_types( [ '_builtin' => false, 'public' => true, 'show_in_rest' => true ], 'objects' );
            unset( $post_types['product'], $post_types['shop_coupon'] );

            foreach ( $post_types as $slug => $obj ) {
                $groups["cpt_{$slug}"] = [
                    'title'     => $obj->label,
                    'endpoints' => [
                        [ 'method' => 'GET',    'path' => "/wp/v2/{$slug}",       'desc' => "List {$obj->label}" ],
                        [ 'method' => 'GET',    'path' => "/wp/v2/{$slug}/{id}",  'desc' => "Retrieve a {$obj->label}" ],
                        [ 'method' => 'POST',   'path' => "/wp/v2/{$slug}",       'desc' => "Create a {$obj->label}" ],
                        [ 'method' => 'PUT',    'path' => "/wp/v2/{$slug}/{id}",  'desc' => "Update a {$obj->label}" ],
                        [ 'method' => 'DELETE', 'path' => "/wp/v2/{$slug}/{id}",  'desc' => "Delete a {$obj->label}" ],
                    ]
                ];
            }
        }

        if ( ! empty( $options['enable_woocommerce_overview'] ) && class_exists( 'WooCommerce' ) ) {
            $groups['woocommerce'] = [
                'title'     => 'WooCommerce',
                'endpoints' => [
                    [ 'method' => 'GET',    'path' => '/wc/v3/products',        'desc' => 'List products' ],
                    [ 'method' => 'POST',   'path' => '/wc/v3/products',        'desc' => 'Create product' ],
                    [ 'method' => 'GET',    'path' => '/wc/v3/products/{id}',   'desc' => 'Retrieve product' ],
                    [ 'method' => 'PUT',    'path' => '/wc/v3/products/{id}',   'desc' => 'Update product' ],
                    [ 'method' => 'DELETE', 'path' => '/wc/v3/products/{id}',   'desc' => 'Delete product' ],

                    [ 'method' => 'GET',    'path' => '/wc/v3/coupons',         'desc' => 'List coupons' ],
                    [ 'method' => 'POST',   'path' => '/wc/v3/coupons',         'desc' => 'Create coupon' ],
                    [ 'method' => 'GET',    'path' => '/wc/v3/coupons/{id}',    'desc' => 'Retrieve coupon' ],
                    [ 'method' => 'PUT',    'path' => '/wc/v3/coupons/{id}',    'desc' => 'Update coupon' ],
                    [ 'method' => 'DELETE', 'path' => '/wc/v3/coupons/{id}',    'desc' => 'Delete coupon' ],

                    [ 'method' => 'GET',    'path' => '/wc/v3/orders',          'desc' => 'List orders' ],
                    [ 'method' => 'POST',   'path' => '/wc/v3/orders',          'desc' => 'Create order' ],
                    [ 'method' => 'GET',    'path' => '/wc/v3/orders/{id}',     'desc' => 'Retrieve order' ],
                    [ 'method' => 'PUT',    'path' => '/wc/v3/orders/{id}',     'desc' => 'Update order' ],
                    [ 'method' => 'DELETE', 'path' => '/wc/v3/orders/{id}',     'desc' => 'Delete order' ],
                ]
            ];
        }

        return $groups;
    }

    // ───────────────────────────────────────────────
    // NEW: OpenAPI 3.0 export
    // ───────────────────────────────────────────────
    public function handle_openapi_export( $request ) {
        $base     = rest_url( '' );
        $groups   = $this->get_active_endpoint_groups();

        $openapi = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => 'WordPress REST API – ' . get_bloginfo( 'name' ),
                'description' => 'Generated from WP API Endpoints Explorer plugin',
                'version'     => '1.0.0',
            ],
            'servers' => [
                [ 'url' => rtrim( $base, '/' ) ]
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'         => 'http',
                        'scheme'       => 'bearer',
                        'bearerFormat' => 'JWT',
                    ]
                ]
            ],
            'security' => [
                [ 'bearerAuth' => [] ]
            ],
            'paths' => [],
        ];

        foreach ( $groups as $group ) {
            foreach ( $group['endpoints'] as $ep ) {
                $path   = $ep['path'];
                $method = strtolower( $ep['method'] );

                if ( ! isset( $openapi['paths'][$path] ) ) {
                    $openapi['paths'][$path] = [];
                }

                $op = [
                    'summary'     => $ep['desc'],
                    'tags'        => [ $group['title'] ],
                    'responses'   => [
                        '200' => [ 'description' => 'OK' ],
                        '401' => [ 'description' => 'Unauthorized' ],
                        '403' => [ 'description' => 'Forbidden' ],
                        '404' => [ 'description' => 'Not Found' ],
                    ]
                ];

                if ( strpos( $path, '{id}' ) !== false ) {
                    $op['parameters'] = [
                        [
                            'name'        => 'id',
                            'in'          => 'path',
                            'required'    => true,
                            'schema'      => [ 'type' => 'integer' ],
                            'description' => 'Resource ID'
                        ]
                    ];
                }

                $openapi['paths'][$path][$method] = $op;
            }
        }

        return rest_ensure_response( $openapi );
    }

    // ───────────────────────────────────────────────
    // NEW: Postman Collection v2.1 export
    // ───────────────────────────────────────────────
    public function handle_postman_export( $request ) {
        $groups   = $this->get_active_endpoint_groups();
        $base_url = rest_url( '' );

        $collection = [
            'info' => [
                '_postman_id' => wp_generate_uuid4(),
                'name'        => 'WP REST API – ' . get_bloginfo( 'name' ),
                'description' => 'Exported from WP API Endpoints Explorer',
                'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'item'     => [],
            'variable' => [
                [
                    'key'   => 'base_url',
                    'value' => rtrim( $base_url, '/' ),
                    'type'  => 'string'
                ],
                [
                    'key'         => 'jwt_token',
                    'value'       => '',
                    'type'        => 'string',
                    'description' => 'Your JWT Bearer token'
                ]
            ]
        ];

        foreach ( $groups as $key => $group ) {
            $folder = [
                'name' => $group['title'],
                'item' => []
            ];

            foreach ( $group['endpoints'] as $ep ) {
                $folder['item'][] = [
                    'name'    => $ep['desc'],
                    'request' => [
                        'method'  => $ep['method'],
                        'header'  => [
                            [
                                'key'   => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type'  => 'text'
                            ]
                        ],
                        'url'     => [
                            'raw'  => '{{base_url}}' . $ep['path'],
                            'host' => [ '{{base_url}}' ],
                            'path' => explode( '/', ltrim( $ep['path'], '/' ) )
                        ]
                    ],
                    'response' => []
                ];
            }

            $collection['item'][] = $folder;
        }

        return rest_ensure_response( $collection );
    }

    /**
     * Renders the HTML for the admin page, displaying content types.
     */
    public function render_admin_page() {
        $options = get_option( 'explorer_settings', [] );

        $enable_public_api_visibility = isset( $options['enable_public_api_visibility'] )
            ? (bool) $options['enable_public_api_visibility']
            : true;

        $enable_posts = ! empty( $options['enable_posts_endpoints'] );
        $enable_pages = ! empty( $options['enable_pages_endpoints'] );
        $enable_cpt   = ! empty( $options['enable_custom_post_types_endpoints'] );
        $enable_woo   = ! empty( $options['enable_woocommerce_overview'] ) && class_exists( 'WooCommerce' );
        ?>
        <div class="wrap">

            <h1 class="wp-heading-inline">
                <?php _e( 'REST API Explorer', 'wp-api-endpoints-explorer' ); ?>
            </h1>

            <p class="description" style="font-size:1.1em; max-width:800px; margin:1em 0 2em;">
                <?php _e( 'Quickly view and manage which REST API endpoints are active on your site — no coding required.', 'wp-api-endpoints-explorer' ); ?>
            </p>

            <div class="card" style="max-width:100%; width:100%; margin-bottom:2.5em; box-sizing:border-box;">
                <h2 class="title"><?php _e( 'Plugin Settings', 'wp-api-endpoints-explorer' ); ?></h2>
                <div class="inside">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields( 'wp-api-explorer-settings-group' );
                        do_settings_sections( 'wp-api-endpoints-explorer' );
                        submit_button( __( 'Save Changes', 'wp-api-endpoints-explorer' ), 'primary', 'submit', true, [ 'style' => 'margin-top:1.5em;' ] );
                        ?>
                    </form>
                </div>
            </div>

            <?php if ( $enable_public_api_visibility ) : ?>

                <div class="card" style="max-width:100%; width:100%; box-sizing:border-box;">
                    <h2 class="title">
                        <span class="dashicons dashicons-admin-links" style="vertical-align:middle; margin-right:8px;"></span>
                        <?php _e( 'API Endpoints Overview', 'wp-api-endpoints-explorer' ); ?>
                    </h2>

                    <div class="inside">

                        <div class="notice notice-warning inline" style="margin:0 0 1.5em;">
                            <p>
                                <strong>⚠️ <?php _e( 'Important:', 'wp-api-endpoints-explorer' ); ?></strong><br>
                                <?php _e( 'Even when marked as "Public", all endpoints still require a valid JWT token for access. The /wp-json root remains protected.', 'wp-api-endpoints-explorer' ); ?>
                            </p>
                        </div>

                        <details style="margin-bottom:1.4em;">
                            <summary style="font-weight:bold; cursor:pointer; font-size:1.05em;">
                                <?php _e( 'Authentication Endpoint', 'wp-api-endpoints-explorer' ); ?>
                            </summary>
                            <div style="margin-top:1em; padding-left:0.6em;">
                                <?php $this->render_endpoint_line( 'POST', rest_url( 'endpoints-explorer/v1/token' ), 'Get token' ); ?>
                                <p style="margin:1.4em 0 0.6em;"><strong><?php _e( 'Example body (JSON):', 'wp-api-endpoints-explorer' ); ?></strong></p>
                                <pre style="background:#f6f7f7; padding:12px; border-radius:4px; max-width:640px; overflow-x:auto;"><?php echo esc_html('{
    "username": "your_username",
    "password": "your_password"
}'); ?></pre>
                            </div>
                        </details>

                        <details style="margin-bottom:1.4em;">
                            <summary style="font-weight:bold; cursor:pointer; font-size:1.05em;">
                                <?php _e( 'Refresh Token Endpoint', 'wp-api-endpoints-explorer' ); ?>
                            </summary>
                            <div style="margin-top:1em; padding-left:0.6em;">
                                <?php $this->render_endpoint_line( 'POST', rest_url( 'endpoints-explorer/v1/refresh' ), 'Refresh token' ); ?>
                                <p style="margin:1.2em 0 0.4em;"><strong><?php _e( 'Example body (JSON):', 'wp-api-endpoints-explorer' ); ?></strong></p>
                                <pre style="background:#f6f7f7; padding:12px; border-radius:4px; max-width:640px; overflow-x:auto;"><?php echo esc_html('{
    "refresh_token": "YOUR_REFRESH_TOKEN"
}'); ?></pre>
                                <p style="color:#555; font-size:0.95em; margin-top:1em;">
                                    ⚠️ <?php _e( 'This endpoint is intended for frontend or mobile applications.<br>Do not send username and password again.', 'wp-api-endpoints-explorer' ); ?>
                                </p>
                            </div>
                        </details>

                        <?php if ( $enable_posts ) : ?>
                            <hr style="margin:2.2em 0 1.6em;">
                            <details open>
                                <summary style="font-weight:bold; cursor:pointer; font-size:1.15em;">
                                    <?php _e( 'Posts', 'wp-api-endpoints-explorer' ); ?>
                                </summary>
                                <div style="margin-top:1.2em; padding-left:0.6em;">
                                    <?php
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wp/v2/posts' ),       'List all' );
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wp/v2/posts/{id}' ),   'Single' );
                                    $this->render_endpoint_line( 'POST',   rest_url( 'wp/v2/posts' ),       'Create' );
                                    $this->render_endpoint_line( 'PUT',    rest_url( 'wp/v2/posts/{id}' ),   'Update' );
                                    $this->render_endpoint_line( 'PATCH',  rest_url( 'wp/v2/posts/{id}' ),   'Partial' );
                                    $this->render_endpoint_line( 'DELETE', rest_url( 'wp/v2/posts/{id}' ),   'Delete' );
                                    ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <?php if ( $enable_pages ) : ?>
                            <hr style="margin:2.2em 0 1.6em;">
                            <details open>
                                <summary style="font-weight:bold; cursor:pointer; font-size:1.15em;">
                                    <?php _e( 'Pages', 'wp-api-endpoints-explorer' ); ?>
                                </summary>
                                <div style="margin-top:1.2em; padding-left:0.6em;">
                                    <?php
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wp/v2/pages' ),       'List all' );
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wp/v2/pages/{id}' ),   'Single' );
                                    $this->render_endpoint_line( 'POST',   rest_url( 'wp/v2/pages' ),       'Create' );
                                    $this->render_endpoint_line( 'PUT',    rest_url( 'wp/v2/pages/{id}' ),   'Update' );
                                    $this->render_endpoint_line( 'DELETE', rest_url( 'wp/v2/pages/{id}' ),   'Delete' );
                                    ?>

                                    <?php
                                    $pages = get_posts([
                                        'post_type'      => 'page',
                                        'post_status'    => 'publish',
                                        'posts_per_page' => -1,
                                        'orderby'        => 'title',
                                        'order'          => 'ASC'
                                    ]);
                                    if ( ! empty( $pages ) ) : ?>
                                        <h4 style="margin:1.8em 0 0.9em;"><?php _e( 'Individual Published Pages', 'wp-api-endpoints-explorer' ); ?></h4>
                                        <ul style="margin:0; padding-left:0; list-style:none;">
                                            <?php foreach ( $pages as $page ) : ?>
                                                <li style="margin:0.8em 0; padding:0.6em; background:#fafafa; border-radius:4px;">
                                                    <strong><?php echo esc_html( $page->post_title ); ?></strong>
                                                    <span style="color:#666; font-size:0.92em;"> (ID: <?php echo $page->ID; ?>)</span>
                                                    <div style="margin-top:0.5em;">
                                                        <?php
                                                        $this->render_endpoint_line( 'GET',    rest_url( "wp/v2/pages/{$page->ID}" ), 'View' );
                                                        $this->render_endpoint_line( 'PUT',    rest_url( "wp/v2/pages/{$page->ID}" ), 'Update' );
                                                        $this->render_endpoint_line( 'DELETE', rest_url( "wp/v2/pages/{$page->ID}" ), 'Delete' );
                                                        ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <p style="color:#777; font-style:italic; margin-top:1em;">
                                            <?php _e( 'No published pages found.', 'wp-api-endpoints-explorer' ); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <?php if ( $enable_cpt ) : ?>
                            <hr style="margin:2.2em 0 1.6em;">
                            <details open>
                                <summary style="font-weight:bold; cursor:pointer; font-size:1.15em;">
                                    <?php _e( 'Custom Post Types', 'wp-api-endpoints-explorer' ); ?>
                                </summary>
                                <div style="margin-top:1.2em; padding-left:0.6em;">
                                    <?php
                                    $post_types = get_post_types(
                                        [ '_builtin' => false, 'public' => true, 'show_in_rest' => true ],
                                        'objects'
                                    );
                                    unset( $post_types['product'], $post_types['shop_coupon'] );

                                    if ( ! empty( $post_types ) ) :
                                        foreach ( $post_types as $slug => $object ) : ?>
                                            <div style="margin:1.4em 0; padding:1em; background:#f9f9f9; border-radius:6px;">
                                                <strong><?php echo esc_html( $object->label ); ?></strong>
                                                <code style="font-size:0.96em; color:#555;">/wp/v2/<?php echo esc_html( $slug ); ?></code>
                                                <div style="margin-top:0.9em;">
                                                    <?php
                                                    $this->render_endpoint_line( 'GET',    rest_url( "wp/v2/{$slug}" ),       'List' );
                                                    $this->render_endpoint_line( 'GET',    rest_url( "wp/v2/{$slug}/{id}" ),   'Single' );
                                                    $this->render_endpoint_line( 'POST',   rest_url( "wp/v2/{$slug}" ),       'Create' );
                                                    $this->render_endpoint_line( 'PUT',    rest_url( "wp/v2/{$slug}/{id}" ),   'Update' );
                                                    $this->render_endpoint_line( 'DELETE', rest_url( "wp/v2/{$slug}/{id}" ),   'Delete' );
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else : ?>
                                        <p style="color:#777; font-style:italic;">
                                            <?php _e( 'No public custom post types with REST support found.', 'wp-api-endpoints-explorer' ); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <?php if ( $enable_woo ) : ?>
                            <hr style="margin:2.2em 0 1.6em;">
                            <details open>
                                <summary style="font-weight:bold; cursor:pointer; font-size:1.15em;">
                                    <?php _e( 'WooCommerce', 'woocommerce' ); ?>
                                </summary>
                                <div style="margin-top:1.2em; padding-left:0.6em;">

                                    <h4 style="margin:1.2em 0 0.7em;"><?php _e( 'Products', 'woocommerce' ); ?></h4>
                                    <?php
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wc/v3/products' ),       'List all' );
                                    $this->render_endpoint_line( 'POST',   rest_url( 'wc/v3/products' ),       'Create' );
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wc/v3/products/{id}' ),   'Single' );
                                    $this->render_endpoint_line( 'PUT',    rest_url( 'wc/v3/products/{id}' ),   'Update' );
                                    $this->render_endpoint_line( 'DELETE', rest_url( 'wc/v3/products/{id}' ),   'Delete' );
                                    ?>

                                    <hr style="margin:2em 0 1.2em;">

                                    <h4 style="margin:1.2em 0 0.7em;"><?php _e( 'Coupons', 'woocommerce' ); ?></h4>
                                    <?php
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wc/v3/coupons' ),       'List all' );
                                    $this->render_endpoint_line( 'POST',   rest_url( 'wc/v3/coupons' ),       'Create' );
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wc/v3/coupons/{id}' ),   'Single' );
                                    $this->render_endpoint_line( 'PUT',    rest_url( 'wc/v3/coupons/{id}' ),   'Update' );
                                    $this->render_endpoint_line( 'DELETE', rest_url( 'wc/v3/coupons/{id}' ),   'Delete' );
                                    ?>

                                    <hr style="margin:2em 0 1.2em;">

                                    <h4 style="margin:1.2em 0 0.7em;"><?php _e( 'Orders', 'woocommerce' ); ?></h4>
                                    <?php
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wc/v3/orders' ),       'List all' );
                                    $this->render_endpoint_line( 'POST',   rest_url( 'wc/v3/orders' ),       'Create' );
                                    $this->render_endpoint_line( 'GET',    rest_url( 'wc/v3/orders/{id}' ),   'Single' );
                                    $this->render_endpoint_line( 'PUT',    rest_url( 'wc/v3/orders/{id}' ),   'Update' );
                                    $this->render_endpoint_line( 'DELETE', rest_url( 'wc/v3/orders/{id}' ),   'Delete' );
                                    ?>

                                </div>
                            </details>
                        <?php endif; ?>

                        <!-- ─────────────────────────────────────────────── -->
                        <!-- NEW: Export API Documentation Section -->
                        <!-- ─────────────────────────────────────────────── -->
                        <hr style="margin:2.8em 0 1.8em; border-color:#ddd;">
                        <h3 style="margin:0 0 1em;"><?php _e( 'Export API Documentation', 'wp-api-endpoints-explorer' ); ?></h3>
                        <p style="color:#555; margin-bottom:1.4em; max-width:680px;">
                            <?php _e( 'Download OpenAPI (Swagger) JSON or Postman Collection. Only visible to users with the manage_options capability.', 'wp-api-endpoints-explorer' ); ?>
                        </p>

                        <div style="display:flex; gap:16px; flex-wrap:wrap;">
                            <button type="button" class="button button-primary export-docs-btn"
                                    data-format="openapi"
                                    data-filename="openapi-<?php echo esc_attr( sanitize_title( get_bloginfo('name') ) ); ?>.json">
                                <?php _e( 'Export OpenAPI (Swagger) JSON', 'wp-api-endpoints-explorer' ); ?>
                            </button>

                            <button type="button" class="button button-primary export-docs-btn"
                                    data-format="postman"
                                    data-filename="postman-<?php echo esc_attr( sanitize_title( get_bloginfo('name') ) ); ?>.json">
                                <?php _e( 'Export Postman Collection', 'wp-api-endpoints-explorer' ); ?>
                            </button>
                        </div>

                    </div>
                </div>

            <?php endif; ?>

        </div>

        <?php
    }
}