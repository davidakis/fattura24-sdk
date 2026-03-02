<?php

namespace SimplyIT\Fattura24SDK\Handler;

use RuntimeException;
use SimpleXMLElement;
use SimplyIT\Fattura24SDK\Response\GetChartOfAccountsResponse;
use SimplyIT\Fattura24SDK\Response\GetNumeratorsResponse;
use SimplyIT\Fattura24SDK\Response\GetTemplatesResponse;
use SimplyIT\Fattura24SDK\Response\SaveDocumentResponse;

/**
 * ResponseHandler
 *
 * Factory for parsing raw Fattura24 API responses into typed response objects.
 * Responsible for XML parsing and response object construction.
 */
class ResponseHandler
{
    /**
     * Parses saveDocument API response.
     *
     * @param array $response Raw HTTP response with 'body' key
     * @return SaveDocumentResponse Typed response object
     */
    public function parseDocumentResponse(array $response): SaveDocumentResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            throw new RuntimeException('Invalid XML response from saveDocument API');
        }

        return new SaveDocumentResponse(
            raw: $response,
            docId: (string) ($xml->docId ?? ''),
            docNumber: (string) ($xml->docNumber ?? ''),
            docType: (string) ($xml->docType ?? ''),
            pdfUrl: !empty($xml->pdfUrl) ? (string) $xml->pdfUrl : null,
            xmlUrl: !empty($xml->xmlUrl) ? (string) $xml->xmlUrl : null,
        );
    }

    public function getDocId(array $response): string
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return '';
        }

        return (string) ($xml->docId ?? '');
    }

    public function getDocNumber(array $response): string
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return '';
        }

        return (string) ($xml->docNumber ?? '');
    }

    /**
     * Parses getTemplates API response.
     *
     * @param array $response Raw HTTP response
     * @return GetTemplatesResponse Typed response with order and invoice templates
     */
    public function parseTemplatesResponse(array $response): GetTemplatesResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new GetTemplatesResponse(order: [], invoice: []);
        }

        $order = [];
        foreach ($xml->modelloOrdine ?? [] as $item) {
            $id = (int) $item->id;
            $order[$id] = $this->label((string) $item->descrizione, $id);
        }

        $invoice = [];
        foreach ($xml->modelloFattura ?? [] as $item) {
            $id = (int) $item->id;
            $invoice[$id] = $this->label((string) $item->descrizione, $id);
        }

        return new GetTemplatesResponse(
            order: $order,
            invoice: $invoice,
        );
    }

    /**
     * Parses getNumerators API response.
     *
     * @param array $response Raw HTTP response
     * @return GetNumeratorsResponse Typed response with numerators by document type
     */
    public function parseNumeratorsResponse(array $response): GetNumeratorsResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new GetNumeratorsResponse(
                invoice: [],
                receipt: [],
                electronicInvoice: [],
            );
        }

        $invoice = [];
        $receipt = [];
        $electronicInvoice = [];

        foreach ($xml->sezionale ?? [] as $sez) {
            $sezId = (int) $sez->id;
            $preview = (string) $sez->anteprima;

            foreach ($sez->doc ?? [] as $doc) {
                $docId = (int) $doc->id;
                $docState = (int) $doc->stato;
                $label = $docState === 2 ? "{$preview} (Predefinito)" : $preview;

                if ($docId === 1 && $docState >= 1) {
                    $invoice[$sezId] = $label;
                }
                if ($docId === 3 && $docState >= 1) {
                    $receipt[$sezId] = $label;
                }
                if ($docId === 11 && $docState >= 1) {
                    $electronicInvoice[$sezId] = $label;
                }
            }
        }

        return new GetNumeratorsResponse(
            invoice: $invoice,
            receipt: $receipt,
            electronicInvoice: $electronicInvoice,
        );
    }

    /**
     * Parses getChartOfAccounts API response.
     *
     * @param array $response Raw HTTP response
     * @return GetChartOfAccountsResponse Typed response with account ID => description map
     */
    public function parseChartOfAccountsResponse(array $response): GetChartOfAccountsResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new GetChartOfAccountsResponse(accounts: []);
        }

        $accounts = [];
        foreach ($xml->pdc ?? [] as $pdc) {
            $id = (int) $pdc->id;
            $code = \str_replace('^', '.', (string) $pdc->codice);
            $desc = \str_replace("'", "\\'", (string) $pdc->descrizione);
            $accounts[$id] = "{$code} - {$desc}";
        }

        return new GetChartOfAccountsResponse(accounts: $accounts);
    }

    // -------------------------------------------------------------------------

    private function loadXml(string $body): ?SimpleXMLElement
    {
        if (empty($body)) {
            return null;
        }
        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($body);
        \libxml_clear_errors();

        return $xml instanceof SimpleXMLElement ? $xml : null;
    }

    private function label(string $description, int $id): string
    {
        return \str_replace("'", "\\'", $description) . " (ID: {$id})";
    }
}
