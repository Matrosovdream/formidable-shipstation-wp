<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationCarrierModel {

    private $db;

    private string $table;

    private const SORTABLE = [ 'id','name','code','account_number','balance','is_primary','is_req_funded' ];

    private $serviceModel;
    private $packageModel;


    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_shipstation_carriers';

        // Extra models
        $this->serviceModel = new FrmShipstationServiceModel();
        $this->packageModel = new FrmShipstationPackageModel();

    }

    /**
     * Filters:
     *  - id, code, account_number
     *  - name (LIKE)
     *  - is_primary (0|1), is_req_funded (0|1)
     *  - min_balance, max_balance (numeric)
     *  - search (LIKE over name, code, account_number)
     * Options:
     *  - order_by, order, limit/offset or page/per_page
     */
    public function getList( array $filter = [], array $opts = [] ) {
        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( ! empty( $filter['code'] ) ) { $where[] = 'code = %s'; $params[] = (string) $filter['code']; }
        if ( ! empty( $filter['account_number'] ) ) { $where[] = 'account_number = %s'; $params[] = (string) $filter['account_number']; }
        if ( ! empty( $filter['name'] ) ) { $where[] = 'name LIKE %s'; $params[] = '%' . $this->db->esc_like( (string) $filter['name'] ) . '%'; }
        if ( isset( $filter['is_primary'] ) && $filter['is_primary'] !== '' ) { $where[] = 'is_primary = %d'; $params[] = (int) $filter['is_primary']; }
        if ( isset( $filter['is_req_funded'] ) && $filter['is_req_funded'] !== '' ) { $where[] = 'is_req_funded = %d'; $params[] = (int) $filter['is_req_funded']; }
        if ( isset( $filter['min_balance'] ) && $filter['min_balance'] !== '' ) { $where[] = 'balance >= %f'; $params[] = (float) $filter['min_balance']; }
        if ( isset( $filter['max_balance'] ) && $filter['max_balance'] !== '' ) { $where[] = 'balance <= %f'; $params[] = (float) $filter['max_balance']; }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[]  = '(name LIKE %s OR code LIKE %s OR account_number LIKE %s)';
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

        foreach ( $rows as &$row ) {

            // Add services
            $row['services'] = $this->serviceModel->getAllByCarrierCode( (string) $row['code'] );

            // Add packages
            $row['packages'] = $this->packageModel->getAllByCarrierCode( (string) $row['code'] );
        }

        return $rows;
    }

    public function getById( int $id ) {
        $rows = $this->getList( [ 'id' => $id ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    public function getByCode( string $code ) {
        $rows = $this->getList( [ 'code' => $code ], [ 'limit' => 1 ] );
        if ( is_wp_error( $rows ) ) { return $rows; }
        return $rows[0] ?? null;
    }

    public function getPrimary() {
        $rows = $this->getList( [ 'is_primary' => 1 ], [ 'limit' => 1, 'order_by' => 'id', 'order' => 'ASC' ] );
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
        if ( empty( $rows ) ) {
            return [ 'processed' => 0, 'chunks' => 0, 'rows_affected' => 0, 'errors' => [] ];
        }
        
        $cols = [
            'name', 'code', 'account_number', 'balance', 'is_primary', 'is_req_funded'
        ];
        $formats = [
            'name' => '%s', 'code' => '%s', 'account_number' => '%s', 'balance' => '%f', 'is_primary' => '%d', 'is_req_funded' => '%d'
        ];
        
        $processed = 0; $chunks = 0; $affected = 0; $errors = [];
        $batch = [];
        
        foreach ( $rows as $idx => $row ) {
            if ( ! is_array( $row ) ) { continue; }
            if ( empty( $row['code'] ) ) {
                $errors[] = "Row {$idx}: missing code";
                continue;
            }
            $batch[] = $this->normalizeOrderRow( $row );
            
            if ( count( $batch ) >= 100 ) {
                $res = $this->upsertChunk( $batch, $cols, $formats );
                if ( is_wp_error( $res ) ) { $errors[] = $res->get_error_message(); }
                else { $affected += (int) $res; $processed += count( $batch ); $chunks++; }
                $batch = [];
            }
        }
        
        if ( $batch ) {
            $res = $this->upsertChunk( $batch, $cols, $formats );
            if ( is_wp_error( $res ) ) { $errors[] = $res->get_error_message(); }
            else { $affected += (int) $res; $processed += count( $batch ); $chunks++; }
        }
        
        return [ 'processed' => $processed, 'chunks' => $chunks, 'rows_affected' => $affected, 'errors' => $errors ];
    }

    private function upsertChunk( array $batch, array $cols, array $formats ) {

        $valuesSql = [];
        foreach ( $batch as $row ) {
            $vals = [];
            foreach ( $cols as $c ) {
                $vals[] = $this->escapeValueForSql( $formats[$c], $row[$c] ?? null );
            }
            $valuesSql[] = '(' . implode( ',', $vals ) . ')';
        }
        $colList = implode( ',', $cols );
        
        // Update all fields except the unique key
        $updateCols = array_diff( $cols, [ 'code' ] );
        $updates = array_map( fn($c) => "$c=VALUES($c)", $updateCols );
        
        $sql = "INSERT INTO {$this->table} ({$colList}) VALUES "
        . implode( ',', $valuesSql )
        . " ON DUPLICATE KEY UPDATE " . implode( ',', $updates );
        
        $res = $this->db->query( $sql );
        if ( $res === false ) {
            return new WP_Error( 'db_upsert_failed', __( 'Bulk upsert failed.', 'shipstation-wp' ), [ 'last_error' => $this->db->last_error ] );
        }
        return $res; // rows affected

    }
    
    /** Normalize incoming row to expected column types */
    private function normalizeOrderRow( array $row ): array {
        return $row;
    }
    
    /** Escape a single value into SQL literal, respecting NULL and type */
    private function escapeValueForSql( string $format, $value ): string {
        if ( $value === null || $value === '' ) {
            return 'NULL';
        }
        switch ( $format ) {
            case '%d':
                return (string) (int) $value;
            case '%f':
                return (string) (float) $value;
            case '%s':
            default:
                return $this->db->prepare( '%s', (string) $value );
        }
    }

}
