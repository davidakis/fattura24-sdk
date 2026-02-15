<?php

namespace SimplyIT\Fattura24SDK\Handler;

/**
 * ResponseHandler
 *
 * Parses raw Fattura24 API responses into usable PHP structures.
 */
class ResponseHandler
{
    public function getDocId(array $response): string
    {
        $xml = $this->loadXml($response['body'] ?? '');
        return $xml ? (string) $xml->docId : '';
    }

    public function getDocNumber(array $response): string
    {
        $xml = $this->loadXml($response['body'] ?? '');
        return $xml ? (string) $xml->docNumber : '';
    }

    /**
     * @return array{order: array<int,string>, invoice: array<int,string>}
     */
    public function parseTemplates(array $response): array
    {
        $xml = $this->loadXml($response['body'] ?? '');
        if (!$xml) {
            return ['order' => [], 'invoice' => []];
        }

        $result = ['order' => [], 'invoice' => []];

        foreach ($xml->modelloOrdine ?? [] as $item) {
            $id = (int) $item->id;
            $result['order'][$id] = $this->label((string) $item->descrizione, $id);
        }

        foreach ($xml->modelloFattura ?? [] as $item) {
            $id = (int) $item->id;
            $result['invoice'][$id] = $this->label((string) $item->descrizione, $id);
        }

        return $result;
    }

    /**
     * @return array{invoice: array<int,string>, receipt: array<int,string>, electronic_invoice: array<int,string>}
     */
    public function parseNumerators(array $response): array
    {
        $xml = $this->loadXml($response['body'] ?? '');
        if (!$xml) {
            return [];
        }

        $result = ['invoice' => [], 'receipt' => [], 'electronic_invoice' => []];

        foreach ($xml->sezionale ?? [] as $sez) {
            $sezId   = (int) $sez->id;
            $preview = (string) $sez->anteprima;

            foreach ($sez->doc ?? [] as $doc) {
                $docId    = (int) $doc->id;
                $docState = (int) $doc->stato;
                $label    = $docState === 2 ? "{$preview} (Predefinito)" : $preview;

                if ($docId === 1  && $docState >= 1) { $result['invoice'][$sezId]             = $label; }
                if ($docId === 3  && $docState >= 1) { $result['receipt'][$sezId]              = $label; }
                if ($docId === 11 && $docState >= 1) { $result['electronic_invoice'][$sezId]   = $label; }
            }
        }

        return $result;
    }

    /**
     * @return array<int,string>
     */
    public function parseChartOfAccounts(array $response): array
    {
        $xml = $this->loadXml($response['body'] ?? '');
        if (!$xml) {
            return [];
        }

        $result = [];
        foreach ($xml->pdc ?? [] as $pdc) {
            $id          = (int) $pdc->id;
            $code        = str_replace('^', '.', (string) $pdc->codice);
            $description = str_replace("'", "\\'", (string) $pdc->descrizione);
            $result[$id] = "{$code} - {$description}";
        }

        return $result;
    }

    // -------------------------------------------------------------------------

    private function loadXml(string $body): ?\SimpleXMLElement
    {
        if (empty($body)) {
            return null;
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        return $xml instanceof \SimpleXMLElement ? $xml : null;
    }

    private function label(string $description, int $id): string
    {
        return str_replace("'", "\\'", $description) . " (ID: {$id})";
    }
}
