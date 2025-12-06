<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Exceptions\ImportException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Registro de anulación de una factura
 *
 * @field RegistroAnulacion
 */
class CancellationRecord extends Record {
    /**
     * Indicador que especifica que se trata de la anulación de un registro que no existe en la AEAT o en el SIF.
     *
     * @field SinRegistroPrevio
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $withoutPriorRecord = false;

    /**
     * Indicador de rechazo previo
     *
     * Para remitir un nuevo registro de facturación de anulación subsanado tras haber sido rechazado en su remisión
     * inmediatamente anterior.
     * Es decir, en el último envío que contenía ese registro de facturación de alta rechazado.
     *
     * @field RechazoPrevio
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $isPriorRejection = false;

    /**
     * @inheritDoc
     */
    protected static function getRecordElementName(): string {
        return 'RegistroAnulacion';
    }

    /**
     * @inheritDoc
     */
    public function calculateHash(): string {
        // NOTE: Values should NOT be escaped as that what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'IDEmisorFacturaAnulada=' . $this->invoiceId->issuerId;
        $payload .= '&NumSerieFacturaAnulada=' . $this->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFacturaAnulada=' . $this->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&Huella=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $this->hashedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    #[Assert\Callback]
    final public function validateEnforcePreviousInvoice(ExecutionContextInterface $context): void {
        if ($this->previousInvoiceId === null) {
            $context->buildViolation('Previous invoice ID is required for all cancellation records')
                ->atPath('previousInvoiceId')
                ->addViolation();
        }
        if ($this->previousHash === null) {
            $context->buildViolation('Previous hash is required for all cancellation records')
                ->atPath('previousHash')
                ->addViolation();
        }
    }

    /**
     * @inheritDoc
     */
    protected function importCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->get('sum1:IDFactura');
        if ($idFacturaElement === null) {
            throw new ImportException('Missing <sum1:IDFactura /> element');
        }
        $this->invoiceId = InvoiceIdentifier::fromXml($idFacturaElement);

        // Flags
        $withoutPriorRecord = $recordElement->get('sum1:SinRegistroPrevio')?->asText() ?? 'N';
        $isPriorRejection = $recordElement->get('sum1:RechazoPrevio')?->asText() ?? 'N';
        $this->withoutPriorRecord = ($withoutPriorRecord === 'S');
        $this->isPriorRejection = ($isPriorRejection === 'S');
    }

    /**
     * @inheritDoc
     */
    protected function exportCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->add('sum1:IDFactura');
        $this->invoiceId->export($idFacturaElement, true);

        // Flags
        if ($this->withoutPriorRecord) {
            $recordElement->add('sum1:SinRegistroPrevio', 'S');
        }
        if ($this->isPriorRejection) {
            $recordElement->add('sum1:RechazoPrevio', 'S');
        }
    }
}
