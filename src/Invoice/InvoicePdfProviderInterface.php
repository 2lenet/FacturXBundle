<?php

namespace Lle\FacturXBundle\Invoice;

/** Implemented by the project: makes the Factur-X PDF of one of its invoices (FacturXPdfConverter, or your own tools). */
interface InvoicePdfProviderInterface
{
    /** Path of a temporary Factur-X PDF file, deleted by InvoiceSender once sent. */
    public function getPdf(ElectronicInvoiceInterface $invoice): string;
}
