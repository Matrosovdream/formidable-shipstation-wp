<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmShipstationOrdersCron {
    public const HOOK = 'frm_shipstation_update_orders';

    /** Register schedule + callback. Safe to call multiple times. */
    public static function init(): void {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_five_min_schedule' ] );
        add_action( self::HOOK, [ __CLASS__, 'run_update_orders' ] );

        // Ensure an event is queued (in case activation hook was missed on deploy)
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'five_minutes', self::HOOK );
        }
    }

    /** Add a 5-minute recurrence to WP-Cron */
    public static function add_five_min_schedule( array $schedules ): array {
        if ( ! isset( $schedules['five_minutes'] ) ) {
            $schedules['five_minutes'] = [
                'interval' => 300, // 5 minutes
                'display'  => __( 'Every 5 Minutes', 'shipstation-wp' ),
            ];
        }
        return $schedules;
    }

    /** The job handler — calls your model's updateOrdersApi() */
    public static function run_update_orders(): void {
        if ( ! class_exists( 'FrmShipstationOrderHelper' ) ) { return; }
        try {
            $model = new FrmShipstationOrderHelper();
            $res   = $model->updateOrdersApi();
            if ( is_wp_error( $res ) ) {
                error_log( '[ShipStation] updateOrdersApi error: ' . $res->get_error_message() );
            }
            update_option( 'shipstation_wp_last_orders_sync', time() );
        } catch ( \Throwable $e ) {
            error_log( '[ShipStation] Cron exception: ' . $e->getMessage() );
        }
    }

    /** Called on plugin activation */
    public static function activate(): void {
        // Make sure our schedule exists early
        add_filter( 'cron_schedules', [ __CLASS__, 'add_five_min_schedule' ] );
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'five_minutes', self::HOOK );
        }
    }

    /** Called on plugin deactivation */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }
}


