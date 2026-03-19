<?php

namespace SimplyIT\Fattura24SDK\Handler;

use RuntimeException;
use SimpleXMLElement;
use SimplyIT\Fattura24SDK\Response\GetChartOfAccountsResponse;
use SimplyIT\Fattura24SDK\Response\GetNumeratorsResponse;
use SimplyIT\Fattura24SDK\Response\GetTemplatesResponse;
use SimplyIT\Fattura24SDK\Response\SaveDocumentResponse;
use SimplyIT\Fattura24SDK\Response\TestKeyResponse;

/**
 * ResponseHandler
 *
 * Factory for parsing raw Fattura24 API responses into typed response objects.
 * Every public method accepts the raw HTTP array from HttpClient
 * and returns a typed value object. No raw arrays leak to callers.
 */
class ResponseHandler
{
    /**
     * Parses testKey API response.
     */
    public function parseTestKeyResponse(array $response): TestKeyResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new TestKeyResponse(returnCode: 0, description: 'Invalid or empty response');
        }

        $sub = $xml->subscription ?? null;

        return new TestKeyResponse(
            returnCode:            (int) ($xml->returnCode  ?? 0),
            description:           (string) ($xml->description ?? ''),
            accountId:             $sub !== null ? (string) ($sub->accountId             ?? '') : null,
            emailOwner:            $sub !== null ? (string) ($sub->emailOwner            ?? '') : null,
            subscriptionType:      $sub !== null ? (string) ($sub->type                  ?? '') : null,
            totalCallsLast24Hours: $sub !== null ? (int) ($sub->totalCallInLast24Hour ?? 0) : null,
            expire:                $sub !== null ? (string) ($sub->expire                ?? '') : null,
        );
    }

    /**
     * Parses saveDocument API response.
     */
    public function parseDocumentResponse(array $response): SaveDocumentResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            throw new RuntimeException('Invalid XML response from saveDocument API');
        }

        return new SaveDocumentResponse(
            raw:       $response,
            docId:     (string) ($xml->docId     ?? ''),
            docNumber: (string) ($xml->docNumber ?? ''),
            docType:   (string) ($xml->docType   ?? ''),
            pdfUrl:    !empty($xml->pdfUrl) ? (string) $xml->pdfUrl : null,
            xmlUrl:    !empty($xml->xmlUrl) ? (string) $xml->xmlUrl : null,
        );
    }

    /**
     * Parses getTemplates API response.
     */
    public function parseTemplatesResponse(array $response): GetTemplatesResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new GetTemplatesResponse(order: [], invoice: []);
        }

        $order = [];
        foreach ($xml->modelloOrdine ?? [] as $item) {
            $id          = (int) $item->id;
            $order[$id]  = $this->label((string) $item->descrizione, $id);
        }

        $invoice = [];
        foreach ($xml->modelloFattura ?? [] as $item) {
            $id            = (int) $item->id;
            $invoice[$id]  = $this->label((string) $item->descrizione, $id);
        }

        return new GetTemplatesResponse(order: $order, invoice: $invoice);
    }

    /**
     * Parses getNumerators API response.
     */
    public function parseNumeratorsResponse(array $response): GetNumeratorsResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new GetNumeratorsResponse(invoice: [], receipt: [], electronicInvoice: []);
        }

        $invoice           = [];
        $receipt           = [];
        $electronicInvoice = [];

        foreach ($xml->sezionale ?? [] as $sez) {
            $sezId   = (int) $sez->id;
            $preview = (string) $sez->anteprima;

            foreach ($sez->doc ?? [] as $doc) {
                $docId    = (int) $doc->id;
                $docState = (int) $doc->stato;
                $label    = $docState === 2 ? "{$preview} (Predefinito)" : $preview;

                if ($docId === 1  && $docState >= 1) {
                    $invoice[$sezId]           = $label;
                }
                if ($docId === 3  && $docState >= 1) {
                    $receipt[$sezId]           = $label;
                }
                if ($docId === 11 && $docState >= 1) {
                    $electronicInvoice[$sezId] = $label;
                }
            }
        }

        return new GetNumeratorsResponse(
            invoice:           $invoice,
            receipt:           $receipt,
            electronicInvoice: $electronicInvoice,
        );
    }

    /**
     * Parses getChartOfAccounts API response.
     */
    public function parseChartOfAccountsResponse(array $response): GetChartOfAccountsResponse
    {
        $xml = $this->loadXml($response['body'] ?? '');

        if (!$xml) {
            return new GetChartOfAccountsResponse(accounts: []);
        }

        $accounts = [];
        foreach ($xml->pdc ?? [] as $pdc) {
            $id              = (int) $pdc->id;
            $code            = \str_replace('^', '.', (string) $pdc->codice);
            $desc            = \str_replace("'", "\\'", (string) $pdc->descrizione);
            $accounts[$id]   = "{$code} - {$desc}";
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
