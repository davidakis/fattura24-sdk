<?php

namespace SimplyIT\Fattura24SDK\Xml;

use DOMDocument;
use DOMElement;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\DeliveryData;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Data\PaymentData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;

/**
 * XmlGenerator
 *
 * Converts typed Data objects into the XML format expected by Fattura24 API.
 * Accepts InvoiceData and CustomerData directly — no raw arrays.
 */
class XmlGenerator
{
    private array $validNaturaCodes = [
        'N1', 'N2.1', 'N2.2', 'N3.1', 'N3.2', 'N3.3', 'N3.4', 'N3.5',
        'N3.6', 'N4', 'N5', 'N6.1', 'N6.2', 'N6.3', 'N6.4', 'N6.5',
        'N6.6', 'N6.7', 'N6.8', 'N6.9', 'N7',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate XML for a full invoice / document.
     *
     * @throws ValidationException
     */
    public function fromInvoice(InvoiceData $invoice): string
    {
        $this->validateRequiredFields($invoice);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('Fattura24');
        $dom->appendChild($root);

        $document = $dom->createElement('Document');
        $root->appendChild($document);

        $this->writeDocument($dom, $document, $invoice->document);
        $this->writeCustomer($dom, $document, $invoice->customer);

        if ($invoice->delivery !== null) {
            $this->writeDelivery($dom, $document, $invoice->delivery);
        }

        if (!empty($invoice->payments)) {
            $this->writePayments($dom, $document, $invoice->payments);
        }

        $this->writeRows($dom, $document, $invoice->rows, $invoice->document->documentType->value);

        return $dom->saveXML();
    }

    /**
     * Generate XML for a standalone customer record.
     */
    public function fromCustomer(CustomerData $customer): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('Fattura24');
        $dom->appendChild($root);

        $document = $dom->createElement('Document');
        $root->appendChild($document);

        $this->writeCustomer($dom, $document, $customer);

        return $dom->saveXML();
    }

    /**
     * Check whether a generated XML string contains a DocumentError node.
     */
    public static function hasErrors(string $xml): bool
    {
        $dom = new DOMDocument();
        @$dom->loadXML($xml);

        return $dom->getElementsByTagName('DocumentError')->length > 0;
    }

    /**
     * Extract the error message text from a DocumentError XML.
     */
    public static function getErrorMessage(string $xml): string
    {
        $dom = new DOMDocument();
        @$dom->loadXML($xml);
        $nodes = $dom->getElementsByTagName('ErrorMsg');

        return $nodes->length > 0 ? (string) $nodes->item(0)->nodeValue : '';
    }

    // -------------------------------------------------------------------------
    // Private writers
    // -------------------------------------------------------------------------

    private function writeDocument(DOMDocument $dom, DOMElement $parent, DocumentData $doc): void
    {
        $this->addText($dom, $parent, 'DocumentType', $doc->documentType->value);
        $this->addText($dom, $parent, 'Total', self::formatAmount($doc->total));
        $this->addText($dom, $parent, 'TotalWithoutTax', self::formatAmount($doc->totalWithoutTax));
        $this->addText($dom, $parent, 'VatAmount', self::formatAmount($doc->vatAmount));
        $this->addText($dom, $parent, 'SendEmail', $doc->sendEmail ? 'true' : 'false');
        $this->addText($dom, $parent, 'FePaymentCode', $doc->fePaymentCode);
        $this->addCdata($dom, $parent, 'PaymentMethodName', $doc->paymentMethodName);
        $this->addCdata($dom, $parent, 'PaymentMethodDescription', $doc->paymentMethodDescription);

        // Optional fields
        $this->addTextIfSet($dom, $parent, 'Currency', $doc->currency);
        $this->addTextIfSet($dom, $parent, 'FeDocType', $doc->feDocType);
        $this->addTextIfSet($dom, $parent, 'FeDocParamiter', $doc->feDocParamiter);
        $this->addTextIfSet($dom, $parent, 'FeVirtualStamp', $doc->feVirtualStamp);
        $this->addCdataIfSet($dom, $parent, 'FootNotes', $doc->footNotes);
        $this->addTextIfSet($dom, $parent, 'F24OrderId', $doc->f24OrderId);
        $this->addTextIfSet($dom, $parent, 'IdTemplate', $doc->idTemplate !== null ? (string) $doc->idTemplate : null);
        $this->addTextIfSet($dom, $parent, 'IdNumerator', $doc->idNumerator !== null ? (string) $doc->idNumerator : null);
        $this->addCdataIfSet($dom, $parent, 'Object', $doc->object);
        $this->addTextIfSet($dom, $parent, 'Number', $doc->number);
    }

