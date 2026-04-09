<?php

namespace Davidakis\Fattura24SDK\Tests\Response;

use PHPUnit\Framework\TestCase;
use Davidakis\Fattura24SDK\Response\GetChartOfAccountsResponse;
use Davidakis\Fattura24SDK\Response\GetNumeratorsResponse;
use Davidakis\Fattura24SDK\Response\GetTemplatesResponse;

class ApiResponsesTest extends TestCase
{
    public function testGetTemplatesResponse(): void
    {
        $response = new GetTemplatesResponse(
            order: [1 => 'Template Order A', 2 => 'Template Order B'],
            invoice: [10 => 'Template Invoice X', 20 => 'Template Invoice Y'],
        );

        $this->assertCount(2, $response->order);
        $this->assertCount(2, $response->invoice);
        $this->assertFalse($response->isEmpty());
        
        $all = $response->getAllTemplates();
        $this->assertCount(4, $all);
        
        $this->assertEquals('Template Invoice X', $response->findTemplateById(10));
        $this->assertNull($response->findTemplateById(999));
    }

    public function testGetTemplatesResponseEmpty(): void
    {
        $response = new GetTemplatesResponse(order: [], invoice: []);
        
        $this->assertTrue($response->isEmpty());
        $this->assertEmpty($response->getAllTemplates());
    }

    public function testGetNumeratorsResponse(): void
    {
        $response = new GetNumeratorsResponse(
            invoice: [1 => '01-2026', 8688 => '01 (Predefinito)'],
            receipt: [1 => '01-2026 (Predefinito)'],
            electronicInvoice: [2 => '01-2026-FE (Predefinito)'],
        );

        $this->assertCount(2, $response->invoice);
        $this->assertEquals('01-2026', $response->getLabel('invoice', 1));
        $this->assertNull($response->getLabel('invoice', 999));
        
        // Test getDefaultId
        $this->assertEquals(8688, $response->getDefaultId('invoice'));
        $this->assertEquals(1, $response->getDefaultId('receipt'));
        $this->assertEquals(2, $response->getDefaultId('electronic_invoice'));
        
        // Test getAllNumerators
        $all = $response->getAllNumerators();
        $this->assertCount(4, $all);
    }

    public function testGetNumeratorsResponseNoDefault(): void
    {
        $response = new GetNumeratorsResponse(
            invoice: [1 => '01-2026', 2 => '02-2026'],
            receipt: [],
            electronicInvoice: [],
        );

        $this->assertNull($response->getDefaultId('invoice'));
    }

    public function testGetChartOfAccountsResponse(): void
    {
        $response = new GetChartOfAccountsResponse(
            accounts: [
                432342 => '01.01 - Prodotto/Servizio A',
                432343 => '01.02 - Prodotto/Servizio B',
                452564 => '1234 - commissione metodo pagamento',
            ],
        );

        $this->assertCount(3, $response->accounts);
        $this->assertFalse($response->isEmpty());
        
        $this->assertEquals('01.01 - Prodotto/Servizio A', $response->getDescription(432342));
        $this->assertNull($response->getDescription(999999));
        
        $ids = $response->getAccountIds();
        $this->assertCount(3, $ids);
        $this->assertContains(432342, $ids);
    }

    public function testGetChartOfAccountsResponseSearch(): void
    {
        $response = new GetChartOfAccountsResponse(
            accounts: [
                432342 => '01.01 - Prodotto/Servizio A',
                432343 => '01.02 - Prodotto/Servizio B',
                452564 => '1234 - commissione metodo pagamento',
            ],
        );

        $results = $response->search('prodotto');
        $this->assertCount(2, $results);
        
        $results = $response->search('commissione');
        $this->assertCount(1, $results);
        $this->assertArrayHasKey(452564, $results);
        
        $results = $response->search('XXXXX');
        $this->assertEmpty($results);
    }

    public function testGetChartOfAccountsResponseEmpty(): void
    {
        $response = new GetChartOfAccountsResponse(accounts: []);
        
        $this->assertTrue($response->isEmpty());
        $this->assertEmpty($response->getAccountIds());
    }
}
