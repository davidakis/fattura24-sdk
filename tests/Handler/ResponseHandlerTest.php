<?php

namespace Davidakis\Fattura24SDK\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Davidakis\Fattura24SDK\Handler\ResponseHandler;

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
    // parseTemplatesResponse
    // -------------------------------------------------------------------------

    public function testParseTemplatesResponseReturnsTypedObject(): void
    {
        $result = $this->handler->parseTemplatesResponse($this->templateResponse());

        $this->assertInstanceOf(\Davidakis\Fattura24SDK\Response\GetTemplatesResponse::class, $result);
        $this->assertIsArray($result->order);
        $this->assertIsArray($result->invoice);
    }

    public function testParseTemplatesResponseOrderList(): void
    {
        $result = $this->handler->parseTemplatesResponse($this->templateResponse());
        
        $this->assertArrayHasKey(1, $result->order);
        $this->assertStringContainsString('Ordine Standard', $result->order[1]);
        $this->assertStringContainsString('ID: 1', $result->order[1]);
    }

    public function testParseTemplatesResponseInvoiceList(): void
    {
        $result = $this->handler->parseTemplatesResponse($this->templateResponse());
        
        $this->assertCount(2, $result->invoice);
        $this->assertArrayHasKey(10, $result->invoice);
        $this->assertArrayHasKey(11, $result->invoice);
    }

    public function testParseTemplatesResponseReturnsEmptyForEmptyBody(): void
    {
        $result = $this->handler->parseTemplatesResponse($this->emptyResponse());
        
        $this->assertInstanceOf(\Davidakis\Fattura24SDK\Response\GetTemplatesResponse::class, $result);
        $this->assertEmpty($result->order);
        $this->assertEmpty($result->invoice);
        $this->assertTrue($result->isEmpty());
    }

    // -------------------------------------------------------------------------
    // parseNumeratorsResponse
    // -------------------------------------------------------------------------

    public function testParseNumeratorsResponseReturnsTypedObject(): void
    {
        $result = $this->handler->parseNumeratorsResponse($this->numeratorResponse());

        $this->assertInstanceOf(\Davidakis\Fattura24SDK\Response\GetNumeratorsResponse::class, $result);
        $this->assertIsArray($result->invoice);
        $this->assertIsArray($result->receipt);
        $this->assertIsArray($result->electronicInvoice);
    }

    public function testParseNumeratorsResponseElectronicInvoice(): void
    {
        $result = $this->handler->parseNumeratorsResponse($this->numeratorResponse());
        
        $this->assertArrayHasKey(5, $result->electronicInvoice);
        $this->assertStringContainsString('Predefinito', $result->electronicInvoice[5]);
    }

    public function testParseNumeratorsResponseInvoice(): void
    {
        $result = $this->handler->parseNumeratorsResponse($this->numeratorResponse());
        
        $this->assertArrayHasKey(2, $result->invoice);
        $this->assertStringContainsString('2025/FA', $result->invoice[2]);
    }

    public function testParseNumeratorsResponseReceipt(): void
    {
        $result = $this->handler->parseNumeratorsResponse($this->numeratorResponse());
        
        $this->assertArrayHasKey(3, $result->receipt);
    }

    // -------------------------------------------------------------------------
    // parseChartOfAccountsResponse
    // -------------------------------------------------------------------------

    public function testParseChartOfAccountsResponseReturnsTypedObject(): void
    {
        $result = $this->handler->parseChartOfAccountsResponse($this->coaResponse());
        
        $this->assertInstanceOf(\Davidakis\Fattura24SDK\Response\GetChartOfAccountsResponse::class, $result);
        $this->assertIsArray($result->accounts);
    }

    public function testParseChartOfAccountsResponseConvertsCaretToDot(): void
    {
        $result = $this->handler->parseChartOfAccountsResponse($this->coaResponse());
        
        $this->assertArrayHasKey(100, $result->accounts);
        $this->assertStringContainsString('1.1.1', $result->accounts[100]);
        $this->assertStringNotContainsString('^', $result->accounts[100]);
    }

    public function testParseChartOfAccountsResponseIncludesDescription(): void
    {
        $result = $this->handler->parseChartOfAccountsResponse($this->coaResponse());
        
        $this->assertStringContainsString('Ricavi da servizi', $result->accounts[100]);
        $this->assertStringContainsString('Costi operativi', $result->accounts[200]);
    }

    public function testParseChartOfAccountsResponseReturnsEmptyForEmptyBody(): void
    {
        $result = $this->handler->parseChartOfAccountsResponse($this->emptyResponse());
        
        $this->assertInstanceOf(\Davidakis\Fattura24SDK\Response\GetChartOfAccountsResponse::class, $result);
        $this->assertEmpty($result->accounts);
        $this->assertTrue($result->isEmpty());
    }
}