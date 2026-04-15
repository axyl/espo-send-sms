<?php

namespace Espo\Custom;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;

class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        // This binding is still active and intentional.
        //
        // The custom /Activities/... API layer delegates non-Sms requests to the
        // core Activities service, but the dependency below is resolved through
        // the container. Binding the core class to our subclass keeps the working
        // Sms-aware query override available anywhere the core service is
        // injected.
        //
        // Do not remove this together with the custom route layer unless the full
        // activity/history SQL generated for Sms + non-Sms unions has been
        // validated end-to-end. Earlier "cleanup" attempts in that direction
        // triggered SQLSTATE[21000] cardinality violations.
        $binder->bindImplementation(
            \Espo\Modules\Crm\Tools\Activities\Service::class,
            \Espo\Custom\Tools\Activities\Service::class
        );
    }
}
