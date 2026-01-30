<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class WP_API_Endpoints_Explorer_JWT {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_jwt_auth_routes' ] );
        add_filter( 'rest_authentication_errors', [ $this, 'authenticate_jwt_token' ] );
    }

    /**
     * Register JWT endpoints
     */
    public function register_jwt_auth_routes() {

        // 🔑 Login → Access + Refresh token
        register_rest_route( 'endpoints-explorer/v1', '/token', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'get_token' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => [ 'required' => true ],
                'password' => [ 'required' => true ],
            ],
        ]);

        // 🔄 Refresh → New access token
        register_rest_route( 'endpoints-explorer/v1', '/refresh', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'refresh_token' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'refresh_token' => [ 'required' => true ],
            ],
        ]);
    }

    /**
     * Generate access + refresh tokens
     */
    public function get_token( WP_REST_Request $request ) {

        $user = wp_authenticate(
            $request->get_param( 'username' ),
            $request->get_param( 'password' )
        );

        if ( is_wp_error( $user ) ) {
            return new WP_Error(
                'invalid_credentials',
                'Invalid username or password',
                [ 'status' => 403 ]
            );
        }

        $now = time();

        // 🔐 Access token (15 mins)
        $access_payload = [
            'iss'  => get_bloginfo( 'url' ),
            'iat'  => $now,
            'exp'  => $now + ( 15 * MINUTE_IN_SECONDS ),
            'type' => 'access',
            'data' => [
                'user' => [
                    'id' => $user->ID,
                ],
            ],
        ];

        // 🔄 Refresh token (7 days)
        $refresh_payload = [
            'iss'  => get_bloginfo( 'url' ),
            'iat'  => $now,
            'exp'  => $now + ( 7 * DAY_IN_SECONDS ),
            'type' => 'refresh',
            'data' => [
                'user' => [
                    'id' => $user->ID,
                ],
            ],
        ];

        try {
            return new WP_REST_Response( [
                'access_token'  => JWT::encode( $access_payload, JWT_AUTH_SECRET_KEY, 'HS256' ),
                'refresh_token' => JWT::encode( $refresh_payload, JWT_AUTH_SECRET_KEY, 'HS256' ),
                'expires_in'    => 900, // 15 mins
                'user'          => $user->display_name,
            ], 200 );
        } catch ( Exception $e ) {
            return new WP_Error(
                'token_generation_failed',
                'Could not generate token',
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Refresh access token
     */
    public function refresh_token( WP_REST_Request $request ) {

        $refresh_token = $request->get_param( 'refresh_token' );

        try {
            $decoded = JWT::decode(
                $refresh_token,
                new Key( JWT_AUTH_SECRET_KEY, 'HS256' )
            );
        } catch ( Exception $e ) {
            return new WP_Error(
                'invalid_refresh_token',
                'Invalid refresh token',
                [ 'status' => 403 ]
            );
        }

        // 🔐 Ensure refresh token
        if ( empty( $decoded->type ) || $decoded->type !== 'refresh' ) {
            return new WP_Error(
                'invalid_token_type',
                'Refresh token required',
                [ 'status' => 403 ]
            );
        }

        $user_id = $decoded->data->user->id ?? 0;
        $user    = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new WP_Error(
                'invalid_user',
                'User not found',
                [ 'status' => 403 ]
            );
        }

        // 🔁 Issue new access token
        $payload = [
            'iss'  => get_bloginfo( 'url' ),
            'iat'  => time(),
            'exp'  => time() + ( 15 * MINUTE_IN_SECONDS ),
            'type' => 'access',
            'data' => [
                'user' => [
                    'id' => $user_id,
                ],
            ],
        ];

        return new WP_REST_Response( [
            'access_token' => JWT::encode( $payload, JWT_AUTH_SECRET_KEY, 'HS256' ),
            'expires_in'   => 900,
        ], 200 );
    }

    /**
     * Authenticate ALL REST requests (root included)
     */
    public function authenticate_jwt_token( $response ) {

        // ✅ Allow logged-in users
        if ( is_user_logged_in() ) {
            return $response;
        }

        // ✅ Respect existing auth
        if ( ! is_null( $response ) ) {
            return $response;
        }

        $request_path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        $rest_prefix  = '/' . rest_get_url_prefix() . '/';

        // ✅ Allow token & refresh endpoints
        if (
            strpos( $request_path, $rest_prefix . 'endpoints-explorer/v1/token' ) !== false ||
            strpos( $request_path, $rest_prefix . 'endpoints-explorer/v1/refresh' ) !== false
        ) {
            return $response;
        }

        // 🚫 Require Authorization header
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (
            empty( $auth_header ) ||
            ! preg_match( '/Bearer\s(\S+)/', $auth_header, $matches )
        ) {
            return new WP_Error(
                'jwt_required',
                'JWT access token required',
                [ 'status' => 401 ]
            );
        }

        try {
            $decoded = JWT::decode(
                $matches[1],
                new Key( JWT_AUTH_SECRET_KEY, 'HS256' )
            );
        } catch ( Exception $e ) {
            return new WP_Error(
                'invalid_token',
                'Invalid JWT token',
                [ 'status' => 403 ]
            );
        }

        // 🔐 Allow ONLY access tokens
        if ( empty( $decoded->type ) || $decoded->type !== 'access' ) {
            return new WP_Error(
                'invalid_access_token',
                'Access token required',
                [ 'status' => 403 ]
            );
        }

        $user_id = $decoded->data->user->id ?? 0;
        $user    = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new WP_Error(
                'invalid_user',
                'Invalid user in token',
                [ 'status' => 403 ]
            );
        }

        wp_set_current_user( $user_id );

        return null;
    }
}
