<?php
class FrmShipstationInit {

    public function __construct() {

        // Admin classes
        require_once FRM_SHP_BASE_URL.'/classes/admin/FrmShipstationAdminSettings.php';

        // API class
        require_once FRM_SHP_BASE_URL.'/classes/api/FrmShipstationApi.php';

        // Endpoints
        require_once FRM_SHP_BASE_URL.'/classes/endpoints/FrmShipstationRoutes.php';

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Utilities
        $this->include_utils();

        // CRON
        $this->include_cron();

        /*
        // CRON
        require_once FRM_SHP_BASE_URL.'/classes/cron/schedules.cron.php';

        // Shortcodes
        $this->include_shortcodes();

        // Hooks
        $this->include_hooks();
        */

    }

    private function include_migrations() {

        // Entries cleaner extra tables
        require_once FRM_SHP_BASE_URL.'/classes//migrations/FrmShipstationMigrations.php';

        // Run migrations
        FrmShipstationMigrations::maybe_upgrade();

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

    private function include_cron() {

        // Orders cron
        require_once FRM_SHP_BASE_URL.'/classes/cron/FrmShipstationOrdersCron.php';
        FrmShipstationOrdersCron::init();

        // Carriers cron
        require_once FRM_SHP_BASE_URL.'/classes/cron/FrmShipstationCarriersCron.php';
        FrmShipstationCarriersCron::init();

    }

    private function include_shortcodes() {

        // Refund
        require_once FRM_SHP_BASE_URL.'/shortcodes/payment.refund.php';


    }

    private function include_hooks() {

        // Formidable forms processing
        require_once DOTFILER_BASE_URL.'/actions/formidable.php';

        // Ajax
        require_once DOTFILER_BASE_URL.'/actions/ajax.php';
        require_once DOTFILER_BASE_URL.'/actions/ajax/phone.validate.php';

        // Page CSS/JS scripts
        require_once DOTFILER_BASE_URL.'/actions/page.php';

    }

}

new FrmShipstationInit();