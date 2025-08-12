<?php
namespace josemmo\Verifactu\Exceptions;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception thrown when a model class does not pass validation
 */
class InvalidModelException extends RuntimeException {
    public readonly ConstraintViolationListInterface $violations;

    /**
     * Class constructor
     * @param ConstraintViolationListInterface $violations Constraint violations
     */
    public function __construct(ConstraintViolationListInterface $violations) {
        parent::__construct('Invalid instance of model class');
        $this->violations = $violations;
    }

    /**
     * Get human representation of constraint violations
     * @return string Human-readable constraint violations
     */
    public function getHumanRepresentation(): string {
        $res = [];
        foreach ($this->violations as $violation) {
            $res[] = "- {$violation}";
        }
        return implode("\n", $res);
    }

    public function __toString(): string {
        return get_class($this) . ': ' .
           "{$this->message}:\n" .
           $this->getHumanRepresentation() . "\n" .
           "in {$this->file}:{$this->line}\n" .
           $this->getTraceAsString();
    }
}
