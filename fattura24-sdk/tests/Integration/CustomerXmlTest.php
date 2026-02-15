<?php

namespace SimplyIT\Fattura24SDK\Tests\Integration;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Xml\XmlGenerator;

/**
 * Generates a complete, realistic CustomerData XML and saves it
 * to tests/Integration/output/customer.xml so it can be submitted
 * manually or programmatically to the Fattura24 API for format validation.
 *
 * Run with:
 *   ./vendor/bin/phpunit --group integration
 */
#[Group('integration')]
class CustomerXmlTest extends TestCase
{
    private const OUTPUT_DIR = __DIR__ . '/output';
    private const OUTPUT_FILE = self::OUTPUT_DIR . '/customer.xml';

    protected function setUp(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }
    }

    public function testGeneratesCompleteCustomerXml(): void
    {
        $customer = new CustomerData('Studio Medico Rossi S.r.l.');

        // Address
        $customer->customerAddress  = 'Via della Salute, 42';
        $customer->customerPostcode = '20121';
        $customer->customerCity     = 'Milano';
        $customer->customerProvince = 'MI';
        $customer->customerCountry  = 'IT';

        // Contact / fiscal
        $customer->customerEmail      = 'amministrazione@studiorossi.it';
        $customer->customerCellPhone  = '+39 02 1234567';
        $customer->customerVatCode    = '12345678910';
        $customer->customerFiscalCode = 'RSSMRA80A01H501U';

        // Electronic invoice delivery
        $customer->feCustomerPec      = 'studiorossi@pec.it';
        $customer->feDestinationCode  = 'ABCDEFG';

        $generator = new XmlGenerator();
        $xml       = $generator->fromCustomer($customer);

        // Assert valid XML
        $this->assertNotEmpty($xml);
        $this->assertFalse(XmlGenerator::hasErrors($xml));

        // Assert structure
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $this->assertSame('Fattura24',    $dom->documentElement->tagName);
        $this->assertSame(1, $dom->getElementsByTagName('Document')->length);
        $this->assertSame(1, $dom->getElementsByTagName('CustomerName')->length);

        // Assert all fields present
        $fields = [
            'CustomerName'      => 'Studio Medico Rossi S.r.l.',
            'CustomerAddress'   => 'Via della Salute, 42',
            'CustomerPostcode'  => '20121',
            'CustomerCity'      => 'Milano',
            'CustomerProvince'  => 'MI',
            'CustomerCountry'   => 'IT',
            'CustomerEmail'     => 'amministrazione@studiorossi.it',
            'CustomerCellPhone' => '+39 02 1234567',
            'CustomerVatCode'   => '12345678910',
            'CustomerFiscalCode'=> 'RSSMRA80A01H501U',
            'FeCustomerPec'     => 'studiorossi@pec.it',
            'FeDestinationCode' => 'ABCDEFG',
        ];

        foreach ($fields as $tag => $expectedValue) {
            $nodes = $dom->getElementsByTagName($tag);
            $this->assertSame(1, $nodes->length, "Missing XML tag: <{$tag}>");
            $this->assertSame($expectedValue, $nodes->item(0)->nodeValue, "Wrong value for <{$tag}>");
        }

        // Save to file for manual API submission
        file_put_contents(self::OUTPUT_FILE, $xml);

        $this->assertFileExists(self::OUTPUT_FILE);
        $this->assertGreaterThan(0, filesize(self::OUTPUT_FILE));

        echo "\n[integration] Customer XML saved to: " . self::OUTPUT_FILE . "\n";
        echo $xml . "\n";
    }
}
