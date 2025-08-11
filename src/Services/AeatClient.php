<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use UXML\UXML;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\RegistrationType;

/**
 * Class to communicate with the AEAT web service endpoint for VERI*FACTU
 */
class AeatClient {
    const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_SUM = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
    const NS_SUM1 = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    private readonly ComputerSystem $system;
    private readonly FiscalIdentifier $taxpayer;
    private ?FiscalIdentifier $representative = null;
    private readonly Client $client;
    private bool $isProduction = true;

    /**
     * Class constructor
     *
     * @param ComputerSystem   $system       Computer system details
     * @param FiscalIdentifier $taxpayer     Taxpayer details (party that issues the invoices)
     * @param string           $certPath     Path to encrypted PEM certificate or PKCS#12 bundle
     * @param string|null      $certPassword Certificate password or `null` for none
     */
    public function __construct(
        ComputerSystem $system,
        FiscalIdentifier $taxpayer,
        string $certPath,
        ?string $certPassword = null,
    ) {
        $this->system = $system;
        $this->taxpayer = $taxpayer;
        $this->client = new Client([
            'cert' => ($certPassword === null) ? $certPath : [$certPath, $certPassword],
            'headers' => [
                'User-Agent' => "Mozilla/5.0 (compatible; {$system->name}/{$system->version})",
            ],
        ]);
    }

    //para llevarme el xml
    private ?string $lastRequestXml = null;

    /**
     * Set representative
     *
     * NOTE: Requires the represented fiscal entity to fill the "GENERALLEY58" form at AEAT.
     *
     * @param  FiscalIdentifier|null $representative Representative details (party that sends the invoices)
     * @return $this                                 This instance
     */
    public function setRepresentative(?FiscalIdentifier $representative): static {
        $this->representative = $representative;
        return $this;
    }

    /**
     * Set production environment
     *
     * @param  bool  $production Pass `true` for production, `false` for testing
     * @return $this             This instance
     */
    public function setProduction(bool $production): static {
        $this->isProduction = $production;
        return $this;
    }

    /**
     * Send registration records
     *
     * @param  RegistrationRecord[] $records Registration records
     * @return UXML                          XML response from web service
     * @throws GuzzleException if request failed
     */
    public function sendRegistrationRecords(array $records): UXML {
        // Build initial request
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum'     => self::NS_SUM,
            'xmlns:sum1'    => self::NS_SUM1,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        // Add header
        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $this->taxpayer->nif);
        if ($this->representative !== null) {
            $representanteElement = $cabeceraElement->add('sum1:Representante');
            $representanteElement->add('sum1:NombreRazon', $this->representative->name);
            $representanteElement->add('sum1:NIF', $this->representative->nif);
        }

