<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationMigrations {
    public const DB_VERSION = '1.0.4';
    public const VERSION_OPTION = 'shipstation_wp_db_version';

    /** Run on plugin activation */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        $orders    = self::sql_orders( $prefix, $charset_collate );
        $shipments = self::sql_shipments( $prefix, $charset_collate );
        $carriers  = self::sql_carriers( $prefix, $charset_collate );
        $packages  = self::sql_packages( $prefix, $charset_collate );
        $services  = self::sql_services( $prefix, $charset_collate );

        dbDelta( $orders );
        dbDelta( $shipments );
        dbDelta( $carriers );
        dbDelta( $packages );
        dbDelta( $services );

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    /** Optional: call this if you want to auto-upgrade on version bump (not required by request) */
    public static function maybe_upgrade(): void {
        $installed = get_option( self::VERSION_OPTION );
        if ( $installed !== self::DB_VERSION ) {
            self::install();
        }
    }

    private static function sql_orders( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_shipstation_orders';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            shp_order_id bigint(20) unsigned NOT NULL,
            shp_order_number varchar(100) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            order_status varchar(50) NULL,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            shipping_total decimal(12,2) NOT NULL DEFAULT 0.00,
            carrier_code varchar(100) NULL,
            service_code varchar(100) NULL,
            package_code varchar(100) NULL,
            created_at datetime NULL,
            updated_at datetime NULL,
            paid_at datetime NULL,
            ship_date datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_shp_order_id (shp_order_id),
            KEY idx_shp_order_number (shp_order_number),
            KEY idx_entry_id (entry_id),
            KEY idx_order_status (order_status),
            KEY idx_carrier_service (carrier_code,service_code)
        ) {$collate};";
    }

    private static function sql_shipments( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_shipstation_shipments';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            shipment_id bigint(20) unsigned NULL,
            shp_order_id bigint(20) unsigned NOT NULL,
            shp_order_number varchar(100) NOT NULL,
            entry_id bigint(20) unsigned NULL,
            shipment_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            insurance_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            tracking_number varchar(100) NULL,
            carrier_code varchar(100) NULL,
            service_code varchar(100) NULL,
            package_code varchar(100) NULL,
            is_voided tinyint(1) NOT NULL DEFAULT 0,
            voided_at datetime NULL,
            ship_to longtext NULL,
            weight longtext NULL,
            dimensions longtext NULL,
            created_at datetime NULL,
            updated_at datetime NULL,
            shipped_at datetime NULL,
            PRIMARY KEY  (id),
            KEY idx_shp_order_id (shp_order_id),
            KEY idx_shp_order_number (shp_order_number),
            KEY idx_shipment_id (shipment_id),
            UNIQUE KEY uniq_tracking (shipment_id),
        ) {$collate};";
    }    

    private static function sql_carriers( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_shipstation_carriers';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            code varchar(100) NOT NULL,
            account_number varchar(100) NULL,
            balance decimal(12,2) NOT NULL DEFAULT 0.00,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            is_req_funded tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_code (code),
            KEY idx_is_primary (is_primary)
        ) {$collate};";
    }

    private static function sql_packages( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_shipstation_packages';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            carrier_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            is_domestic tinyint(1) NOT NULL DEFAULT 1,
            is_international tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_carrier_code (carrier_code,code),
            KEY idx_domestic (is_domestic),
            KEY idx_international (is_international)
        ) {$collate};";
    }

    private static function sql_services( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_shipstation_services';
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            carrier_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            is_domestic tinyint(1) NOT NULL DEFAULT 1,
            is_international tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_carrier_service (carrier_code,code),
            KEY idx_domestic (is_domestic),
            KEY idx_international (is_international)
        ) {$collate};";
    }
}

/* -------------------------------------------------------------------------
 * Wiring tips
 * -------------------------------------------------------------------------
 * 1) Require this file from your main plugin file.
 * 2) Register the activation hook so dbDelta runs on each activation.
 *
 * Example (in your main plugin bootstrap):
 *
 * require_once __DIR__ . '/includes/ShipstationMigrations.php';
 * register_activation_hook( __FILE__, [ 'ShipstationMigrations', 'install' ] );
 *
 * // Optional: auto-upgrade on version bump without reactivation
 * add_action( 'plugins_loaded', [ 'ShipstationMigrations', 'maybe_upgrade' ] );
 */
