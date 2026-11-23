<?php

namespace Lle\FacturXBundle\Platform;

use Lle\FacturXBundle\Model\Status;

/**
 * Approved platform (plateforme agréée) the invoices are sent to. Choose it with lle_facturx.platform:
 * "iopole", or the id of your own service implementing this interface.
 */
interface PlatformInterface
{
    /**
     * Sends an invoice file (Factur-X PDF or XML) and returns its id on the platform.
     * The processing is asynchronous: the result comes later as statuses (getStatusHistory()).
     */
    public function send(string $file): string;

    /**
     * Life of the invoice on the platform, the most recent status first.
     *
     * @return Status[]
     */
    public function getStatusHistory(string $id): array;
}
