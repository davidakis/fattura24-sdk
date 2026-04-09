<?php

namespace Davidakis\Fattura24SDK\Tests\Data;

use PHPUnit\Framework\TestCase;
use Davidakis\Fattura24SDK\Data\CustomerData;
use Davidakis\Fattura24SDK\Exceptions\ValidationException;

class CustomerDataTest extends TestCase
{
    public function testConstructorSetsName(): void
    {
        $c = new CustomerData('Mario Rossi');
        $this->assertSame('Mario Rossi', $c->customerName);
    }

    public function testEmptyNameThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        new CustomerData('');
    }

    public function testWhitespaceOnlyNameThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        new CustomerData('   ');
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $c = new CustomerData('Test');

        $this->assertNull($c->customerAddress);
        $this->assertNull($c->customerPostcode);
        $this->assertNull($c->customerCity);
        $this->assertNull($c->customerProvince);
        $this->assertNull($c->customerCountry);
        $this->assertNull($c->customerEmail);
        $this->assertNull($c->customerCellPhone);
        $this->assertNull($c->customerFiscalCode);
        $this->assertNull($c->customerVatCode);
        $this->assertNull($c->feCustomerPec);
        $this->assertNull($c->feDestinationCode);
    }

    public function testHasFeDeliveryReturnsFalseWhenBothEmpty(): void
    {
        $c = new CustomerData('Test');
        $this->assertFalse($c->hasFeDelivery());
    }

    public function testHasFeDeliveryReturnsTrueWithPec(): void
    {
        $c = new CustomerData('Test');
        $c->feCustomerPec = 'test@pec.it';
        $this->assertTrue($c->hasFeDelivery());
    }

    public function testHasFeDeliveryReturnsTrueWithDestinationCode(): void
    {
        $c = new CustomerData('Test');
        $c->feDestinationCode = 'ABCDEFG';
        $this->assertTrue($c->hasFeDelivery());
    }

    public function testHasFeDeliveryReturnsTrueWithBoth(): void
    {
        $c = new CustomerData('Test');
        $c->feCustomerPec     = 'test@pec.it';
        $c->feDestinationCode = 'ABCDEFG';
        $this->assertTrue($c->hasFeDelivery());
    }
}
