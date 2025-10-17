<?php
namespace josemmo\Verifactu\Models\Records;

enum OperationType: string {
    /** Operación sujeta y no exenta - Sin inversión del sujeto pasivo */
    case Subject = 'S1';

    /** Operación sujeta y no exenta - Con inversión del sujeto pasivo */
    case PassiveSubject = 'S2';

    /** Operación no sujeta - Artículos 7, 14 y otros */
    case NonSubject = 'N1';

    /** Operación no sujeta por reglas de localización */
    case NonSubjectByLocation = 'N2';

    /** Exenta por el artículo 20 */
    case ExemptByArticle20 = 'E1';

    /** Exenta por el artículo 21 */
    case ExemptByArticle21 = 'E2';

    /** Exenta por el artículo 22 */
    case ExemptByArticle22 = 'E3';

    /** Exenta por los artículos 23 y 24 */
    case ExemptByArticles23And24 = 'E4';

    /** Exenta por el artículo 25 */
    case ExemptByArticle25 = 'E5';

    /** Exenta por otros */
    case ExemptByOther = 'E6';

    /**
     * Is subject operation
     *
     * @return bool Whether is a subject operation type
     */
    public function isSubject(): bool {
        return ($this === self::Subject || $this === self::PassiveSubject);
    }

    /**
     * Is non-subject operation
     *
     * @return bool Whether is a non-subject operation type
     */
    public function isNonSubject(): bool {
        return ($this === self::NonSubject || $this === self::NonSubjectByLocation);
    }

    /**
     * Is exempt operation
     *
     * @return bool Whether is an exempt operation type
     */
    public function isExempt(): bool {
        return (
            $this === self::ExemptByArticle20 ||
            $this === self::ExemptByArticle21 ||
            $this === self::ExemptByArticle22 ||
            $this === self::ExemptByArticles23And24 ||
            $this === self::ExemptByArticle25 ||
            $this === self::ExemptByOther
        );
    }
}