        // Add registration records
        foreach ($records as $record) {
            $recordElement = $baseElement->add('sum:RegistroFactura');
            $registroElement = $recordElement->add('sum1:RegistroAlta');
            $registroElement->add('sum1:IDVersion', '1.0');

            // IDFactura
            $idFacturaElement = $registroElement->add('sum1:IDFactura');
            $idFacturaElement->add('sum1:IDEmisorFactura', $record->invoiceId->issuerId);
            $idFacturaElement->add('sum1:NumSerieFactura', $record->invoiceId->invoiceNumber);
            $idFacturaElement->add('sum1:FechaExpedicionFactura', $record->invoiceId->issueDate->format('d-m-Y'));

            // Datos de la factura
            $registroElement->add('sum1:NombreRazonEmisor', $record->issuerName);
            $registroElement->add('sum1:TipoFactura', $record->invoiceType->value);

            if (str_starts_with($record->invoiceType->value, 'R') /*&& $record->rectificationType !== null*/) {
                $registroElement->add('sum1:TipoRectificativa', $record->rectificationType);
            }

            //Todavía no está implementado ya que me falta información de la AEAT 

            /*$tieneExento = count(array_filter($record->breakdown, fn($detalle) =>!empty($detalle->exemptReasonCode))) > 0;

            //fecha operacion
            if($record->operationDate && !$tieneExento)
                $fechaOp = date('d-m-Y', strtotime($record->operationDate));
                !empty($registroElement->add('sum1:FechaOperacion', $fechaOp));*/

            $registroElement->add('sum1:DescripcionOperacion', $record->description);

            if (in_array($record->invoiceType->value, ['F1', 'R1', 'R2', 'R3', 'R4']) && $record->recipient !== null) {
                $destinatarios = $registroElement->add('sum1:Destinatarios');
                $idDestinatario = $destinatarios->add('sum1:IDDestinatario');
                $idDestinatario->add('sum1:NombreRazon', $record->recipient->name);
                $idDestinatario->add('sum1:NIF', $record->recipient->nif);
            }

            // Desglose
            $desgloseElement = $registroElement->add('sum1:Desglose');
            foreach ($record->breakdown as $breakdownDetails) {
                $detalleDesgloseElement = $desgloseElement->add('sum1:DetalleDesglose');
                $detalleDesgloseElement->add('sum1:Impuesto', $breakdownDetails->taxType->value);
                $detalleDesgloseElement->add('sum1:ClaveRegimen', $breakdownDetails->regimeType->value);
                
                //TIPO DE OPERACION
                if($breakdownDetails->exemptReasonCode != ""){
                    $detalleDesgloseElement->add('sum1:OperacionExenta', $breakdownDetails->exemptReasonCode);
                    $detalleDesgloseElement->add('sum1:BaseImponibleOimporteNoSujeto', $breakdownDetails->baseAmount);
                }else
                {
                    $detalleDesgloseElement->add('sum1:CalificacionOperacion', $breakdownDetails->operationType->value);
                    $detalleDesgloseElement->add('sum1:TipoImpositivo', $breakdownDetails->taxRate);
                    $detalleDesgloseElement->add('sum1:BaseImponibleOimporteNoSujeto', $breakdownDetails->baseAmount);
                    $detalleDesgloseElement->add('sum1:CuotaRepercutida', $breakdownDetails->taxAmount);
                }
                
                
            }

            $registroElement->add('sum1:CuotaTotal', $record->totalTaxAmount);
            $registroElement->add('sum1:ImporteTotal', $record->totalAmount);

            // Importe rectificativa (si aplica)
            if (str_starts_with($record->invoiceType->value, 'R') &&
                $record->rectificationType === 'S' &&
                $record->rectifiedBaseAmount !== null &&
                $record->rectifiedTaxAmount !== null) {

                $importeRectificacion = $registroElement->add('sum1:ImporteRectificacion');
                $importeRectificacion->add('sum1:BaseRectificada', $record->rectifiedBaseAmount);
                $importeRectificacion->add('sum1:CuotaRectificada', $record->rectifiedTaxAmount);
                $importeRectificacion->add('sum1:FechaOperacion', $record->operationDate);
            }

            // Encadenamiento
            $encadenamientoElement = $registroElement->add('sum1:Encadenamiento');
            if ($record->previousInvoiceId === null) {
                $encadenamientoElement->add('sum1:PrimerRegistro', 'S');
            } else {
                $registroAnteriorElement = $encadenamientoElement->add('sum1:RegistroAnterior');
                $registroAnteriorElement->add('sum1:IDEmisorFactura', $record->previousInvoiceId->issuerId);
                $registroAnteriorElement->add('sum1:NumSerieFactura', $record->previousInvoiceId->invoiceNumber);
                $registroAnteriorElement->add('sum1:FechaExpedicionFactura', $record->previousInvoiceId->issueDate->format('d-m-Y'));
                $registroAnteriorElement->add('sum1:Huella', $record->previousHash);
            }

            // Sistema Informático
            $sistemaInformaticoElement = $registroElement->add('sum1:SistemaInformatico');
            $sistemaInformaticoElement->add('sum1:NombreRazon', $this->system->vendorName);
            $sistemaInformaticoElement->add('sum1:NIF', $this->system->vendorNif);
            $sistemaInformaticoElement->add('sum1:NombreSistemaInformatico', $this->system->name);
            $sistemaInformaticoElement->add('sum1:IdSistemaInformatico', $this->system->id);
            $sistemaInformaticoElement->add('sum1:Version', $this->system->version);
            $sistemaInformaticoElement->add('sum1:NumeroInstalacion', $this->system->installationNumber);
            $sistemaInformaticoElement->add('sum1:TipoUsoPosibleSoloVerifactu', $this->system->onlySupportsVerifactu ? 'S' : 'N');
            $sistemaInformaticoElement->add('sum1:TipoUsoPosibleMultiOT', $this->system->supportsMultipleTaxpayers ? 'S' : 'N');
            $sistemaInformaticoElement->add('sum1:IndicadorMultiplesOT', $this->system->hasMultipleTaxpayers ? 'S' : 'N');

            $registroElement->add('sum1:FechaHoraHusoGenRegistro', $record->hashedAt->format('c'));
            $registroElement->add('sum1:TipoHuella', '01');
            $registroElement->add('sum1:Huella', $record->hash);
        }