    private function writeCustomer(DOMDocument $dom, DOMElement $parent, CustomerData $c): void
    {
        $this->addCdata($dom, $parent, 'CustomerName', $c->customerName);
        $this->addCdataIfSet($dom, $parent, 'CustomerAddress', $c->customerAddress);
        $this->addCdataIfSet($dom, $parent, 'CustomerPostcode', $c->customerPostcode);
        $this->addCdataIfSet($dom, $parent, 'CustomerCity', $c->customerCity);
        $this->addCdataIfSet($dom, $parent, 'CustomerProvince', $c->customerProvince);
        $this->addCdataIfSet($dom, $parent, 'CustomerCountry', $c->customerCountry);
        $this->addCdataIfSet($dom, $parent, 'CustomerEmail', $c->customerEmail);
        $this->addCdataIfSet($dom, $parent, 'CustomerCellPhone', $c->customerCellPhone);
        $this->addTextIfSet($dom, $parent, 'CustomerFiscalCode', $c->customerFiscalCode);
        $this->addTextIfSet($dom, $parent, 'CustomerVatCode', $c->customerVatCode);
        $this->addTextIfSet($dom, $parent, 'FeCustomerPec', $c->feCustomerPec);
        $this->addTextIfSet($dom, $parent, 'FeDestinationCode', $c->feDestinationCode);
    }

    private function writeDelivery(DOMDocument $dom, DOMElement $parent, DeliveryData $d): void
    {
        $this->addCdataIfSet($dom, $parent, 'DeliveryName', $d->deliveryName);
        $this->addCdataIfSet($dom, $parent, 'DeliveryAddress', $d->deliveryAddress);
        $this->addCdataIfSet($dom, $parent, 'DeliveryPostcode', $d->deliveryPostcode);
        $this->addCdataIfSet($dom, $parent, 'DeliveryCity', $d->deliveryCity);
        $this->addCdataIfSet($dom, $parent, 'DeliveryProvince', $d->deliveryProvince);
        $this->addCdataIfSet($dom, $parent, 'DeliveryCountry', $d->deliveryCountry);
    }

    /**
     * @param PaymentData[] $payments
     */
    private function writePayments(DOMDocument $dom, DOMElement $parent, array $payments): void
    {
        $paymentsEl = $dom->createElement('Payments');
        $parent->appendChild($paymentsEl);

        foreach ($payments as $p) {
            $paymentEl = $dom->createElement('Payment');
            $paymentsEl->appendChild($paymentEl);

            $this->addText($dom, $paymentEl, 'Date', $p->date);
            $this->addText($dom, $paymentEl, 'Amount', self::formatAmount($p->amount));
            $this->addText($dom, $paymentEl, 'Paid', $p->paid ? 'true' : 'false');
        }
    }

    /**
     * @param RowData[] $rows
     * @throws ValidationException
     */
    private function writeRows(DOMDocument $dom, DOMElement $parent, array $rows, string $documentType): void
    {
        $rowsEl = $dom->createElement('Rows');
        $parent->appendChild($rowsEl);

        foreach ($rows as $index => $row) {
            if ($documentType === 'FE' && $row->vatCode === 0) {
                $this->validateFeVatNature($row, $index);
            }

            $rowEl = $dom->createElement('Row');
            $rowsEl->appendChild($rowEl);

            $this->addCdataIfSet($dom, $rowEl, 'Code', $row->code);
            $this->addCdata($dom, $rowEl, 'Description', $row->description);
            $this->addText($dom, $rowEl, 'Qty', self::formatQty($row->qty));
            $this->addTextIfSet($dom, $rowEl, 'Um', $row->um);
            $this->addText($dom, $rowEl, 'Price', self::formatAmount($row->price));
            $this->addTextIfSet($dom, $rowEl, 'Discounts', $row->discounts !== null ? (string) $row->discounts : null);
            $this->addText($dom, $rowEl, 'VatCode', (string) $row->vatCode);
            $this->addCdataIfSet($dom, $rowEl, 'VatDescription', $row->vatDescription);
            $this->addTextIfSet($dom, $rowEl, 'FeVatNature', $row->feVatNature);
            $this->addTextIfSet($dom, $rowEl, 'IdPdc', $row->idPdc !== null ? (string) $row->idPdc : null);
        }
    }

