<?php

namespace Lle\FacturXBundle\Invoice;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Follow-up of the sending to the approved platform (ElectronicInvoiceInterface), for a Doctrine entity:
 * columns platform_id, platform_sent_at, platform_status, platform_reasons (create the migration in the project).
 */
trait ElectronicInvoiceTrait
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $platformId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $platformSentAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $platformStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $platformReasons = null;

    public function getPlatformId(): ?string
    {
        return $this->platformId;
    }

    public function setPlatformId(?string $platformId): static
    {
        $this->platformId = $platformId;

        return $this;
    }

    public function getPlatformSentAt(): ?\DateTimeInterface
    {
        return $this->platformSentAt;
    }

    public function setPlatformSentAt(?\DateTimeInterface $platformSentAt): static
    {
        $this->platformSentAt = $platformSentAt;

        return $this;
    }

    public function getPlatformStatus(): ?string
    {
        return $this->platformStatus;
    }

    public function setPlatformStatus(?string $platformStatus): static
    {
        $this->platformStatus = $platformStatus;

        return $this;
    }

    public function getPlatformReasons(): ?string
    {
        return $this->platformReasons;
    }

    public function setPlatformReasons(?string $platformReasons): static
    {
        $this->platformReasons = $platformReasons;

        return $this;
    }
}
