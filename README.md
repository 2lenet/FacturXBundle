# FacturXBundle

French electronic invoicing for Symfony. With the reform, every company must be able to receive electronic invoices
since September 2026, and must issue them from September 2026 (large companies and mid-caps) or September 2027 (SMEs
and micro-companies). The bundle provides:

- the **Factur-X XML** of an invoice, EN 16931 profile, from a Twig template;
- the **Factur-X PDF/A-3** (the XML attached to the visual PDF), when
  [2lenet/pdf-generator-bundle](https://github.com/2lenet/PdfGeneratorBundle) is installed (optional);
- the **seller, billing framework and mandatory notes** in the config screen of
  [2lenet/config-bundle](https://github.com/2lenet/ConfigBundle) (optional);
- the **sending to an approved platform** (plateforme agréée) and the follow-up of the invoice status. Iopole is
  built in; any other platform can be plugged in.

## Installation

```bash
composer require 2lenet/facturx-bundle
```

```yaml
# config/packages/lle_facturx.yaml
lle_facturx:
    platform: iopole                # approved platform, empty: no sending
    config_screen: true             # needs 2lenet/config-bundle
```

The credentials of the platform are read **from the environment only** (generic names, whatever the platform):

```dotenv
FACTURX_PLATFORM_AUTH_URL=        # token URL given by the platform
FACTURX_PLATFORM_API_URL=         # API URL given by the platform
FACTURX_PLATFORM_CLIENT_ID=
FACTURX_PLATFORM_CLIENT_SECRET=
FACTURX_PLATFORM_ACCOUNT_ID=      # account on the platform (Iopole: customer-id)
```

- Put the real credentials in `.env.local`, in the variables of the server, or in the Symfony secrets vault
  (`bin/console secrets:set FACTURX_PLATFORM_CLIENT_SECRET`), never in `.env` nor in a committed yaml file.
  There is no option to write them in a yaml file.
- They never go through the config screen: it only holds the seller, the billing framework and the notes.
- For tests, the Iopole sandbox is `https://auth.preprod.iopole.fr/realms/iopole/protocol/openid-connect/token` and
  `https://api.ppd.iopole.fr`.

## Make a Factur-X invoice

```php
$invoice = array_merge($facturXConfig->getInvoiceData(), [   // seller, business_process, notes
    'number' => 'F2026-0042',
    'issue_date' => new \DateTimeImmutable(),
    'buyer' => [...],
    'lines' => [...],
    'taxes' => [...],
    'totals' => [...],
]);
$xml = $twig->render('@LleFacturX/en16931.xml.twig', ['invoice' => $invoice]);
$pdfPath = $facturXPdfConverter->convertToZugferdPdf($visualPdfPath, $xml, ['title' => 'F2026-0042', 'creator' => 'My company']);
```

- The project fills the `invoice` array from its own entities and computes the totals itself (see
  [Keys of `invoice`](#keys-of-invoice) and [Totals](#totals)).
- `FacturXConfig` exists only with `config_screen: true` and 2lenet/config-bundle installed. Otherwise, give `seller`,
  `business_process` and `notes` yourself.
- `FacturXPdfConverter` exists only with 2lenet/pdf-generator-bundle installed (see [PDF/A-3](#pdfa-3)).
- Override the template the Symfony way, in `templates/bundles/LleFacturXBundle/en16931.xml.twig`.

## PDF/A-3

The platforms expect a PDF/A-3 with the XML attached under the name `factur-x.xml` and the XMP metadata Factur-X
`1.0` / `EN 16931`.

- With 2lenet/pdf-generator-bundle installed, `Lle\FacturXBundle\Pdf\FacturXPdfConverter` does it (service declared
  automatically): `convertToZugferdPdf($visualPdfPath, $xml, $metadata)` returns the path of the Factur-X PDF.
- Without it, make the PDF/A-3 with your own tools, from the XML of the template.

## Keys of `invoice`

`?` marks an optional key: its element is left out of the XML when the value is empty.

| Key | |
|---|---|
| `number` | Invoice number (BT-1) |
| `type_code`? | `380` invoice (default), `381` credit note |
| `issue_date` | `DateTimeInterface` or date string |
| `due_date`? | Payment due date |
| `delivery_date`? | Delivery date, the issue date by default |
| `currency`? | `EUR` by default |
| `business_process`? | French billing framework (BT-23, required by BR-FR-08): `B1` goods, `S1` services, `M1` mixed |
| `profile`? | `urn:cen.eu:en16931:2017` by default |
| `buyer_reference`? | Buyer reference (BT-10) |
| `notes[]`? | `content`, `subject_code`? (`PMT` recovery fee, `PMD` late penalties, `AAB` discount: required in France, BR-FR-05) |
| `seller`, `buyer` | See below |
| `lines[]` | `label`, `quantity`, `unit_price_excl_tax`, `vat_category`, `vat_rate`, `total_excl_tax`, `unit_code`? (`C62` by default) |
| `taxes[]` | One entry per VAT category and rate: `vat_category`, `vat_rate`, `basis_amount`, `tax_amount`, `exemption_reason`? (required for the category `E`) |
| `totals` | `total_excl_tax`, `total_tax`, `total_incl_tax`, `tax_basis_total`? (`total_excl_tax` by default), `due_amount`? (`total_incl_tax` by default) |

`seller` and `buyer`:

| Key | |
|---|---|
| `name` | Company name |
| `siren`? | SIREN (written with the scheme `0002`) |
| `vat_number`? | Intra-community VAT number |
| `electronic_address`? | Electronic address of the directory, used to route the invoice (usually the SIREN) |
| `electronic_address_scheme`? | `0225` by default |
| `address` | `line1`, `line2`?, `postcode`, `city`, `country_code`? (`FR` by default) |

VAT categories: `S` standard rate, `Z` zero rate, `E` exempt, `AE` reverse charge, `K` intra-community, `G` export.

## Totals

The platforms check the sums. Compute them as EN 16931 does:

- line total = quantity × unit price, rounded to 2 decimals;
- for each VAT category and rate: basis = sum of the line totals, VAT = basis × rate, **rounded once per rate** (not per line);
- `total_excl_tax` = sum of the bases, `total_tax` = sum of the VAT amounts, `total_incl_tax` = `total_excl_tax` + `total_tax`.

## Seller, billing framework and notes in the config screen

With `config_screen: true` and 2lenet/config-bundle installed, the seller, the billing framework and the notes are
kept in the config screen (group `facturx`), so they can be changed without a deployment.

- `bin/console lle:config:warmup` creates the lines, empty (`FacturXConfigWarmup`): fill them before any sending.
  A value already set is never overwritten.
- `FacturXConfig::getInvoiceData()` returns `business_process`, `notes` and `seller`, to merge in `invoice`.

Keys: `seller_name`, `seller_address1`, `seller_address2`, `seller_postcode`, `seller_city`, `seller_country` (`FR` when
empty), `seller_siren`, `seller_vat_number`, `seller_electronic_address`, `business_process`, and the notes (text)
`note_pmt`, `note_pmd`, `note_aab`. An empty note is left out.

## Send and follow the invoices

Needs a platform (`lle_facturx.platform`).

1. Your invoice entity implements `ElectronicInvoiceInterface` with `ElectronicInvoiceTrait` (columns `platform_id`,
   `platform_sent_at`, `platform_status`, `platform_reasons`: create the migration).
2. A service of yours implements `InvoicePdfProviderInterface` (`getPdf($invoice)`: path of a **temporary** Factur-X PDF,
   deleted once sent: never return a file you keep). When
   there is only one, it is used automatically; otherwise alias the interface.
3. Send and refresh with `InvoiceSender`:

```php
if (!$invoiceSender->isSent($invoice)) {      // sent and not rejected: cannot be sent again
    $invoiceSender->send($invoice);            // id, date and status SUBMITTED kept on the invoice
}
$invoiceSender->refreshStatus($invoice);       // most recent status and reasons of a rejection
```

4. Refresh the statuses with a cron. The command finds by itself the entities using `ElectronicInvoiceTrait`:

```
*/15 * * * * php bin/console lle:facturx:refresh-statuses    # --days=60 by default
```

## Platforms

`PlatformInterface` is the platform chosen with `lle_facturx.platform`:

```php
$id = $platform->send($pdfPath);                 // id of the invoice on the platform
$history = $platform->getStatusHistory($id);    // Status[], the most recent first
$history[0]->code;                               // StatusCode::RECEIVED, REJECTED...
$history[0]->code->isFinal();                    // REJECTED, DUPLICATED, REFUSED, PAID: no more change
$history[0]->reasons;                            // reasons of a rejection
```

The processing is asynchronous: read the statuses later (the cron above).

To try a file by hand:

```bash
bin/console lle:facturx:send invoice.pdf --wait=10
```

### Statuses

`StatusCode` holds:

- the 14 statuses of the reform (AFNOR XP Z12-012, codes 200 to 213): `SUBMITTED` (Déposée), `ISSUED`, `RECEIVED`,
  `MADE_AVAILABLE`, `IN_HAND`, `APPROVED`, `PARTIALLY_APPROVED`, `DISPUTED`, `SUSPENDED`, `COMPLETED`, `REFUSED`,
  `PAYMENT_SENT`, `PAID`, `REJECTED` (Rejetée);
- `DUPLICATED`, given by Iopole when the invoice number was already sent;
- `DELIVERED`, planned but never observed from Iopole;
- `UNKNOWN`, for a code the bundle does not know: the code of the platform is kept in `Status::$platformCode`.

Their labels are translations (domain `LleFacturXBundle`, French and English), shown by the template
`@LleFacturX/field/status.html.twig` (the code itself when unknown):

```php
// Crudit
Field::new('platformStatus')->setTemplate('@LleFacturX/field/status.html.twig');
```

```twig
{# elsewhere #}
{% include '@LleFacturX/field/status.html.twig' with {value: invoice.platformStatus} only %}
```

### What the platforms offer, and what the bundle covers

All approved platforms let you deposit an invoice and follow its life cycle: that is `PlatformInterface`. Some also
offer things not covered yet, to add as optional interfaces when needed:

- webhooks: the platform calls you when a status changes (instead of the cron);
- reception of the supplier invoices;
- the directory (annuaire): check the electronic address of a buyer before sending;
- e-reporting (sales to individuals or abroad).

### Another platform

1. Write a service implementing `Lle\FacturXBundle\Platform\PlatformInterface`:
   - `send()` deposits the file and returns the id of the invoice on the platform;
   - `getStatusHistory()` returns the statuses **most recent first**. Do not rely on the dates alone if the platform
     rounds them: Iopole rounds some to the second, so `IopolePlatform` sorts by its `statusId`, a time-ordered UUID;
   - translate the codes of the platform to `StatusCode`, with `StatusCode::UNKNOWN` and the original code in
     `Status::$platformCode` for the unknown ones.
2. Read its credentials from the `FACTURX_PLATFORM_*` variables when they fit, or from its own environment variables.
3. Give the id of the service:

```yaml
lle_facturx:
    platform: App\EInvoicing\MyPlatform
```
