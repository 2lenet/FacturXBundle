<?php

namespace Lle\FacturXBundle\Tests\Command\Fixtures;

use Lle\FacturXBundle\Invoice\ElectronicInvoiceInterface;
use Lle\FacturXBundle\Invoice\ElectronicInvoiceTrait;

class TestInvoice implements ElectronicInvoiceInterface
{
    use ElectronicInvoiceTrait;
}
