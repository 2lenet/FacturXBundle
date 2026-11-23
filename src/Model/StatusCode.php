<?php

namespace Lle\FacturXBundle\Model;

/**
 * Steps of the life of an invoice, common to the approved platforms (French e-invoicing reform).
 * The value is the code used by Iopole; another platform translates its own codes to these cases.
 * Labels: translations facturx.status.<value> (domain LleFacturXBundle), displayed by @LleFacturX/field/status.html.twig.
 */
enum StatusCode: string
{
    /** deposited on the platform (200 Déposée) */
    case SUBMITTED = 'SUBMITTED';
    /** issued by the platform of the seller (201 Émise) */
    case ISSUED = 'ISSUED';
    /** received by the platform of the buyer (202 Reçue) */
    case RECEIVED = 'RECEIVED';
    /** made available to the buyer (203 Mise à disposition) */
    case MADE_AVAILABLE = 'MADE_AVAILABLE';
    case DELIVERED = 'DELIVERED';
    /** taken over by the buyer (204 Prise en charge) */
    case IN_HAND = 'IN_HAND';
    case APPROVED = 'APPROVED';
    case PARTIALLY_APPROVED = 'PARTIALLY_APPROVED';
    case DISPUTED = 'DISPUTED';
    case SUSPENDED = 'SUSPENDED';
    case COMPLETED = 'COMPLETED';
    /** refused by the buyer (210 Refusée) */
    case REFUSED = 'REFUSED';
    case PAYMENT_SENT = 'PAYMENT_SENT';
    /** paid (212 Encaissée) */
    case PAID = 'PAID';
    /** rejected by the platform: not conformant (213 Rejetée) */
    case REJECTED = 'REJECTED';
    /** same invoice number already sent */
    case DUPLICATED = 'DUPLICATED';
    /** code not known by the bundle: see Status::$platformCode */
    case UNKNOWN = 'UNKNOWN';

    /** The platform will not change the status of the invoice any more. */
    public function isFinal(): bool
    {
        return in_array($this, [self::REJECTED, self::DUPLICATED, self::REFUSED, self::PAID], true);
    }

    /** @return string[] */
    public static function finalValues(): array
    {
        return array_values(array_map(
            fn (self $code): string => $code->value,
            array_filter(self::cases(), fn (self $code): bool => $code->isFinal())
        ));
    }
}
