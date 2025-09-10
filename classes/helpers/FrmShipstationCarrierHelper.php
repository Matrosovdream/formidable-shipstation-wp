<?php

class FrmShipstationCarrierHelper {

    protected $carrierApi;
    protected $carrierModel;
    protected $serviceModel;
    protected $packageModel;

    public function __construct() {
        $this->carrierApi = new FrmShipstationCarrierApi();
        $this->carrierModel = new FrmShipstationCarrierModel();
        $this->serviceModel = new FrmShipstationServiceModel();
        $this->packageModel = new FrmShipstationPackageModel();
    }

    // Update carriers
    public function updateCarriersApi() {

        // Get carriers from ShipStation API
        $carriers = $this->carrierApi->getCarriers();

        foreach( $carriers as $key=>$carrier ) {

            // Get carrier services
            $services = $this->carrierApi->getCarrierServices(['carrierCode' => $carrier['code'] ?? '']);
            $carrier['services'] = $services;

            // Get carrier packages
            $packages = $this->carrierApi->getCarrierPackages(['carrierCode' => $carrier['code'] ?? '']);
            $carrier['packages'] = $packages;

            $carriers[$key] = $carrier;

        }

        // Prepare carriers for update/create
        $carriersProcessed = [];
        $services = [];
        foreach( $carriers as $carrier ) {

            $carriersProcessed[] = [
                'name' => $carrier['name'] ?? '',
                'code' => $carrier['code'] ?? '',
                'account_number' => $carrier['accountNumber'] ?? '',
                'balance' => $carrier['balance'] ?? 0,
                'is_primary' => $carrier['primary'],
                'is_req_funded' => $carrier['requiresFundedAccount'],
            ];

            // Add services
            if( ! empty($carrier['services']) ) {
                $services = array_merge( $services, $carrier['services'] );
            }

            // Add packages
            if( ! empty($carrier['packages']) ) {
                $packages = array_merge( $packages, $carrier['packages'] ); 
            }

        }

        // Update records
        $res[] = $this->carrierModel->multipleUpdateCreate( $carriersProcessed  );


        // Prepare and update services
        $servicesProcessed = [];
        foreach( $services as $service ) {
            $servicesProcessed[] = [
                'code' => $service['code'] ?? '',
                'carrier_code' => $service['carrierCode'] ?? '',
                'name' => $service['name'] ?? '',
                'is_domestic' => $service['domestic'],
                'is_international' => $service['international'],
            ];
        }
        $res[] = $this->serviceModel->multipleUpdateCreate( $servicesProcessed );


        // Prepare and update packages
        $packagesProcessed = [];
        foreach( $packages as $package ) {
            $packagesProcessed[] = [
                'code' => $package['code'] ?? '',
                'carrier_code' => $package['carrierCode'] ?? '',
                'name' => $package['name'] ?? '',
                'is_domestic' => $package['domestic'],
                'is_international' => $package['international'],
            ];
        }
        $res[] = $this->packageModel->multipleUpdateCreate( $packagesProcessed );

        return $res;

    }
    

}