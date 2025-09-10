<?php

class FrmShipstationCarrierApi extends FrmShipstationAbstractApi {

    /**
     * 1) List of carriers
     * @return array|WP_Error
     */
    public function getCarriers() {
        return $this->request( 'GET', '/carriers' );
    }

    // List services for a carrier
    public function getCarrierServices( array $args ) {
        if ( empty( $args['carrierCode'] ) ) {
            return new WP_Error( 'shipstation_carriercode_required', __( 'carrierCode is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'GET', '/carriers/listservices/?carrierCode=' . urlencode( $args['carrierCode'] ) );
    }

    // List packages for a carrier
    public function getCarrierPackages( array $args ) {
        if ( empty( $args['carrierCode'] ) ) {
            return new WP_Error( 'shipstation_carriercode_required', __( 'carrierCode is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'GET', '/carriers/listpackages/?carrierCode=' . urlencode( $args['carrierCode'] ) );
    }

}    

