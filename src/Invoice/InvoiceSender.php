<?php

namespace Lle\FacturXBundle\Invoice;

use Doctrine\Persistence\ManagerRegistry;
use Lle\FacturXBundle\Model\StatusCode;
use Lle\FacturXBundle\Platform\PlatformInterface;

/**
 * Sends the invoices of the project to the approved platform and follows their status, kept on the invoice
 * (ElectronicInvoiceInterface). The processing is asynchronous: refresh the status later (lle:facturx:refresh-statuses).
 *
 * send() and refreshStatus() flush the entity manager of the invoice: other pending changes are saved too.
 */
class InvoiceSender
{
    public function __construct(
        protected PlatformInterface $platform,
        protected InvoicePdfProviderInterface $pdfProvider,
        protected ManagerRegistry $doctrine,
    ) {
    }

    /** Sent and not rejected: it cannot be sent again (a rejected invoice can, after correction). */
    public function isSent(ElectronicInvoiceInterface $invoice): bool
    {
        return $invoice->getPlatformId() !== null && $invoice->getPlatformStatus() !== StatusCode::REJECTED->value;
    }

    /**
     * @throws \LogicException   already sent and not rejected
     * @throws \RuntimeException sent but not saved: the message gives the id on the platform
     */
    public function send(ElectronicInvoiceInterface $invoice): void
    {
        if ($this->isSent($invoice)) {
            throw new \LogicException(sprintf(
                'Invoice already sent (platform id %s, status %s)',
                $invoice->getPlatformId(),
                $invoice->getPlatformStatus()
            ));
        }

        $file = $this->pdfProvider->getPdf($invoice);
        try {
            $id = $this->platform->send($file);
        } finally {
            @unlink($file);
        }

        $invoice
            ->setPlatformId($id)
            ->setPlatformSentAt(new \DateTime())
            ->setPlatformStatus(StatusCode::SUBMITTED->value)
            ->setPlatformReasons(null);
        try {
            $this->flush($invoice);
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Invoice sent to the platform (id %s) but not saved: %s', $id, $e->getMessage()), 0, $e);
        }
    }

    /** Keeps the most recent status of the invoice on the platform, with the reasons of a rejection. */
    public function refreshStatus(ElectronicInvoiceInterface $invoice): void
    {
        if ($invoice->getPlatformId() === null) {
            return;
        }
        $last = $this->platform->getStatusHistory($invoice->getPlatformId())[0] ?? null;
        if ($last === null) {
            return;
        }

        $invoice
            ->setPlatformStatus($last->getLabel())
            ->setPlatformReasons($last->reasons !== [] ? implode("\n", $last->reasons) : null);
        $this->flush($invoice);
    }

    protected function flush(ElectronicInvoiceInterface $invoice): void
    {
        $this->doctrine->getManagerForClass($invoice::class)?->flush();
    }
}
