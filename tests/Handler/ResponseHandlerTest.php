<?php

namespace SimplyIT\Fattura24SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Handler\ResponseHandler;

class ResponseHandlerTest extends TestCase
{
    private ResponseHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ResponseHandler();
    }

    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function saveDocumentResponse(): array
    {
        return ['body' => '<?xml version="1.0"?><root><docId>40206114</docId><docNumber>12/2025/FE</docNumber></root>'];
    }

    private function emptyResponse(): array
    {
        return ['body' => ''];
    }

    private function templateResponse(): array
    {
        return ['body' => '<?xml version="1.0"?>
<root>
    <modelloOrdine><id>1</id><descrizione>Ordine Standard</descrizione></modelloOrdine>
    <modelloFattura><id>10</id><descrizione>Fattura Standard</descrizione></modelloFattura>
    <modelloFattura><id>11</id><descrizione>Fattura con Logo</descrizione></modelloFattura>
</root>'];
    }

    private function numeratorResponse(): array
    {
        return ['body' => '<?xml version="1.0"?>
<root>
    <sezionale>
        <id>5</id>
        <anteprima>2025/FE</anteprima>
        <doc><id>11</id><stato>2</stato></doc>
    </sezionale>
    <sezionale>
        <id>2</id>
        <anteprima>2025/FA</anteprima>
        <doc><id>1</id><stato>1</stato></doc>
    </sezionale>
    <sezionale>
        <id>3</id>
        <anteprima>2025/RC</anteprima>
        <doc><id>3</id><stato>1</stato></doc>
    </sezionale>
</root>'];
    }

    private function coaResponse(): array
    {
        return ['body' => '<?xml version="1.0"?>
<root>
    <pdc><id>100</id><codice>1^1^1</codice><descrizione>Ricavi da servizi</descrizione></pdc>
    <pdc><id>200</id><codice>2^1</codice><descrizione>Costi operativi</descrizione></pdc>
</root>'];
    }

    // -------------------------------------------------------------------------
    // getDocId
    // -------------------------------------------------------------------------

    public function testGetDocIdExtractsId(): void
    {
        $this->assertSame('40206114', $this->handler->getDocId($this->saveDocumentResponse()));
    }

    public function testGetDocIdReturnsEmptyForEmptyBody(): void
    {
        $this->assertSame('', $this->handler->getDocId($this->emptyResponse()));
    }

    public function testGetDocIdReturnsEmptyForMissingNode(): void
    {
        $this->assertSame('', $this->handler->getDocId(['body' => '<?xml version="1.0"?><root></root>']));
    }

    // -------------------------------------------------------------------------
    // getDocNumber
    // -------------------------------------------------------------------------

    public function testGetDocNumberExtractsNumber(): void
    {
        $this->assertSame('12/2025/FE', $this->handler->getDocNumber($this->saveDocumentResponse()));
    }

    public function testGetDocNumberReturnsEmptyForEmptyBody(): void
    {
        $this->assertSame('', $this->handler->getDocNumber($this->emptyResponse()));
    }

    // -------------------------------------------------------------------------
    // parseTemplates
    // -------------------------------------------------------------------------

    public function testParseTemplatesReturnsOrderAndInvoiceLists(): void
    {
        $result = $this->handler->parseTemplates($this->templateResponse());

        $this->assertArrayHasKey('order',   $result);
        $this->assertArrayHasKey('invoice', $result);
    }

    public function testParseTemplatesOrderList(): void
    {
        $result = $this->handler->parseTemplates($this->templateResponse());
        $this->assertArrayHasKey(1, $result['order']);
        $this->assertStringContainsString('Ordine Standard', $result['order'][1]);
        $this->assertStringContainsString('ID: 1',           $result['order'][1]);
    }

    public function testParseTemplatesInvoiceList(): void
    {
        $result = $this->handler->parseTemplates($this->templateResponse());
        $this->assertCount(2, $result['invoice']);
        $this->assertArrayHasKey(10, $result['invoice']);
        $this->assertArrayHasKey(11, $result['invoice']);
    }

    public function testParseTemplatesReturnsEmptyArraysForEmptyBody(): void
    {
        $result = $this->handler->parseTemplates($this->emptyResponse());
        $this->assertSame(['order' => [], 'invoice' => []], $result);
    }

    // -------------------------------------------------------------------------
    // parseNumerators
    // -------------------------------------------------------------------------

    public function testParseNumeratorsReturnsAllCategories(): void
    {
        $result = $this->handler->parseNumerators($this->numeratorResponse());

        $this->assertArrayHasKey('invoice',             $result);
        $this->assertArrayHasKey('receipt',             $result);
        $this->assertArrayHasKey('electronic_invoice',  $result);
    }

    public function testParseNumeratorsElectronicInvoice(): void
    {
        $result = $this->handler->parseNumerators($this->numeratorResponse());
        $this->assertArrayHasKey(5, $result['electronic_invoice']);
        $this->assertStringContainsString('Predefinito', $result['electronic_invoice'][5]);
    }

    public function testParseNumeratorsInvoice(): void
    {
        $result = $this->handler->parseNumerators($this->numeratorResponse());
        $this->assertArrayHasKey(2, $result['invoice']);
        $this->assertStringContainsString('2025/FA', $result['invoice'][2]);
    }

    public function testParseNumeratorsReceipt(): void
    {
        $result = $this->handler->parseNumerators($this->numeratorResponse());
        $this->assertArrayHasKey(3, $result['receipt']);
    }

    // -------------------------------------------------------------------------
    // parseChartOfAccounts
    // -------------------------------------------------------------------------

    public function testParseChartOfAccountsConvertsCaretToDot(): void
    {
        $result = $this->handler->parseChartOfAccounts($this->coaResponse());
        $this->assertArrayHasKey(100, $result);
        $this->assertStringContainsString('1.1.1', $result[100]);
        $this->assertStringNotContainsString('^', $result[100]);
    }

    public function testParseChartOfAccountsIncludesDescription(): void
    {
        $result = $this->handler->parseChartOfAccounts($this->coaResponse());
        $this->assertStringContainsString('Ricavi da servizi', $result[100]);
        $this->assertStringContainsString('Costi operativi',   $result[200]);
    }

    public function testParseChartOfAccountsReturnsEmptyForEmptyBody(): void
    {
        $this->assertSame([], $this->handler->parseChartOfAccounts($this->emptyResponse()));
    }
}
