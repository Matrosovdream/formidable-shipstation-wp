<?php
class FrmShipstationInit {

    public function __construct() {

        // Admin classes
        require_once FRM_SHP_BASE_URL.'/classes/admin/FrmShipstationAdminSettings.php';

        // API class
        $this->include_api();

        // Endpoints
        require_once FRM_SHP_BASE_URL.'/classes/endpoints/FrmShipstationRoutes.php';

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Utilities
        $this->include_utils();

        // Helpers
        $this->include_helpers();

        // CRON
        $this->include_cron();

        // Hooks
        $this->include_hooks();

        /*
        // Shortcodes
        $this->include_shortcodes();
        */

    }

    private function include_migrations() {

        // Entries cleaner extra tables
        require_once FRM_SHP_BASE_URL.'/classes//migrations/FrmShipstationMigrations.php';

        // Run migrations
        FrmShipstationMigrations::maybe_upgrade();

    }

    private function include_api() {

        // Abstract API
        require_once FRM_SHP_BASE_URL.'/classes/api/FrmShipstationAbstractApi.php';

        // Order API
        require_once FRM_SHP_BASE_URL.'/classes/api/FrmShipstationOrderApi.php';

        // Shipment API
        require_once FRM_SHP_BASE_URL.'/classes/api/FrmShipstationShipmentApi.php';

        // Carrier API
        require_once FRM_SHP_BASE_URL.'/classes/api/FrmShipstationCarrierApi.php';

    }

    private function include_models() {

        // Abstract model
        require_once FRM_SHP_BASE_URL.'/classes/models/FrmShipstationAbstractModel.php';

        // Order model
        require_once FRM_SHP_BASE_URL.'/classes/models/FrmShipstationOrderModel.php';

        // Shipment model
        require_once FRM_SHP_BASE_URL.'/classes/models/FrmShipstationShipmentModel.php';

        // Carrier model
        require_once FRM_SHP_BASE_URL.'/classes/models/FrmShipstationCarrierModel.php';

        // Package model
        require_once FRM_SHP_BASE_URL.'/classes/models/FrmShipstationPackageModel.php';

        // Service model
        require_once FRM_SHP_BASE_URL.'/classes/models/FrmShipstationServiceModel.php';

    }

    private function include_utils() {

        // Model Entry
        require_once FRM_SHP_BASE_URL.'/classes/utils/FrmShipstationModelEntry.php';

    }

    private function include_helpers() {

        // Order Helper
        require_once FRM_SHP_BASE_URL.'/classes/helpers/FrmShipstationOrderHelper.php';

        // Carrier Helper
        require_once FRM_SHP_BASE_URL.'/classes/helpers/FrmShipstationCarrierHelper.php';

        // Shipment Helper
        require_once FRM_SHP_BASE_URL.'/classes/helpers/FrmShipstationShipmentHelper.php';

    }

    private function include_cron() {

        // Orders cron
        require_once FRM_SHP_BASE_URL.'/classes/cron/FrmShipstationOrdersCron.php';
        FrmShipstationOrdersCron::init();

        // Carriers cron
        require_once FRM_SHP_BASE_URL.'/classes/cron/FrmShipstationCarriersCron.php';
        FrmShipstationCarriersCron::init();

        // Shipments cron
        require_once FRM_SHP_BASE_URL.'/classes/cron/FrmShipstationShipmentsCron.php';
        FrmShipstationShipmentsCron::init();

    }

    private function include_shortcodes() {

        // Refund
        require_once FRM_SHP_BASE_URL.'/shortcodes/payment.refund.php';


    }

    private function include_hooks() {
        
        // Void shipment ajax
        require_once FRM_SHP_BASE_URL.'/actions//user/void-shipment.php';

    }

}

new FrmShipstationInit();