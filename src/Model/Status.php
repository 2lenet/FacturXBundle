<?php

namespace Lle\FacturXBundle\Model;

/** One step of the life of an invoice on an approved platform. */
final readonly class Status
{
    /**
     * @param string $platformCode code given by the platform, kept when the bundle does not know it
     * @param string $recipient who the step concerns (for Iopole: OPERATOR, PPF...)
     * @param string[] $reasons reasons of a rejection (rule: message)
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public StatusCode $code,
        public string $platformCode,
        public string $recipient = '',
        public array $reasons = [],
    ) {
    }

    /** Common code, or the code of the platform when the bundle does not know it. */
    public function getLabel(): string
    {
        return $this->code === StatusCode::UNKNOWN ? $this->platformCode : $this->code->value;
    }
}
