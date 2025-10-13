<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Tipo de registro
 */
enum RecordType: string {
    /** Registro */
    case Registration = 'Alta';

    /** Anulación */
    case Cancellation = 'Anulacion';
}
