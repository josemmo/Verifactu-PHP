<?php
namespace josemmo\Verifactu\Services;

use DateTimeInterface;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\RegistrationRecord;

/**
 * Service to generate QR code URLs.
 *
 * By default uses the production environment operating in online mode (VeriFactu mode).
 */
class QrGenerator {
    private bool $isProduction = true;
    private bool $isOnlineMode = true;

    /**
     * Set production environment
     *
     * @param bool $production Pass `true` for production, `false` for testing
     *
     * @return $this This instance
     */
    public function setProduction(bool $production): static {
        $this->isProduction = $production;
        return $this;
    }

    /**
     * Set online mode
     *
     * @param bool $onlineMode Pass `true` for online mode (VeriFactu), `false` for offline mode (No VeriFactu)
     *
     * @return $this This instance
     */
    public function setOnlineMode(bool $onlineMode): static {
        $this->isOnlineMode = $onlineMode;
        return $this;
    }

    /**
     * Generate URL from registration record
     *
     * @param RegistrationRecord $record Registration record
     *
     * @return string QR code URL
     */
    public function fromRegistrationRecord(RegistrationRecord $record): string {
        return $this->fromInvoiceId($record->invoiceId, $record->totalAmount);
    }

    /**
     * Generate URL from invoice ID and amount
     *
     * @param InvoiceIdentifier $invoiceId Invoice ID
     * @param string            $amount    Invoice amount
     *
     * @return string QR code URL
     */
    public function fromInvoiceId(InvoiceIdentifier $invoiceId, string $amount): string {
        return $this->from($invoiceId->issuerId, $invoiceId->invoiceNumber, $invoiceId->issueDate, $amount);
    }

    /**
     * Generate URL from raw parameters
     *
     * @param string            $issuerId      Invoice issuer ID
     * @param string            $invoiceNumber Invoice number
     * @param DateTimeInterface $issueDate     Invoice issue date
     * @param string            $amount        Invoice total amount
     *
     * @return string QR code URL
     */
    public function from(string $issuerId, string $invoiceNumber, DateTimeInterface $issueDate, string $amount): string {
        $url  = $this->isProduction ? 'https://www2.agenciatributaria.gob.es' : 'https://prewww2.aeat.es';
        $url .= '/wlpl/TIKE-CONT/';
        $url .= $this->isOnlineMode ? 'ValidarQR' : 'ValidarQRNoVerifactu';
        $url .= '?' . http_build_query([
            'nif' => $issuerId,
            'numserie' => $invoiceNumber,
            'fecha' => $issueDate->format('d-m-Y'),
            'importe' => $amount,
        ]);
        return $url;
    }
}
