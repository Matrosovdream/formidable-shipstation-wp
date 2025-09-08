<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationOrderModel extends FrmShipstationAbstractModel {

    protected $db;
    protected string $table;

    /** Whitelisted sortable columns */
    private const SORTABLE = [
        'id','shp_order_id','shp_order_number','entry_id','order_status','total','shipping_total',
        'carrier_code','service_code','package_code','created_at','updated_at','paid_at', 'ship_date'
    ];

    private FrmShipstationShipmentModel $shipmentModel;

    public function __construct() {

        parent::__construct();
        
        $this->table = $this->db->prefix . 'frm_shipstation_orders';

        // Extra models
        $this->shipmentModel = new FrmShipstationShipmentModel();

    }

    /**
     * Base list query.
     *
     * Supported $filter keys (all optional):
     *  - shp_order_id (int)
     *  - shp_order_number (string)
     *  - entry_id (int)
     *  - order_status (string|array)
     *  - created_from (Y-m-d or datetime)
     *  - created_to   (Y-m-d or datetime)
     *  - paid_from    (Y-m-d or datetime)
     *  - paid_to      (Y-m-d or datetime)
     *  - search       (string; LIKE on order number, carrier/service)
     *
     * $opts:
     *  - order_by (one of self::SORTABLE) default 'created_at'
     *  - order    ('ASC'|'DESC') default 'DESC'
     *  - limit    (int) default 50
     *  - offset   (int) default 0 (or use page/per_page)
     *  - page, per_page (ints) — convenience for LIMIT/OFFSET
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        // Exact matches
        if ( isset( $filter['shp_order_id'] ) && $filter['shp_order_id'] !== '' ) {
            $where[]  = 'shp_order_id = %d';
            $params[] = (int) $filter['shp_order_id'];
        }
        if ( ! empty( $filter['shp_order_number'] ) ) {
            $where[]  = 'shp_order_number = %s';
            $params[] = (string) $filter['shp_order_number'];
        }
        if ( isset( $filter['entry_id'] ) && $filter['entry_id'] !== '' ) {
            $where[]  = 'entry_id = %d';
            $params[] = (int) $filter['entry_id'];
        }

        // Status (single or array)
        if ( isset( $filter['order_status'] ) && $filter['order_status'] !== '' ) {
            $statuses = is_array( $filter['order_status'] ) ? array_filter( $filter['order_status'], 'strlen' ) : [ (string) $filter['order_status'] ];
            $statuses = array_values( array_unique( array_map( 'strval', $statuses ) ) );
            if ( $statuses ) {
                $ph = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
                $where[] = "order_status IN ($ph)";
                $params  = array_merge( $params, $statuses );
            }
        }

        // Date ranges
        if ( ! empty( $filter['created_from'] ) ) { $where[] = 'created_at >= %s'; $params[] = $this->dateToMysql( $filter['created_from'] ); }
        if ( ! empty( $filter['created_to'] ) )   { $where[] = 'created_at <= %s'; $params[] = $this->dateToMysql( $filter['created_to'] ); }
        if ( ! empty( $filter['paid_from'] ) )    { $where[] = 'paid_at >= %s';    $params[] = $this->dateToMysql( $filter['paid_from'] ); }
        if ( ! empty( $filter['paid_to'] ) )      { $where[] = 'paid_at <= %s';    $params[] = $this->dateToMysql( $filter['paid_to'] ); }

        // Free-text search
        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(shp_order_number LIKE %s OR carrier_code LIKE %s OR service_code LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        // Sorting
        $orderBy = isset( $opts['order_by'] ) && in_array( $opts['order_by'], self::SORTABLE, true ) ? $opts['order_by'] : 'created_at';
        $order   = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        // Pagination
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

        foreach ( $rows as &$row ) {

            // Add a shipments
            $row['shipments'] = $this->shipmentModel->getAllByOrderId( (int) $row['shp_order_id'] );
        }

        return $rows;
    }

    /** Wrapper: first row by order number */
    public function getByOrderNumber( string $orderNumber ) {
        $rows = $this->getList( [ 'shp_order_number' => $orderNumber ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Wrapper: first row by order id */
    public function getByOrderId( int $orderId ) {
        $rows = $this->getList( [ 'shp_order_id' => $orderId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    /** Wrapper: first row by Formidable entry id */
    public function getByEntryId( int $entryId ) {
        $rows = $this->getList( [ 'entry_id' => $entryId ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    private function dateToMysql( string $in ): string {
        // Accepts 'Y-m-d' or ISO8601; normalizes to MySQL DATETIME (UTC assumed)
        $t = strtotime( $in );
        if ( ! $t ) { return $in; }
        return gmdate( 'Y-m-d H:i:s', $t );
    }

    public function multipleUpdateCreate( array $rows ) {
        
        $cols = [
            'shp_order_id', 'shp_order_number', 'entry_id', 'order_status',
            'total', 'shipping_total', 'carrier_code', 'service_code', 'package_code',
            'created_at', 'updated_at', 'paid_at', 'ship_date'
        ];
        $formats = [
            'shp_order_id' => '%d',
            'shp_order_number' => '%s',
            'entry_id' => '%d',
            'order_status' => '%s',
            'total' => '%f',
            'shipping_total' => '%f',
            'carrier_code' => '%s',
            'service_code' => '%s',
            'package_code' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
            'paid_at' => '%s',
            'ship_date' => '%s',
        ];

        return $this->multipleUpdateCreateAbstract( $rows, $cols, $formats, $uniqueKey = 'shp_order_id' );
        
    }
    
    /** Normalize incoming row to expected column types */
    protected function normalizeOrderRow( array $row ): array {
        // Cast numerics
        if ( isset( $row['shp_order_id'] ) ) { $row['shp_order_id'] = (int) $row['shp_order_id']; }
        if ( isset( $row['entry_id'] ) ) { $row['entry_id'] = (int) $row['entry_id']; }
        if ( isset( $row['total'] ) ) { $row['total'] = (float) $row['total']; }
        if ( isset( $row['shipping_total'] ) ){ $row['shipping_total'] = (float) $row['shipping_total']; }
        
        
        // Normalize datetimes if present
        foreach ( [ 'created_at', 'updated_at', 'paid_at' ] as $k ) {
            if ( isset( $row[$k] ) && $row[$k] !== null && $row[$k] !== '' ) {
                $row[$k] = $this->dateToMysql( (string) $row[$k] );
            }
        }
        return $row;
    }

}