    // -------------------------------------------------------------------------
    // DOM helpers
    // -------------------------------------------------------------------------

    private function addText(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        $el->textContent = $value;
        $parent->appendChild($el);
    }

    private function addCdata(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createCDATASection($value));
        $parent->appendChild($el);
    }

    private function addTextIfSet(DOMDocument $dom, DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->addText($dom, $parent, $name, $value);
        }
    }

    private function addCdataIfSet(DOMDocument $dom, DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->addCdata($dom, $parent, $name, $value);
        }
    }

    // -------------------------------------------------------------------------
    // Numeric formatting
    // -------------------------------------------------------------------------

    /**
     * Formats a monetary float for XML output.
     * Always produces exactly 2 decimal places with dot separator,
     * regardless of PHP locale settings.
     * e.g. 1000.0 → '1000.00', 1000.5 → '1000.50', 1220.123 → '1220.12'
     */
    private static function formatAmount(?float $value): string
    {
        return \number_format($value, 2, '.', '');
    }

    /**
     * Formats a quantity float for XML output.
     * Integer quantities are serialized without decimals (1 → '1').
     * Fractional quantities use up to 2 decimal places (1.5 → '1.50', 1.25 → '1.25').
     * This avoids '1.00' for whole units while supporting weight/hour-based billing.
     */
    private static function formatQty(float $value): string
    {
        return \fmod($value, 1.0) === 0.0
            ? (string) (int) $value
            : \number_format($value, 2, '.', '');
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------
    /**
     * Validate only critical required fields (minimal validation to avoid friction)
     *
     * Requirements:
     * - Customer name must not be empty
     * - Document total must be set
     * - Document totalWithoutTax must be set
     * - Document vatAmount must be set
     * - Each row price must be set (can be zero or negative)
     *
     * All other validations (fiscal codes, VAT rates, natura codes, etc.)
     * are delegated to Fattura24 API, which will return detailed errors
     * if something is wrong.
     *
     * @throws ValidationException only if required fields are missing
     */
    private function validateRequiredFields(InvoiceData $invoice): void
    {
        $errors = [];

        // Customer name required
        if (empty($invoice->customer->customerName)) {
            $errors[] = 'Customer name is required';
        }

        // Document total required
        if (!isset($invoice->document->total)) {
            $errors[] = 'Document total is required';
        }

        // Document totalWithoutTax required
        if (!isset($invoice->document->totalWithoutTax)) {
            $errors[] = 'Document totalWithoutTax is required';
        }

        // Document vatAmount required
        if (!isset($invoice->document->vatAmount)) {
            $errors[] = 'Document vatAmount is required';
        }

        // Each row price required
        foreach ($invoice->rows as $index => $row) {
            $rowNum = $index + 1;

            if (!isset($row->price)) {
                $errors[] = "Row #{$rowNum}: price is required";
            }
        }

        // Throw if any errors
        if (!empty($errors)) {
            throw new ValidationException(
                "Invoice validation failed:\n- " . \implode("\n- ", $errors)
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateFeVatNature(RowData $row, int $index): void
    {
        $humanIndex = $index + 1;

        if (empty($row->feVatNature)) {
            throw new ValidationException(
                "Row {$humanIndex}: FeVatNature is required for FE documents with VatCode = 0."
            );
        }

        if (!\in_array($row->feVatNature, $this->validNaturaCodes, true)) {
            throw new ValidationException(
                "Row {$humanIndex}: FeVatNature value '{$row->feVatNature}' is not valid."
            );
        }
    }
}
