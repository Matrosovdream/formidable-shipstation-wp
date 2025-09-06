<?php

final class FrmShipstationAdminSettings {
    private const OPTION_NAME = 'frm_shipstation';

    /** @var array Cached settings */
    private array $settings = [];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_quick_link' ] );
    }

    /**
     * Top-level menu: ShipStation, Submenu: Settings
     */
    public function register_menus(): void {
        $cap = 'manage_options';

        // Top-level page (will render the same settings screen for simplicity)
        add_menu_page(
            __( 'ShipStation', 'frm-shipstation' ),
            __( 'ShipStation', 'frm-shipstation' ),
            $cap,
            'frm-shipstation',
            [ $this, 'render_settings_page' ],
            'dashicons-admin-site',
            56
        );

        // Explicit Settings subpage
        add_submenu_page(
            'frm-shipstation',
            __( 'Settings', 'frm-shipstation' ),
            __( 'Settings', 'frm-shipstation' ),
            $cap,
            'frm-shipstation-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings, sections, and fields using the Settings API
     */
    public function register_settings(): void {
        register_setting( 'frm_shipstation', self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'show_in_rest'      => false,
            'default'           => $this->defaults(),
        ] );

        // Section: API
        add_settings_section(
            'frm_shipstation_api',
            __( 'API Credentials', 'frm-shipstation' ),
            function() {
                echo '<p>' . esc_html__( 'Enter your ShipStation API credentials. You can lock these with wp-config constants.', 'frm-shipstation' ) . '</p>';
            },
            'frm_shipstation'
        );

        add_settings_field( 'api_key', __( 'API Key', 'frm-shipstation' ), [ $this, 'field_text' ], 'frm_shipstation', 'frm_shipstation_api', [ 'key' => 'api_key', 'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' ] );
        add_settings_field( 'api_secret', __( 'API Secret', 'frm-shipstation' ), [ $this, 'field_password' ], 'frm_shipstation', 'frm_shipstation_api', [ 'key' => 'api_secret', 'placeholder' => '••••••••••••••••••••••••••••••••' ] );
        add_settings_field( 'api_base', __( 'API Base URL', 'frm-shipstation' ), [ $this, 'field_text' ], 'frm_shipstation', 'frm_shipstation_api', [ 'key' => 'api_base', 'placeholder' => 'https://ssapi.shipstation.com' ] );
        add_settings_field( 'store_id', __( 'Default Store ID', 'frm-shipstation' ), [ $this, 'field_text' ], 'frm_shipstation', 'frm_shipstation_api', [ 'key' => 'store_id', 'placeholder' => 'Optional' ] );

        // Section: Shipping Defaults
        add_settings_section(
            'frm_shipstation_defaults',
            __( 'Shipping Defaults', 'frm-shipstation' ),
            function() {
                echo '<p>' . esc_html__( 'Defaults used when creating labels (you can override per order).', 'frm-shipstation' ) . '</p>';
            },
            'frm_shipstation'
        );

        add_settings_field( 'carrier_code', __( 'Default Carrier Code', 'frm-shipstation' ), [ $this, 'field_text' ], 'frm_shipstation', 'frm_shipstation_defaults', [ 'key' => 'carrier_code', 'placeholder' => 'e.g. stamps_com, ups, fedex' ] );
        add_settings_field( 'service_code', __( 'Default Service Code', 'frm-shipstation' ), [ $this, 'field_text' ], 'frm_shipstation', 'frm_shipstation_defaults', [ 'key' => 'service_code', 'placeholder' => 'e.g. usps_priority_mail' ] );
        add_settings_field( 'confirmation', __( 'Delivery Confirmation', 'frm-shipstation' ), [ $this, 'field_select' ], 'frm_shipstation', 'frm_shipstation_defaults', [ 'key' => 'confirmation', 'choices' => [
            'none'             => __( 'None', 'frm-shipstation' ),
            'delivery'         => __( 'Delivery', 'frm-shipstation' ),
            'signature'        => __( 'Signature', 'frm-shipstation' ),
            'adult_signature'  => __( 'Adult Signature', 'frm-shipstation' ),
        ] ] );
        add_settings_field( 'insurance', __( 'Enable Insurance by Default', 'frm-shipstation' ), [ $this, 'field_checkbox' ], 'frm_shipstation', 'frm_shipstation_defaults', [ 'key' => 'insurance' ] );
        add_settings_field( 'insurance_amount', __( 'Default Insurance Amount (USD)', 'frm-shipstation' ), [ $this, 'field_number' ], 'frm_shipstation', 'frm_shipstation_defaults', [ 'key' => 'insurance_amount', 'min' => 0, 'step' => '0.01', 'placeholder' => '0.00' ] );

        // Section: Advanced
        add_settings_section(
            'frm_shipstation_advanced',
            __( 'Advanced', 'frm-shipstation' ),
            function() {
                echo '<p>' . esc_html__( 'Diagnostics, webhooks, and developer options.', 'frm-shipstation' ) . '</p>';
            },
            'frm_shipstation'
        );

        add_settings_field( 'webhook_url', __( 'Webhook Callback URL', 'frm-shipstation' ), [ $this, 'field_webhook' ], 'frm_shipstation', 'frm_shipstation_advanced', [ 'key' => 'webhook_url' ] );
        add_settings_field( 'logging', __( 'Enable Logging', 'frm-shipstation' ), [ $this, 'field_checkbox' ], 'frm_shipstation', 'frm_shipstation_advanced', [ 'key' => 'logging' ] );
    }

    /**
     * Default settings
     */
    private function defaults(): array {
        return [
            'api_key'          => '',
            'api_secret'       => '',
            'api_base'         => 'https://ssapi.shipstation.com',
            'store_id'         => '',
            'carrier_code'     => '',
            'service_code'     => '',
            'confirmation'     => 'none',
            'insurance'        => false,
            'insurance_amount' => '0',
            'logging'          => false,
        ];
    }

    /**
     * Settings page UI
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->settings = $this->get_settings();
        ?>
        <div class="wrap frm-shipstation-settings">
            <h1 style="display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-admin-site" style="font-size:28px;line-height:1;"></span>
                <?php esc_html_e( 'ShipStation — Settings', 'frm-shipstation' ); ?>
            </h1>

            <style>
                .frm-shipstation-settings .card {background:#fff;border:1px solid #dcdcdc;border-radius:10px;padding:18px;margin:18px 0; max-width: 100%;}
                .frm-shipstation-settings .muted {color:#666;margin-top:6px;}
                .frm-shipstation-settings code {font-size:12px;background:#f6f7f7;border:1px solid #e0e0e0;padding:2px 6px;border-radius:4px;}
                .frm-shipstation-settings .lock {display:inline-flex;gap:6px;align-items:center;color:#2d6cdf;}
                .frm-shipstation-grid {display:grid;grid-template-columns:1fr 1fr;gap:18px;}
                @media (max-width: 1024px){.frm-shipstation-grid{grid-template-columns:1fr;}}
            </style>

            <form method="post" action="options.php">
                <?php settings_fields( 'frm_shipstation' ); ?>

                <div class="frm-shipstation-grid">
                    <div>
                        <div class="card">
                            <?php do_settings_sections( 'frm_shipstation' ); // All sections render here; we style by order ?>
                        </div>
                    </div>
                    <div>
                        <div class="card">
                            <h2><?php esc_html_e( 'Save Changes', 'frm-shipstation' ); ?></h2>
                            <p class="muted"><?php esc_html_e( 'Be sure to store keys in wp-config.php for production. Fields will show as locked when constants are defined.', 'frm-shipstation' ); ?></p>
                            <?php submit_button( __( 'Save Settings', 'frm-shipstation' ) ); ?>
                            <hr/>
                            <h3><?php esc_html_e( 'Webhook', 'frm-shipstation' ); ?></h3>
                            <?php $this->field_webhook(); ?>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <style>

        </style>

        <?php
    }

    /**
     * Retrieve current settings merged with defaults and wp-config constants
     */
    private function get_settings(): array {
        $opts = wp_parse_args( get_option( self::OPTION_NAME, [] ), $this->defaults() );

        // Apply wp-config constants if defined (lock fields)
        if ( defined( 'SHIPSTATION_API_KEY' ) ) {
            $opts['api_key'] = (string) constant( 'SHIPSTATION_API_KEY' );
        }
        if ( defined( 'SHIPSTATION_API_SECRET' ) ) {
            $opts['api_secret'] = (string) constant( 'SHIPSTATION_API_SECRET' );
        }
        if ( defined( 'SHIPSTATION_API_BASE' ) ) {
            $opts['api_base'] = (string) constant( 'SHIPSTATION_API_BASE' );
        }

        return $opts;
    }

    /**
     * Sanitize callback for all settings.
     */
    public function sanitize_settings( array $input ): array {
        $output = $this->get_settings(); // start from existing + constants

        $map_text = [ 'api_key', 'api_secret', 'store_id', 'carrier_code', 'service_code' ];
        foreach ( $map_text as $key ) {
            if ( isset( $input[ $key ] ) && ! $this->is_locked( $key ) ) {
                $output[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
            }
        }

        if ( isset( $input['api_base'] ) && ! $this->is_locked( 'api_base' ) ) {
            $output['api_base'] = esc_url_raw( trim( (string) $input['api_base'] ) );
            if ( empty( $output['api_base'] ) ) {
                $output['api_base'] = 'https://ssapi.shipstation.com';
            }
        }

        $output['confirmation']     = isset( $input['confirmation'] ) && in_array( $input['confirmation'], [ 'none', 'delivery', 'signature', 'adult_signature' ], true )
            ? $input['confirmation'] : 'none';
        $output['insurance']        = ! empty( $input['insurance'] );
        $output['logging']          = ! empty( $input['logging'] );
        $output['insurance_amount'] = isset( $input['insurance_amount'] ) ? number_format( (float) $input['insurance_amount'], 2, '.', '' ) : '0.00';

        return $output;
    }

    /**
     * Field: text
     */
    public function field_text( array $args = [] ): void {
        $key   = $args['key'] ?? '';
        $val   = $this->get_settings()[ $key ] ?? '';
        $ph    = $args['placeholder'] ?? '';
        $locked= $this->is_locked( $key );
        printf(
            '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s" %5$s />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $key ),
            esc_attr( $val ),
            esc_attr( $ph ),
            $locked ? 'readonly' : ''
        );
        $this->maybe_locked_note( $key );
        if ( $ph ) {
            echo '<p class="description">' . esc_html( $ph ) . '</p>';
        }
    }

    /**
     * Field: password (masked)
     */
    public function field_password( array $args = [] ): void {
        $key    = $args['key'] ?? '';
        $val    = $this->get_settings()[ $key ] ?? '';
        $ph     = $args['placeholder'] ?? '';
        $locked = $this->is_locked( $key );
        $display = $val ? str_repeat( '•', 12 ) : '';

        printf(
            '<input type="password" name="%1$s[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s" %5$s autocomplete="new-password" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $key ),
            esc_attr( $locked ? $display : $val ),
            esc_attr( $ph ),
            $locked ? 'readonly' : ''
        );
        $this->maybe_locked_note( $key );
    }

    /**
     * Field: checkbox
     */
    public function field_checkbox( array $args = [] ): void {
        $key = $args['key'] ?? '';
        $val = ! empty( $this->get_settings()[ $key ] );
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $key ),
            checked( $val, true, false ),
            esc_html__( 'Enabled', 'frm-shipstation' )
        );
    }

    /**
     * Field: number
     */
    public function field_number( array $args = [] ): void {
        $key  = $args['key'] ?? '';
        $val  = $this->get_settings()[ $key ] ?? '';
        $min  = isset( $args['min'] ) ? (float) $args['min'] : 0;
        $step = $args['step'] ?? '1';
        $ph   = $args['placeholder'] ?? '';
        printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$s" min="%4$s" step="%5$s" placeholder="%6$s" class="small-text" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $key ),
            esc_attr( $val ),
            esc_attr( $min ),
            esc_attr( $step ),
            esc_attr( $ph )
        );
    }

    /**
     * Field: select
     */
    public function field_select( array $args = [] ): void {
        $key     = $args['key'] ?? '';
        $choices = $args['choices'] ?? [];
        $val     = $this->get_settings()[ $key ] ?? '';
        echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
        foreach ( $choices as $k => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $k ), selected( (string) $val, (string) $k, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    /**
     * Field: webhook URL (read-only display)
     */
    public function field_webhook(): void {
        $url = esc_url( rest_url( 'shipstation/v1/webhook' ) );
        echo '<code>' . $url . '</code>';
        echo '<p class="description">' . esc_html__( 'Copy this into your ShipStation account for webhooks (e.g., label created, shipped, delivered).', 'frm-shipstation' ) . '</p>';
    }

    /**
     * Show a lock note when a setting is provided via wp-config constant
     */
    private function maybe_locked_note( string $key ): void {
        $map = [
            'api_key'   => 'SHIPSTATION_API_KEY',
            'api_secret'=> 'SHIPSTATION_API_SECRET',
            'api_base'  => 'SHIPSTATION_API_BASE',
        ];
        if ( isset( $map[ $key ] ) && defined( $map[ $key ] ) ) {
            printf( ' <span class="lock">🔒 %s <code>%s</code></span>', esc_html__( 'Locked by', 'frm-shipstation' ), esc_html( $map[ $key ] ) );
        }
    }

    /**
     * Whether a field is locked by a constant
     */
    private function is_locked( string $key ): bool {
        return (
            ( 'api_key'   === $key && defined( 'SHIPSTATION_API_KEY' ) ) ||
            ( 'api_secret'=== $key && defined( 'SHIPSTATION_API_SECRET' ) ) ||
            ( 'api_base'  === $key && defined( 'SHIPSTATION_API_BASE' ) )
        );
    }

    /**
     * Add a quick “Settings” link under the plugin on the Plugins screen
     */
    public function plugin_quick_link( array $links ): array {
        $url = admin_url( 'admin.php?page=frm-shipstation-settings' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'frm-shipstation' ) . '</a>';
        return $links;
    }
}

// Bootstrap
add_action( 'plugins_loaded', static function () {
    new FrmShipstationAdminSettings();
} );