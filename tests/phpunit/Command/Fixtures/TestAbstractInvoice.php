<?php

namespace Lle\FacturXBundle\Tests\Command\Fixtures;

use Lle\FacturXBundle\Invoice\ElectronicInvoiceInterface;
use Lle\FacturXBundle\Invoice\ElectronicInvoiceTrait;

abstract class TestAbstractInvoice implements ElectronicInvoiceInterface
{
    use ElectronicInvoiceTrait;
}
