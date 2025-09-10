<?php

class FrmShipstationShipmentApi extends FrmShipstationAbstractApi {

    public function getShipments( array $params = [], bool $autoPaginate = true ) {

        $allowedParams = [
            'orderId','orderNumber','recipientName','trackingNumber',
            'carrierCode','serviceCode','batchId',
            'shipDateStart','shipDateEnd',
            'createDateStart','createDateEnd',
            'voidDateStart','voidDateEnd'
        ];

        return $this->getListing(
            $params,
            $allowedParams,
            '/shipments',
            'shipments',
            $autoPaginate
        );
    }

    /**
     * Get shipments by order
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
     * Get labels by order (derived from shipments)
    */
    public function getLabelsByOrder( array $args, bool $includeData = false ): array|WP_Error {
        $shipments = $this->getShipmentsByOrder( $args );
        if ( is_wp_error( $shipments ) ) {
            return $shipments;
        }
        return $this->labelsFromShipments( is_array( $shipments ) ? $shipments : [], $includeData );
    }

    /**
     * Void label
    */
    public function voidLabel( $shipmentId ) {
        if ( empty( $shipmentId ) ) {
            return new WP_Error( 'shipstation_labelid_required', __( 'label_id is required.', 'shipstation-wp' ), [ 'status' => 400 ] );
        }
        return $this->request( 'POST', '/shipments/voidlabel', [], [ 'shipmentId' => (int) $shipmentId ] );
    }

    // -------------------------
    // Helpers
    // -------------------------

    /**
     * Map shipments[] -> compact labels[]
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


}