        // Send request
        $response = $this->client->post('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', [
            'base_uri' => $this->getBaseUri(),
            'headers' => ['Content-Type' => 'text/xml'],
            'body'    => $xml->asXML(),
        ]);
        $this->lastRequestXml = $xml->asXML();
        return UXML::fromString($response->getBody()->getContents());
    }

    public function sendRegistrationRecordsRectificativa(array $records): UXML
    {
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum'     => self::NS_SUM,
            'xmlns:sum1'    => self::NS_SUM1,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

         // Add header
        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $this->taxpayer->nif);
        if ($this->representative !== null) {
            $representanteElement = $cabeceraElement->add('sum1:Representante');
            $representanteElement->add('sum1:NombreRazon', $this->representative->name);
            $representanteElement->add('sum1:NIF', $this->representative->nif);
        }

        foreach ($records as $record) {

            $recordElement = $baseElement->add('sum:RegistroFactura');
            $registroElement = $recordElement->add('sum1:RegistroAlta');
            $registroElement->add('sum1:IDVersion', '1.0');

            // IDFactura
            $idFacturaElement = $registroElement->add('sum1:IDFactura');
            $idFacturaElement->add('sum1:IDEmisorFactura', $record->invoiceId->issuerId);
            $idFacturaElement->add('sum1:NumSerieFactura', $record->invoiceId->invoiceNumber);
            $idFacturaElement->add('sum1:FechaExpedicionFactura', $record->invoiceId->issueDate->format('d-m-Y'));
  
            $registroElement->add('sum1:NombreRazonEmisor', $record->issuerName);
            $registroElement->add('sum1:TipoFactura', $record->invoiceType->value);

            if (str_starts_with($record->invoiceType->value, 'R') /*&& $record->rectificationType !== null*/) {
                $registroElement->add('sum1:TipoRectificativa', $record->rectificationType);
            }
            

            $facturasSustituidas = $registroElement->add('sum1:FacturasRectificadas');
            $idFacturaSustituida = $facturasSustituidas->add('sum1:IDFacturaRectificada');
            $idFacturaSustituida->add('sum1:IDEmisorFactura', $record->invoiceIdRectified->issuerId);
            $idFacturaSustituida->add('sum1:NumSerieFactura', $record->invoiceIdRectified->invoiceNumber);
            $idFacturaSustituida->add('sum1:FechaExpedicionFactura', $record->invoiceIdRectified->issueDate->format('d-m-Y'));

            // Importe rectificativa (si aplica)
            if (str_starts_with($record->invoiceType->value, 'R') && $record->rectificationType === 'S' && $record->rectifiedBaseAmount !== null && $record->rectifiedTaxAmount !== null) {

                $importeRectificacion = $registroElement->add('sum1:ImporteRectificacion');
                $importeRectificacion->add('sum1:BaseRectificada', $record->rectifiedBaseAmount);
                $importeRectificacion->add('sum1:CuotaRectificada', $record->rectifiedTaxAmount);
                //$importeRectificacion->add('sum1:FechaOperacion', $record->operationDate);
            }

            //$registroElement->add('sum1:FechaOperacion', $record->operationDate); //TODO: LA FECHA DE LA OPERACIÓN NO PUEDE SER SUPERIOR A LA FACTURA EN MUCHAS OCASIONES, REVISAR CON LA AEAT

            $registroElement->add('sum1:DescripcionOperacion', $record->description);

            if (in_array($record->invoiceType->value, ['F1', 'R1', 'R2', 'R3', 'R4']) && $record->recipient !== null) {
                $destinatarios = $registroElement->add('sum1:Destinatarios');
                $idDestinatario = $destinatarios->add('sum1:IDDestinatario');
                $idDestinatario->add('sum1:NombreRazon', $record->recipient->name);
                $idDestinatario->add('sum1:NIF', $record->recipient->nif);
            }

            // Desglose
            $desgloseElement = $registroElement->add('sum1:Desglose');
            foreach ($record->breakdown as $breakdownDetails) {
                $detalleDesgloseElement = $desgloseElement->add('sum1:DetalleDesglose');
                $detalleDesgloseElement->add('sum1:Impuesto', $breakdownDetails->taxType->value);
                $detalleDesgloseElement->add('sum1:ClaveRegimen', $breakdownDetails->regimeType->value);
                
                //TIPO DE OPERACION
                if($breakdownDetails->exemptReasonCode != ""){
                    $detalleDesgloseElement->add('sum1:OperacionExenta', $breakdownDetails->exemptReasonCode);
                    $detalleDesgloseElement->add('sum1:BaseImponibleOimporteNoSujeto', $breakdownDetails->baseAmount);
                }else
                {
                    $detalleDesgloseElement->add('sum1:CalificacionOperacion', $breakdownDetails->operationType->value);
                    $detalleDesgloseElement->add('sum1:TipoImpositivo', $breakdownDetails->taxRate);
                    $detalleDesgloseElement->add('sum1:BaseImponibleOimporteNoSujeto', $breakdownDetails->baseAmount);
                    $detalleDesgloseElement->add('sum1:CuotaRepercutida', $breakdownDetails->taxAmount);
                }
                
            }

            $registroElement->add('sum1:CuotaTotal', $record->totalTaxAmount);
            $registroElement->add('sum1:ImporteTotal', $record->totalAmount);

            
            if ($record->previousInvoiceId === null) {
                throw new \InvalidArgumentException('En REGISTRO_R1_SUSTITUCION es obligatorio Encadenamiento/RegistroAnterior');
            }
            $enc = $registroElement->add('sum1:Encadenamiento');
            $ra = $enc->add('sum1:RegistroAnterior');
            $ra->add('sum1:IDEmisorFactura', $record->previousInvoiceId->issuerId);
            $ra->add('sum1:NumSerieFactura', $record->previousInvoiceId->invoiceNumber);
            $ra->add('sum1:FechaExpedicionFactura', $record->previousInvoiceId->issueDate->format('d-m-Y'));
            $ra->add('sum1:Huella', $record->previousHash);

            // SistemaInformatico dentro de RegistroAnulacion
            $sis = $registroElement->add('sum1:SistemaInformatico');
            $sis->add('sum1:NombreRazon', $this->system->vendorName);
            $sis->add('sum1:NIF', $this->system->vendorNif);
            $sis->add('sum1:NombreSistemaInformatico', $this->system->name);
            $sis->add('sum1:IdSistemaInformatico', $this->system->id);
            $sis->add('sum1:Version', $this->system->version);
            $sis->add('sum1:NumeroInstalacion', $this->system->installationNumber);
            $sis->add('sum1:TipoUsoPosibleSoloVerifactu', $this->system->onlySupportsVerifactu ? 'S' : 'N');
            $sis->add('sum1:TipoUsoPosibleMultiOT', $this->system->supportsMultipleTaxpayers ? 'S' : 'N');
            $sis->add('sum1:IndicadorMultiplesOT', $this->system->hasMultipleTaxpayers ? 'S' : 'N');

            $registroElement->add('sum1:FechaHoraHusoGenRegistro', $record->hashedAt->format('c'));
            $registroElement->add('sum1:TipoHuella', '01');
            $registroElement->add('sum1:Huella', $record->hash);

        }

        $response = $this->client->post('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', [
            'base_uri' => $this->getBaseUri(),
            'headers'  => ['Content-Type' => 'text/xml'],
            'body'     => $xml->asXML(),
        ]);
        $this->lastRequestXml = $xml->asXML();

        return UXML::fromString($response->getBody()->getContents());
    }


    public function sendRegistrationRecordsAnulate(array $records): UXML 
    {
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum'     => self::NS_SUM,
            'xmlns:sum1'    => self::NS_SUM1,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        $cabecera = $baseElement->add('sum:Cabecera');
        $obligado = $cabecera->add('sum1:ObligadoEmision');
        $obligado->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligado->add('sum1:NIF', $this->taxpayer->nif);
        if ($this->representative !== null) {
            $rep = $cabecera->add('sum1:Representante');
            $rep->add('sum1:NombreRazon', $this->representative->name);
            $rep->add('sum1:NIF', $this->representative->nif);
        }

        foreach ($records as $record) {

            $recordElement = $baseElement->add('sum:RegistroFactura');

            $raElem = $recordElement->add('sum1:RegistroAnulacion');
            $raElem->add('sum1:IDVersion', '1.0');

            // IDFactura (***Anulada***)
            $idFactura = $raElem->add('sum1:IDFactura');
            $idFactura->add('sum1:IDEmisorFacturaAnulada', $record->invoiceId->issuerId);
            $idFactura->add('sum1:NumSerieFacturaAnulada', $record->invoiceId->invoiceNumber);
            $idFactura->add('sum1:FechaExpedicionFacturaAnulada', $record->invoiceId->issueDate->format('d-m-Y'));

            // Encadenamiento obligatorio dentro de RegistroAnulacion
            if ($record->previousInvoiceId === null) {
                throw new \InvalidArgumentException('En REGISTRO_ANULACION es obligatorio Encadenamiento/RegistroAnterior');
            }
            $enc = $raElem->add('sum1:Encadenamiento');
            $ra = $enc->add('sum1:RegistroAnterior');
            $ra->add('sum1:IDEmisorFactura', $record->previousInvoiceId->issuerId);
            $ra->add('sum1:NumSerieFactura', $record->previousInvoiceId->invoiceNumber);
            $ra->add('sum1:FechaExpedicionFactura', $record->previousInvoiceId->issueDate->format('d-m-Y'));
            $ra->add('sum1:Huella', $record->previousHash);

            // SistemaInformatico dentro de RegistroAnulacion
            $sis = $raElem->add('sum1:SistemaInformatico');
            $sis->add('sum1:NombreRazon', $this->system->vendorName);
            $sis->add('sum1:NIF', $this->system->vendorNif);
            $sis->add('sum1:NombreSistemaInformatico', $this->system->name);
            $sis->add('sum1:IdSistemaInformatico', $this->system->id);
            $sis->add('sum1:Version', $this->system->version);
            $sis->add('sum1:NumeroInstalacion', $this->system->installationNumber);
            $sis->add('sum1:TipoUsoPosibleSoloVerifactu', $this->system->onlySupportsVerifactu ? 'S' : 'N');
            $sis->add('sum1:TipoUsoPosibleMultiOT', $this->system->supportsMultipleTaxpayers ? 'S' : 'N');
            $sis->add('sum1:IndicadorMultiplesOT', $this->system->hasMultipleTaxpayers ? 'S' : 'N');

            $raElem->add('sum1:FechaHoraHusoGenRegistro', $record->hashedAt->format('c'));
            $raElem->add('sum1:TipoHuella', '01');
            $raElem->add('sum1:Huella', $record->hash);

        }

        $response = $this->client->post('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', [
            'base_uri' => $this->getBaseUri(),
            'headers'  => ['Content-Type' => 'text/xml'],
            'body'     => $xml->asXML(),
        ]);
        $this->lastRequestXml = $xml->asXML();

        return UXML::fromString($response->getBody()->getContents());
    }


    //me llevo el xml para guardarlo fuera y no volver a construirlo
    public function getLastRequestXml(): ?string {
        return $this->lastRequestXml;
    }

    /**
     * Get base URI of web service
     *
     * @return string Base URI
     */
    private function getBaseUri(): string {
        return $this->isProduction ? 'https://www1.aeat.es' : 'https://prewww1.aeat.es';
    }
}
