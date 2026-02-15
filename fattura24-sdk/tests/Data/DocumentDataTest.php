<?php

namespace SimplyIT\Fattura24SDK\Tests\Data;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;

class DocumentDataTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDocument(DocumentType $type = DocumentType::FatturaElettronica): DocumentData
    {
        return new DocumentData(
            documentType:             $type,
            total:                    1220.00,
            totalWithoutTax:          1000.00,
            vatAmount:                220.00,
            sendEmail:                false,
            fePaymentCode:            'MP05',
            paymentMethodName:        'Bonifico bancario',
            paymentMethodDescription: 'IBAN: IT00 0000 0000 0000'
        );
    }

    // -------------------------------------------------------------------------
    // Constructor and required fields
    // -------------------------------------------------------------------------

    public function testConstructorSetsAllRequiredFields(): void
    {
        $doc = $this->makeDocument(DocumentType::FatturaElettronica);

        $this->assertSame(DocumentType::FatturaElettronica, $doc->documentType);
        $this->assertSame(1220.00,                         $doc->total);
        $this->assertSame(1000.00,                         $doc->totalWithoutTax);
        $this->assertSame(220.00,                         $doc->vatAmount);
        $this->assertFalse($doc->sendEmail);
        $this->assertSame('MP05',                          $doc->fePaymentCode);
        $this->assertSame('Bonifico bancario',             $doc->paymentMethodName);
        $this->assertSame('IBAN: IT00 0000 0000 0000',     $doc->paymentMethodDescription);
    }

    // -------------------------------------------------------------------------
    // DocumentType enum
    // -------------------------------------------------------------------------

    public function testEnumCasesHaveExpectedBackingValues(): void
    {
        $this->assertSame('C',       DocumentType::Order->value);
        $this->assertSame('FE',      DocumentType::FatturaElettronica->value);
        $this->assertSame('I',       DocumentType::Fattura->value);
        $this->assertSame('I-Force', DocumentType::FatturaForce->value);
        $this->assertSame('R',       DocumentType::Ricevuta->value);
    }

    /** @dataProvider documentTypeProvider */
    public function testConstructorAcceptsAllEnumCases(DocumentType $type): void
    {
        $doc = $this->makeDocument($type);
        $this->assertSame($type, $doc->documentType);
    }

    public static function documentTypeProvider(): array
    {
        return array_map(
            fn(DocumentType $t) => [$t],
            DocumentType::cases()
        );
    }

    public function testEnumFromRawString(): void
    {
        // DocumentType::from() is the escape hatch for raw string values
        $type = DocumentType::from('FE');
        $this->assertSame(DocumentType::FatturaElettronica, $type);
    }

    public function testEnumTryFromReturnsNullForUnknownValue(): void
    {
        // Unknown types return null — no exception, no SDK crash
        $this->assertNull(DocumentType::tryFrom('FUTURE_TYPE'));
    }

    // -------------------------------------------------------------------------
    // Optional fields default to null
    // -------------------------------------------------------------------------

    public function testOptionalFieldsDefaultToNull(): void
    {
        $doc = $this->makeDocument();

        $this->assertNull($doc->currency);
        $this->assertNull($doc->feDocType);
        $this->assertNull($doc->feDocParamiter);
        $this->assertNull($doc->feVirtualStamp);
        $this->assertNull($doc->footNotes);
        $this->assertNull($doc->f24OrderId);
        $this->assertNull($doc->idTemplate);
        $this->assertNull($doc->idNumerator);
        $this->assertNull($doc->object);
        $this->assertNull($doc->number);
    }

    public function testOptionalFieldsCanBeSet(): void
    {
        $doc              = $this->makeDocument();
        $doc->currency    = 'EUR';
        $doc->object      = 'Prestazione medica';
        $doc->idNumerator = 42;
        $doc->footNotes   = 'Pagamento ricevuto';

        $this->assertSame('EUR',                $doc->currency);
        $this->assertSame('Prestazione medica', $doc->object);
        $this->assertSame(42,                   $doc->idNumerator);
        $this->assertSame('Pagamento ricevuto', $doc->footNotes);
    }
}
