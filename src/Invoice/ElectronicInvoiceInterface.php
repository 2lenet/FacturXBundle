<?php

namespace Lle\FacturXBundle\Invoice;

/**
 * Invoice of the project that can be sent to the approved platform: keeps the follow-up of the sending.
 * Implement it with ElectronicInvoiceTrait.
 */
interface ElectronicInvoiceInterface
{
    /** Id of the invoice on the platform, null while not sent. */
    public function getPlatformId(): ?string;

    public function setPlatformId(?string $platformId): static;

    public function getPlatformSentAt(): ?\DateTimeInterface;

    public function setPlatformSentAt(?\DateTimeInterface $platformSentAt): static;

    /** Last status on the platform: StatusCode value, or the code of the platform when unknown. */
    public function getPlatformStatus(): ?string;

    public function setPlatformStatus(?string $platformStatus): static;

    /** Reasons of the rejection, one per line. */
    public function getPlatformReasons(): ?string;

    public function setPlatformReasons(?string $platformReasons): static;
}
