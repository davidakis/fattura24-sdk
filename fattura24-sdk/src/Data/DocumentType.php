<?php

namespace SimplyIT\Fattura24SDK\Data;

/**
 * DocumentType
 *
 * Backed enum of known Fattura24 document types.
 * Using an enum guarantees at the type level that only valid values
 * are passed — no runtime validation needed.
 *
 * When Fattura24 introduces a new document type, add a case here
 * and bump the SDK minor version. The change is explicit and traceable.
 *
 * Usage:
 *   new DocumentData(DocumentType::FatturaElettronica, ...)
 *
 * Raw string still accepted via DocumentType::from('FE') if needed.
 */
enum DocumentType: string
{
    case Order = 'C';
    case FatturaElettronica    = 'FE';
    case Fattura               = 'I';
    case FatturaForce          = 'I-Force';
    case Ricevuta              = 'R';
}
