<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class FrmShipstationAbstractApi {
    private const UA = 'ShipStation-WP/0.1.0';

    private string $apiBase;
    private string $apiKey;
    private string $apiSecret;

    protected string $defaultCarrierCode = '';
    protected string $defaultServiceCode = '';
    protected string $defaultConfirmation = 'none'; // none|delivery|signature|adult_signature
    protected bool   $defaultInsurance   = false;
    protected float  $defaultInsuranceAmount = 0.0;

    protected bool   $logging = false;

    /**
     * @param array $overrides Optional overrides, e.g. [ 'api_key' => '...', 'api_secret' => '...', 'api_base' => 'https://...' ]
     */
    public function __construct( array $overrides = [] ) {

        $defaults = [
            'api_key'          => '',
            'api_secret'       => '',
            'api_base'         => 'https://ssapi.shipstation.com',
            'carrier_code'     => '',
            'service_code'     => '',
            'confirmation'     => 'none',
            'insurance'        => false,
            'insurance_amount' => '0.00',
            'logging'          => false,
        ];

        // Pull saved plugin settings if they exist
        $cfg = get_option( 'frm_shipstation', [] );

        $this->apiKey    = $cfg['api_key'];
        $this->apiSecret = $cfg['api_secret'];
        $this->apiBase   = rtrim( (string) $cfg['api_base'], '/' );

        $this->defaultCarrierCode     = (string) $cfg['carrier_code'];
        $this->defaultServiceCode     = (string) $cfg['service_code'];
        $this->defaultConfirmation    = (string) $cfg['confirmation'];
        $this->defaultInsurance       = (bool)   $cfg['insurance'];
        $this->defaultInsuranceAmount = (float)  $cfg['insurance_amount'];
        $this->logging                = (bool)   $cfg['logging'];

    }

    // -------------------------
    // Public API
    // -------------------------

    /**
     * Core HTTP runner using wp_remote_request
     * @param string     $method
     * @param string     $path
     * @param array      $query
     * @param array|null $body
     * @return array|WP_Error
     */
    protected function request( string $method, string $path, array $query = [], ?array $body = null ) {
        if ( empty( $this->apiKey ) || empty( $this->apiSecret ) ) {
            return new WP_Error( 'shipstation_no_keys', __( 'ShipStation API credentials are missing.', 'shipstation-wp' ), [ 'status' => 401 ] );
        }

        $url = $this->apiBase . '/' . ltrim( $path, '/' );
        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->apiKey . ':' . $this->apiSecret ),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'    => self::UA . ' (+ ' . site_url() . ')',
            ],
        ];
        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $this->log( 'HTTP ' . $args['method'] . ' ' . $url, [ 'body' => $body ] );

        $res = wp_remote_request( $url, $args );
        if ( is_wp_error( $res ) ) {
            $this->log( 'HTTP error', [ 'error' => $res->get_error_message() ] );
            return new WP_Error( 'shipstation_http_error', $res->get_error_message(), [ 'status' => 502 ] );
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        $raw  = (string) wp_remote_retrieve_body( $res );
        $json = json_decode( $raw, true );

        if ( $code >= 400 ) {
            $msg = is_array( $json ) && isset( $json['Message'] ) ? (string) $json['Message'] : ( $raw ?: 'HTTP ' . $code );
            $this->log( 'HTTP ' . $code . ' response', [ 'body' => $json ] );
            return new WP_Error( 'shipstation_http_' . $code, $msg, [ 'status' => $code, 'body' => $json ] );
        }

        return is_array( $json ) ? $json : [ 'raw' => $raw ];
    }

    /** Enable/disable debug logging at runtime */
    public function setLogging( bool $enabled ): void { $this->logging = $enabled; }

    /**
     * Basic logger using error_log; guarded by $this->logging
     */
    private function log( string $message, array $context = [] ): void {
        if ( ! $this->logging ) { return; }
        $line = '[ShipStation] ' . $message;
        if ( ! empty( $context ) ) {
            $line .= ' ' . wp_json_encode( $context );
        }
        if ( function_exists( 'error_log' ) ) {
            error_log( $line );
        }
    }

    /**
     * listOrder — list ShipStation orders (v1) with optional auto-pagination.
     *
     * @param array $params
     * @param bool  $autoPaginate  true = fetch all pages and return ['{items}'=>[...], 'total'=>N]
     *                             false = return ShipStation's raw page envelope
     * @return array|WP_Error
     */
    public function getListing( 
        array $params = [], 
        array $allowedParams, 
        string $requestUrl, 
        string $itemsVar,
        bool $autoPaginate = true 
        ) {

        // ['sortBy','sortDir','page','pageSize']    

        // Whitelist of allowed query params for listing
        $allowed = array_merge(
            $allowedParams
        );

        $query = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $params) && $params[$k] !== '' && $params[$k] !== null) {
                $query[$k] = $params[$k];
            }
        }

        // defaults
        if (empty($query['page']))     { $query['page'] = 1; }
        if (empty($query['pageSize'])) { $query['pageSize'] = 100; }

        // First request
        $first = $this->request('GET', $requestUrl, $query);

        if (is_wp_error($first)) { return $first; }
        if (!$autoPaginate)      { return $first; }

        $all   = isset($first[ $itemsVar ]) && is_array($first[ $itemsVar ]) ? $first[ $itemsVar ] : [];
        $total = isset($first['total']) ? (int) $first['total'] : null;
        $pages = isset($first['pages']) ? (int) $first['pages'] : null;

        $page = (int) ($first['page'] ?? $query['page']);
        $size = (int) ($first['pageSize'] ?? $query['pageSize']);

        while (true) {
            if ($pages !== null && $page >= $pages) { break; }
            if ($pages === null && $total !== null && count($all) >= $total) { break; }

            $page++;
            $query['page'] = $page;

            $resp = $this->request('GET', $requestUrl, $query);
            if (is_wp_error($resp)) { return $resp; }

            $chunk = isset($resp[ $itemsVar ]) && is_array($resp[ $itemsVar ]) ? $resp[ $itemsVar ] : [];
            if (empty($chunk)) { break; }
            $all = array_merge($all, $chunk);

            if (isset($resp['pages'])) { $pages = (int) $resp['pages']; }
            if (isset($resp['total'])) { $total = (int) $resp['total']; }

            // safety stops
            if (count($all) >= 200000) { break; }
            if (count($chunk) < $size) { break; }
        }

        return ['items' => $all, 'total' => $total ?? count($all)];
    }

}

// --- Optional convenience factory ---
if ( ! function_exists( 'frm_shipstation' ) ) {
    /**
     * Get a shared instance with settings loaded from options/constants.
     * @return FrmShipstationApi
     */
    function frm_shipstation(): FrmShipstationApi {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new FrmShipstationApi();
        }
        return $instance;
    }
}
