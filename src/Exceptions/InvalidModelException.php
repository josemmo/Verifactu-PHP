<?php
namespace josemmo\Verifactu\Exceptions;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception thrown when a model class does not pass validation
 */
class InvalidModelException extends RuntimeException {
    public readonly ConstraintViolationListInterface $violations;

    /**
     * Class constructor
     *
     * @param ConstraintViolationListInterface $violations Constraint violations
     */
    public function __construct(ConstraintViolationListInterface $violations) {
        $this->violations = $violations;
        parent::__construct("Invalid instance of model class:\n" . $this->getHumanRepresentation());
    }

    /**
     * Get human representation of constraint violations
     *
     * @return string Human-readable constraint violations
     */
    private function getHumanRepresentation(): string {
        $res = [];
        foreach ($this->violations as $violation) {
            // Use the built-in string serializer if available (default), fallback to message
            $res[] = ($violation instanceof ConstraintViolation) ? "- $violation" : "- {$violation->getMessage()}";
        }
        return implode("\n", $res);
    }
}
