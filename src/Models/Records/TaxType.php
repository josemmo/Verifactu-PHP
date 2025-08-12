<?php
namespace josemmo\Verifactu\Models\Records;

enum TaxType: string {
    /** Impuesto sobre el Valor Añadido (IVA) */
    case IVA = '01';

    /** Impuesto sobre la Producción, los Servicios y la Importación (IPSI) de Ceuta y Melilla */
    case IPSI = '02';

    /** Impuesto General Indirecto Canario (IGIC) */
    case IGIC = '03';
	
	/** Recargo de Equivalencia */
    case RE = '04';

    /** Otros */
    case Other = '05';
}
