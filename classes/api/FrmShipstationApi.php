<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationApi {
    private const UA = 'ShipStation-WP/0.1.0';

    private string $apiBase;
    private string $apiKey;
    private string $apiSecret;

    private string $defaultCarrierCode = '';
    private string $defaultServiceCode = '';
    private string $defaultConfirmation = 'none'; // none|delivery|signature|adult_signature
    private bool   $defaultInsurance   = false;
    private float  $defaultInsuranceAmount = 0.0;

    private bool   $logging = false;

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
     * 1) List of carriers
     * @return array|WP_Error
     */
    public function listCarriers() {
        return $this->request( 'GET', '/carriers' );
    }

    // List services for a carrier
    public function listCarrierServices( array $args ) {
        if ( empty( $args['carrierCode'] ) ) {
            return new WP_Error( 'shipstation_carriercode_required', __( 'carrierCode is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'GET', '/carriers/listservices/?carrierCode=' . urlencode( $args['carrierCode'] ) );
    }

    // List packages for a carrier
    public function listCarrierPackages( array $args ) {
        if ( empty( $args['carrierCode'] ) ) {
            return new WP_Error( 'shipstation_carriercode_required', __( 'carrierCode is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'GET', '/carriers/listpackages/?carrierCode=' . urlencode( $args['carrierCode'] ) );
    }

    /**
     * 2) Get order information
     * @param array $args [ 'id' => (int), 'number' => (string) ]
     * @return array|WP_Error Single order (by id) or array of orders (by number)
     */
    public function getOrder( array $args ) {
        if ( ! empty( $args['id'] ) ) {
            return $this->request( 'GET', '/orders/' . (int) $args['id'] );
        }
        if ( ! empty( $args['number'] ) ) {
            $out = $this->request( 'GET', '/orders', [ 'orderNumber' => (string) $args['number'] ] );
            // /orders returns an envelope { orders: [], total: n }
            if ( is_array( $out ) && isset( $out['orders'] ) ) {
                return $out['orders'];
            }
            return $out;
        }
        return new WP_Error( 'shipstation_order_param', __( 'Provide order "id" or "number".', 'shipstation-wp' ), [ 'status' => 400 ] );
    }

    /**
     * 3) Get shipments by order
     * @param array $args [ 'id' => (int), 'number' => (string) ]
     * @return array|WP_Error shipments[]
     */
    public function getShipmentsByOrder( array $args ) {
        $query = [];
        if ( ! empty( $args['id'] ) ) {
            $query['orderId'] = (int) $args['id'];
        } elseif ( ! empty( $args['number'] ) ) {
            $query['orderNumber'] = (string) $args['number'];
        } else {
            return new WP_Error( 'shipstation_ship_param', __( 'Provide order "id" or "number".', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        $out = $this->request( 'GET', '/shipments', $query );
        if ( is_array( $out ) && isset( $out['shipments'] ) ) {
            return $out['shipments'];
        }
        return $out;
    }

    /**
     * 4) Get labels by order (derived from shipments)
     * @param array $args [ 'id' => (int), 'number' => (string) ]
     * @param bool $includeData Include base64 labelData (PDF) if present
     * @return array label summaries
     */
    public function getLabelsByOrder( array $args, bool $includeData = false ): array|WP_Error {
        $shipments = $this->getShipmentsByOrder( $args );
        if ( is_wp_error( $shipments ) ) {
            return $shipments;
        }
        return $this->labelsFromShipments( is_array( $shipments ) ? $shipments : [], $includeData );
    }

    /**
     * 5) Create label for order
     * @param array $params Supports either order_id or order_number. Optional overrides: carrier_code, service_code, package_code, confirmation, weight_value, weight_units, weight (array)
     * @return array|WP_Error API response with label info
     */
    public function createLabelForOrder( array $params ) {
        $order = null;
        $order_id = null;
        
        
        if ( ! empty( $params['order_id'] ) ) {
            $order_id = (int) $params['order_id'];
            $order = $this->getOrder( [ 'id' => $order_id ] );
        } elseif ( ! empty( $params['order_number'] ) ) {
            $orders = $this->getOrder( [ 'number' => (string) $params['order_number'] ] );
        if ( is_array( $orders ) && ! empty( $orders[0]['orderId'] ) ) {
            $order = $orders[0];
            $order_id = (int) $order['orderId'];
        }
        } else {
            return new WP_Error( 'shipstation_label_param', __( 'Provide order_id or order_number.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        
        
        if ( is_wp_error( $order ) || empty( $order ) || empty( $order_id ) ) {
            return new WP_Error( 'shipstation_order_not_found', __( 'Order not found.', 'shipstation-wp' ), [ 'status' => 404 ] );
        }
        
        
        $carrier = (string) ( $params['carrier_code'] ?? $this->defaultCarrierCode );
        $service = (string) ( $params['service_code'] ?? $this->defaultServiceCode );
        $package = (string) ( $params['package_code'] ?? 'package' );
        $confirm = (string) ( $params['confirmation'] ?? $this->defaultConfirmation );
        
        
        // Weight can be provided as ['value'=>..,'units'=>..] or separate value/units
        $weight = $params['weight'] ?? null;
        if ( ! is_array( $weight ) ) {
            $weight = [
                'value' => isset( $params['weight_value'] ) ? (float) $params['weight_value'] : 0.0,
                'units' => (string) ( $params['weight_units'] ?? 'ounces' ),
            ];
        }
        
        if ( $carrier === '' || $service === '' ) {
            return new WP_Error( 'shipstation_missing_defaults', __( 'Carrier/Service are required (set defaults or pass overrides).', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        
        
        $shipment = [
            'orderId' => $order_id,
            'carrierCode' => $carrier,
            'serviceCode' => $service,
            'packageCode' => $package,
            'confirmation'=> $confirm,
            'shipTo' => $order['shipTo'] ?? ( $order['advancedOptions']['shipTo'] ?? null ),
            'shipFrom' => $order['shipFrom'] ?? ( $order['advancedOptions']['shipFrom'] ?? null ),
            'weight' => $weight,
        ];
        
        
        if ( $this->defaultInsurance && $this->defaultInsuranceAmount > 0 ) {
            $shipment['insuranceOptions'] = [ 'insureShipment' => true, 'insuredValue' => $this->defaultInsuranceAmount ];
        }
        /*
        echo '<pre>';
        print_r($shipment);
        echo '</pre>';
        */
        return $this->request( 'POST', '/orders/createlabelfororder', [], [ 'shipment' => $shipment ] );
        
    }

    /**
     * 6) Void label
     * @param int|string $labelId
     * @return array|WP_Error
     */
    public function voidLabel( $labelId ) {
        if ( empty( $labelId ) ) {
            return new WP_Error( 'shipstation_labelid_required', __( 'label_id is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'POST', '/labels/voidlabel', [], [ 'labelId' => (int) $labelId ] );
    }

    // -------------------------
    // Helpers
    // -------------------------

    /**
     * Map shipments[] -> compact labels[]
     * @param array $shipments
     * @param bool  $includeData Include base64 labelData
     * @return array
     */
    public function labelsFromShipments( array $shipments, bool $includeData = false ): array {
        $labels = [];
        foreach ( $shipments as $s ) {
            // Some shipments include labelId/labelData on the shipment object.
            if ( empty( $s['labelId'] ) && empty( $s['labelData'] ) ) { continue; }
            $labels[] = [
                'shipmentId'     => $s['shipmentId']     ?? null,
                'labelId'        => $s['labelId']        ?? null,
                'carrierCode'    => $s['carrierCode']    ?? null,
                'serviceCode'    => $s['serviceCode']    ?? null,
                'trackingNumber' => $s['trackingNumber'] ?? null,
                'shipDate'       => $s['shipDate']       ?? null,
                'labelCreateDate'=> $s['createDate']     ?? null,
                'labelData'      => $includeData ? ( $s['labelData'] ?? null ) : null,
            ];
        }
        return $labels;
    }

    /**
     * Core HTTP runner using wp_remote_request
     * @param string     $method
     * @param string     $path
     * @param array      $query
     * @param array|null $body
     * @return array|WP_Error
     */
    private function request( string $method, string $path, array $query = [], ?array $body = null ) {
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
     * Accepts common ShipStation filters; any of these keys you pass will be sent:
     *   orderNumber, customerName, orderStatus, paymentStatus, storeId, tagId,
     *   orderDateStart, orderDateEnd, createDateStart, createDateEnd,
     *   modifyDateStart, modifyDateEnd, sortBy, sortDir, page, pageSize
     *
     * @param array $params
     * @param bool  $autoPaginate  true = fetch all pages and return ['orders'=>[...], 'total'=>N]
     *                             false = return ShipStation's raw page envelope
     * @return array|WP_Error
     */
    public function listOrder( array $params = [], bool $autoPaginate = true ) {
        // Allow some friendly aliases
        if (isset($params['createDateStart']) && empty($params['orderDateStart'])) {
            // (optional) map to orderDateStart if you prefer that window
            // $params['orderDateStart'] = $params['createDateStart'];
            // unset($params['createDateStart']);
        }
        if (isset($params['createDateEnd']) && empty($params['orderDateEnd'])) {
            // $params['orderDateEnd'] = $params['createDateEnd'];
            // unset($params['createDateEnd']);
        }

        // Whitelist of allowed query params for /orders
        $allowed = [
            'orderNumber','customerName','orderStatus','paymentStatus','storeId','tagId',
            'orderDateStart','orderDateEnd','createDateStart','createDateEnd',
            'modifyDateStart','modifyDateEnd','sortBy','sortDir','page','pageSize',
        ];

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
        $first = $this->request('GET', '/orders', $query);
        if (is_wp_error($first)) { return $first; }
        if (!$autoPaginate)      { return $first; }

        $all   = isset($first['orders']) && is_array($first['orders']) ? $first['orders'] : [];
        $total = isset($first['total']) ? (int) $first['total'] : null;
        $pages = isset($first['pages']) ? (int) $first['pages'] : null;

        $page = (int) ($first['page'] ?? $query['page']);
        $size = (int) ($first['pageSize'] ?? $query['pageSize']);

        while (true) {
            if ($pages !== null && $page >= $pages) { break; }
            if ($pages === null && $total !== null && count($all) >= $total) { break; }

            $page++;
            $query['page'] = $page;

            $resp = $this->request('GET', '/orders', $query);
            if (is_wp_error($resp)) { return $resp; }

            $chunk = isset($resp['orders']) && is_array($resp['orders']) ? $resp['orders'] : [];
            if (empty($chunk)) { break; }
            $all = array_merge($all, $chunk);

            if (isset($resp['pages'])) { $pages = (int) $resp['pages']; }
            if (isset($resp['total'])) { $total = (int) $resp['total']; }

            // safety stops
            if (count($all) >= 200000) { break; }
            if (count($chunk) < $size) { break; }
        }

        return ['orders' => $all, 'total' => $total ?? count($all)];
    }

    /** Convenience alias */
    public function listOrders( array $params = [], bool $autoPaginate = true ) {
        return $this->listOrder($params, $autoPaginate);
    }

        /**
     * listShipment — list ShipStation shipments (v1) with optional auto-pagination.
     *
     * Accepts common ShipStation filters; any of these keys you pass will be sent:
     *   orderId, orderNumber, recipientName, trackingNumber,
     *   carrierCode, serviceCode, batchId,
     *   shipDateStart, shipDateEnd,
     *   createDateStart, createDateEnd,
     *   voidDateStart,   voidDateEnd,
     *   sortBy, sortDir, page, pageSize
     *
     * @param array $params
     * @param bool  $autoPaginate  true = fetch all pages and return ['shipments'=>[...], 'total'=>N]
     *                             false = return ShipStation's raw page envelope
     * @return array|WP_Error
     */
    public function listShipment( array $params = [], bool $autoPaginate = true ) {
        // Whitelist of allowed query params for /shipments
        $allowed = [
            'orderId','orderNumber','recipientName','trackingNumber',
            'carrierCode','serviceCode','batchId',
            'shipDateStart','shipDateEnd',
            'createDateStart','createDateEnd',
            'voidDateStart','voidDateEnd',
            'sortDir','page','pageSize',
        ];

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
        $first = $this->request('GET', '/shipments', $query);
        if (is_wp_error($first)) { return $first; }
        if (!$autoPaginate)      { return $first; }

        $all   = isset($first['shipments']) && is_array($first['shipments']) ? $first['shipments'] : [];
        $total = isset($first['total']) ? (int) $first['total'] : null;
        $pages = isset($first['pages']) ? (int) $first['pages'] : null;

        $page = (int) ($first['page'] ?? $query['page']);
        $size = (int) ($first['pageSize'] ?? $query['pageSize']);

        while (true) {
            if ($pages !== null && $page >= $pages) { break; }
            if ($pages === null && $total !== null && count($all) >= $total) { break; }

            $page++;
            $query['page'] = $page;

            $resp = $this->request('GET', '/shipments', $query);
            if (is_wp_error($resp)) { return $resp; }

            $chunk = isset($resp['shipments']) && is_array($resp['shipments']) ? $resp['shipments'] : [];
            if (empty($chunk)) { break; }
            $all = array_merge($all, $chunk);

            if (isset($resp['pages'])) { $pages = (int) $resp['pages']; }
            if (isset($resp['total'])) { $total = (int) $resp['total']; }

            // safety stops
            if (count($all) >= 200000) { break; }
            if (count($chunk) < $size) { break; }
        }

        return ['shipments' => $all, 'total' => $total ?? count($all)];
    }

    /** Convenience alias */
    public function listShipments( array $params = [], bool $autoPaginate = true ) {
        return $this->listShipment($params, $autoPaginate);
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
