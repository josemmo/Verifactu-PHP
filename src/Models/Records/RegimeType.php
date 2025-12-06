<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Claves de Régimen Especial o Trascendencia Adicional
 */
enum RegimeType: string {
    /** Operación de régimen general */
    case C01 = '01';

    /** Exportación */
    case C02 = '02';

    /** Operaciones a las que se aplique el régimen especial de bienes usados, objetos de arte, antigüedades y objetos de colección */
    case C03 = '03';

    /** Régimen especial del oro de inversión */
    case C04 = '04';

    /** Régimen especial de las agencias de viajes */
    case C05 = '05';

    /** Régimen especial grupo de entidades en IVA (Nivel Avanzado) */
    case C06 = '06';

    /** Régimen especial del criterio de caja */
    case C07 = '07';

    /** Operaciones sujetas al IPSI / IGIC (Impuesto sobre la Producción, los Servicios y la Importación / Impuesto General Indirecto Canario) */
    case C08 = '08';

    /** Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras en nombre y por cuenta ajena (D.A 4ª RD1619/2012) */
    case C09 = '09';

    /** Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial, de autor u otros por cuenta de sus socios, asociados o colegiados efectuados por sociedades, asociaciones, colegios profesionales u otras entidades que realicen estas funciones de cobro */
    case C10 = '10';

    /** Operaciones de arrendamiento de local de negocio */
    case C11 = '11';

    /** Factura con IVA pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública */
    case C14 = '14';

    /** Factura con IVA pendiente de devengo en operaciones de tracto sucesivo */
    case C15 = '15';

    /** Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS) */
    case C17 = '17';

    /** Recargo de equivalencia */
    case C18 = '18';

    /** Operaciones de actividades incluidas en el Régimen Especial de Agricultura, Ganadería y Pesca (REAGYP) */
    case C19 = '19';

    /** Régimen simplificado */
    case C20 = '20';
}
