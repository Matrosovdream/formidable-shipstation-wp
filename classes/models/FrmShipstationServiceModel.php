<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationServiceModel {
    /** @var wpdb */
    private $db;

    private string $table;

    private const SORTABLE = [ 'id','code','carrier_code','name','is_domestic','is_international' ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_shipstation_services';
    }

    /**
     * Filters:
     *  - id, code, carrier_code
     *  - name (LIKE)
     *  - is_domestic (0|1), is_international (0|1)
     *  - search (LIKE over name, code, carrier_code)
     * Options:
     *  - order_by, order, limit/offset or page/per_page
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( ! empty( $filter['code'] ) ) { $where[] = 'code = %s'; $params[] = (string) $filter['code']; }
        if ( ! empty( $filter['carrier_code'] ) ) { $where[] = 'carrier_code = %s'; $params[] = (string) $filter['carrier_code']; }
        if ( ! empty( $filter['name'] ) ) { $where[] = 'name LIKE %s'; $params[] = '%' . $this->db->esc_like( (string) $filter['name'] ) . '%'; }
        if ( isset( $filter['is_domestic'] ) && $filter['is_domestic'] !== '' ) { $where[] = 'is_domestic = %d'; $params[] = (int) $filter['is_domestic']; }
        if ( isset( $filter['is_international'] ) && $filter['is_international'] !== '' ) { $where[] = 'is_international = %d'; $params[] = (int) $filter['is_international']; }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(name LIKE %s OR code LIKE %s OR carrier_code LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $whereSql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $orderBy = ( isset( $opts['order_by'] ) && in_array( $opts['order_by'], self::SORTABLE, true ) ) ? $opts['order_by'] : 'name';
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
        if ( false === $prepared ) { return new WP_Error( 'db_prepare_failed', __( 'Failed to prepare query.', 'shipstation-wp' ) ); }

        $rows = $this->db->get_results( $prepared, ARRAY_A );
        if ( null === $rows ) { return new WP_Error( 'db_query_failed', __( 'Database query failed.', 'shipstation-wp' ), [ 'last_error' => $this->db->last_error ] ); }
        return $rows;
    }

    public function getById( int $id ) {
        $rows = $this->getList( [ 'id' => $id ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    public function getByCode( string $code, ?string $carrierCode = null ) {
        $filter = [ 'code' => $code ];
        if ( $carrierCode ) { $filter['carrier_code'] = $carrierCode; }
        $rows = $this->getList( $filter, [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    public function getAllByCarrierCode( string $carrierCode, array $opts = [] ) {
        return $this->getList( [ 'carrier_code' => $carrierCode ], $opts );
    }

    public function getPrimary() {
        $rows = $this->getList( [ 'is_primary' => 1 ], [ 'limit' => 1, 'order_by' => 'id', 'order' => 'ASC' ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

}
