<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use josemmo\Verifactu\Models\Records\OperationType;
use PHPUnit\Framework\TestCase;

final class OperationTypeTest extends TestCase {
    public function testHasWorkingHelpers(): void {
        $this->assertTrue(OperationType::Subject->isSubject());
        $this->assertFalse(OperationType::Subject->isNonSubject());
        $this->assertFalse(OperationType::Subject->isExempt());

        $this->assertFalse(OperationType::NonSubject->isSubject());
        $this->assertTrue(OperationType::NonSubject->isNonSubject());
        $this->assertFalse(OperationType::NonSubject->isExempt());

        $this->assertFalse(OperationType::ExemptByArticle21->isSubject());
        $this->assertFalse(OperationType::ExemptByArticle21->isNonSubject());
        $this->assertTrue(OperationType::ExemptByArticle21->isExempt());
    }
}
