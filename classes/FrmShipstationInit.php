<?php
class FrmShipstationInit {

    public function __construct() {

        // Admin classes
        require_once FRM_SHP_BASE_URL.'/classes/admin/FrmShipstationAdminSettings.php';

        // API class
        require_once FRM_SHP_BASE_URL.'/classes/api/FrmShipstationApi.php';

        // Endpoints
        require_once FRM_SHP_BASE_URL.'/classes/endpoints/FrmShipstationRoutes.php';

        /*
        // CRON
        require_once FRM_SHP_BASE_URL.'/classes/cron/schedules.cron.php';

        // Migrations
        $this->include_migrations();

        // Shortcodes
        $this->include_shortcodes();

        // Hooks
        $this->include_hooks();
        */

    }

    private function include_migrations() {

        // Entries cleaner extra tables
        require_once FRM_SHP_BASE_URL.'/classes//migrations/archive.entries.php';

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