<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationShipmentModel extends FrmShipstationAbstractModel {
    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns */
    private const SORTABLE = [
        'id','shp_order_id','shp_order_number','entry_id','shipment_cost','insurance_cost','carrier_code','service_code','package_code','tracking_number','is_voided','void_date','created_at','shipped_at'
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_shipstation_shipments';
    }

    /**
     * Base list query.
     *
     * Supported $filter keys (all optional):
     *  - id (int)
     *  - shp_order_id (int)
     *  - shp_order_number (string)
     *  - entry_id (int)
     *  - tracking_number (string)
     *  - is_voided (0|1)
     *  - carrier_code, service_code, package_code (string)
     *  - created_from / created_to (Y-m-d or datetime)
     *  - shipped_from / shipped_to (Y-m-d or datetime)
     *  - search (string; LIKE on order number, carrier/service, tracking)
     *
     * $opts:
     *  - order_by (one of self::SORTABLE) default 'created_at'
     *  - order ('ASC'|'DESC') default 'DESC'
     *  - limit (int) default 50
     *  - offset (int) default 0 (or use page/per_page)
     *  - page, per_page (ints) — convenience
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( isset( $filter['shp_order_id'] ) && $filter['shp_order_id'] !== '' ) { $where[] = 'shp_order_id = %d'; $params[] = (int) $filter['shp_order_id']; }
        if ( ! empty( $filter['shp_order_number'] ) ) { $where[] = 'shp_order_number = %s'; $params[] = (string) $filter['shp_order_number']; }
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) { $where[] = 'entry_id = %d'; $params[] = (int) $filter['entry_id']; }
        if ( ! empty( $filter['tracking_number'] ) ) { $where[] = 'tracking_number = %s'; $params[] = (string) $filter['tracking_number']; }

        if ( isset( $filter['is_voided'] ) && $filter['is_voided'] !== '' ) { $where[] = 'is_voided = %d'; $params[] = (int) $filter['is_voided']; }
        if ( ! empty( $filter['carrier_code'] ) ) { $where[] = 'carrier_code = %s'; $params[] = (string) $filter['carrier_code']; }
        if ( ! empty( $filter['service_code'] ) ) { $where[] = 'service_code = %s'; $params[] = (string) $filter['service_code']; }
        if ( ! empty( $filter['package_code'] ) ) { $where[] = 'package_code = %s'; $params[] = (string) $filter['package_code']; }

        if ( ! empty( $filter['created_from'] ) ) { $where[] = 'created_at >= %s'; $params[] = $this->dateToMysql( $filter['created_from'] ); }
        if ( ! empty( $filter['created_to'] ) )   { $where[] = 'created_at <= %s'; $params[] = $this->dateToMysql( $filter['created_to'] ); }
        if ( ! empty( $filter['shipped_from'] ) ) { $where[] = 'shipped_at >= %s'; $params[] = $this->dateToMysql( $filter['shipped_from'] ); }
        if ( ! empty( $filter['shipped_to'] ) )   { $where[] = 'shipped_at <= %s'; $params[] = $this->dateToMysql( $filter['shipped_to'] ); }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(shp_order_number LIKE %s OR carrier_code LIKE %s OR service_code LIKE %s OR tracking_number LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = isset( $opts['order_by'] ) && in_array( $opts['order_by'], self::SORTABLE, true ) ? $opts['order_by'] : 'created_at';
        $order   = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $limit  = isset( $opts['limit'] ) ? max( 1, (int) $opts['limit'] ) : 50;
        $offset = isset( $opts['offset'] ) ? max( 0, (int) $opts['offset'] ) : 0;
        if ( isset( $opts['page'] ) || isset( $opts['per_page'] ) ) {
            $pp     = isset( $opts['per_page'] ) ? max( 1, (int) $opts['per_page'] ) : 50;
            $page   = isset( $opts['page'] ) ? max( 1, (int) $opts['page'] ) : 1;
            $limit  = $pp;
            $offset = ( $page - 1 ) * $pp;
        }

        $sql  = "SELECT * FROM {$this->table} {$whereSql} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d";
        $args = array_merge( $params, [ $limit, $offset ] );
        $prepared = $this->db->prepare( $sql, $args );
        if ( false === $prepared ) {
            return new WP_Error( 'db_prepare_failed', __( 'Failed to prepare query.', 'shipstation-wp' ) );
        }
        $rows = $this->db->get_results( $prepared, ARRAY_A );
        if ( null === $rows ) {
            return new WP_Error( 'db_query_failed', __( 'Database query failed.', 'shipstation-wp' ), [ 'last_error' => $this->db->last_error ] );
        }
        return $rows;
    }

    /** All shipments by ShipStation order id */
    public function getAllByOrderId( int $shpOrderId, array $opts = [] ) {
        return $this->getList( [ 'shp_order_id' => $shpOrderId ], $opts );
    }

    /** All shipments by ShipStation order number */
    public function getAllByOrderNumber( string $orderNumber, array $opts = [] ) {
        return $this->getList( [ 'shp_order_number' => $orderNumber ], $opts );
    }

    /** Single shipment by tracking number */
    public function getByTrackingNumber( string $trackingNumber ) {
        $rows = $this->getList( [ 'tracking_number' => $trackingNumber ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Single shipment by primary id */
    public function getById( int $id ) {
        $rows = $this->getList( [ 'id' => $id ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    public function multipleUpdateCreate( array $rows ) {
        
        $cols = [
            'shp_order_id','shp_order_number','shipment_id','entry_id','shipment_total','insurance_cost',
            'tracking_number','carrier_code','service_code','package_code',
            'is_voided','voided_at','ship_to','weight','dimensions','created_at','updated_at','shipped_at'
        ];
        $formats = [
            'shipment_id' => '%d',
            'shp_order_id' => '%d',
            'shp_order_number' => '%s',
            'entry_id' => '%d',
            'shipment_cost' => '%f',
            'insurance_cost' => '%f',
            'tracking_number' => '%s',
            'carrier_code' => '%s',
            'service_code' => '%s',
            'package_code' => '%s',
            'is_voided' => '%d',
            'voided_date' => '%s',
            'ship_to' => '%s',
            'weight' => '%s',
            'dimensions' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
            'shipped_at' => '%s',   
        ];

        return $this->multipleUpdateCreateAbstract( $rows, $cols, $formats, $uniqueKey = 'shipment_id' );
        
    }

    // ----------------------- utils -----------------------
    /*
    private function dateToMysql( string $in ): string {
        $t = strtotime( $in );
        if ( ! $t ) { return $in; }
        return gmdate( 'Y-m-d H:i:s', $t );
    }
    */

}
