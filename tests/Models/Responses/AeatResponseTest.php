<?php
namespace josemmo\Verifactu\Tests\Models;

use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\Responses\AeatResponse;
use josemmo\Verifactu\Models\Responses\ItemStatus;
use josemmo\Verifactu\Models\Responses\RecordType;
use josemmo\Verifactu\Models\Responses\ResponseStatus;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class AeatResponseTest extends TestCase {
    public function testParsesCorrectResponse(): void {
        $xml = UXML::fromString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tikR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd" xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
            <env:Header/>
            <env:Body Id="Body">
                <tikR:RespuestaRegFactuSistemaFacturacion xmlns:tikR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd" xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
                    <tikR:CSV>A-86U4KHPACUMVZE</tikR:CSV>
                    <tikR:DatosPresentacion>
                        <tik:NIFPresentador>A00000000</tik:NIFPresentador>
                        <tik:TimestampPresentacion>2025-10-13T12:34:56+02:00</tik:TimestampPresentacion>
                    </tikR:DatosPresentacion>
                    <tikR:Cabecera>
                        <tik:ObligadoEmision>
                            <tik:NombreRazon>Perico de los Palotes, S.A.</tik:NombreRazon>
                            <tik:NIF>A00000000</tik:NIF>
                        </tik:ObligadoEmision>
                    </tikR:Cabecera>
                    <tikR:TiempoEsperaEnvio>60</tikR:TiempoEsperaEnvio>
                    <tikR:EstadoEnvio>Correcto</tikR:EstadoEnvio>
                    <tikR:RespuestaLinea>
                        <tikR:IDFactura>
                            <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                            <tik:NumSerieFactura>TEST-202510-123</tik:NumSerieFactura>
                            <tik:FechaExpedicionFactura>13-10-2025</tik:FechaExpedicionFactura>
                        </tikR:IDFactura>
                        <tikR:Operacion>
                            <tik:TipoOperacion>Alta</tik:TipoOperacion>
                            <tik:Subsanacion>S</tik:Subsanacion>
                        </tikR:Operacion>
                        <tikR:EstadoRegistro>Correcto</tikR:EstadoRegistro>
                    </tikR:RespuestaLinea>
                    <tikR:RespuestaLinea>
                        <tikR:IDFactura>
                            <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                            <tik:NumSerieFactura>TEST-202510-124</tik:NumSerieFactura>
                            <tik:FechaExpedicionFactura>13-10-2025</tik:FechaExpedicionFactura>
                        </tikR:IDFactura>
                        <tikR:Operacion>
                            <tik:TipoOperacion>Alta</tik:TipoOperacion>
                            <tik:Subsanacion>N</tik:Subsanacion>
                        </tikR:Operacion>
                        <tikR:EstadoRegistro>Correcto</tikR:EstadoRegistro>
                    </tikR:RespuestaLinea>
                    <tikR:RespuestaLinea>
                        <tikR:IDFactura>
                            <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                            <tik:NumSerieFactura>TEST-202510-120</tik:NumSerieFactura>
                            <tik:FechaExpedicionFactura>11-10-2025</tik:FechaExpedicionFactura>
                        </tikR:IDFactura>
                        <tikR:Operacion>
                            <tik:TipoOperacion>Anulacion</tik:TipoOperacion>
                            <tik:Subsanacion>N</tik:Subsanacion>
                        </tikR:Operacion>
                        <tikR:EstadoRegistro>Correcto</tikR:EstadoRegistro>
                    </tikR:RespuestaLinea>
                </tikR:RespuestaRegFactuSistemaFacturacion>
            </env:Body>
        </env:Envelope>
        XML);
        $response = AeatResponse::from($xml);

        $this->assertEquals('A-86U4KHPACUMVZE', $response->csv);
        $this->assertNotNull($response->submittedAt);
        $this->assertEquals('2025-10-13T12:34:56+02:00', $response->submittedAt->format('Y-m-d\TH:i:sP'));
        $this->assertEquals(60, $response->waitSeconds);
        $this->assertEquals(ResponseStatus::Correct, $response->status);
        $this->assertEquals(3, count($response->items));

        $this->assertEquals(true, $response->items[0]->isCorrection);
        $this->assertEquals('A00000000', $response->items[0]->invoiceId->issuerId);
        $this->assertEquals('TEST-202510-123', $response->items[0]->invoiceId->invoiceNumber);
        $this->assertEquals('2025-10-13 00:00:00', $response->items[0]->invoiceId->issueDate->format('Y-m-d H:i:s'));
        $this->assertEquals(RecordType::Registration, $response->items[0]->recordType);
        $this->assertEquals(ItemStatus::Correct, $response->items[0]->status);
        $this->assertEquals(null, $response->items[0]->errorCode);
        $this->assertEquals(null, $response->items[0]->errorDescription);

        $this->assertEquals(false, $response->items[1]->isCorrection);
        $this->assertEquals('TEST-202510-124', $response->items[1]->invoiceId->invoiceNumber);
        $this->assertEquals(RecordType::Registration, $response->items[1]->recordType);
        $this->assertEquals(ItemStatus::Correct, $response->items[1]->status);

        $this->assertEquals(false, $response->items[2]->isCorrection);
        $this->assertEquals('TEST-202510-120', $response->items[2]->invoiceId->invoiceNumber);
        $this->assertEquals(RecordType::Cancellation, $response->items[2]->recordType);
        $this->assertEquals(ItemStatus::Correct, $response->items[2]->status);
    }

    public function testParsesIncorrectResponse(): void {
        $xml = UXML::fromString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tikR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd" xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
            <env:Header/>
            <env:Body Id="Body">
                <tikR:RespuestaRegFactuSistemaFacturacion xmlns:tikR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd" xmlns:tik="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">
                    <tikR:Cabecera>
                        <tik:ObligadoEmision>
                            <tik:NombreRazon>Perico de los Palotes, S.A.</tik:NombreRazon>
                            <tik:NIF>A00000000</tik:NIF>
                        </tik:ObligadoEmision>
                    </tikR:Cabecera>
                    <tikR:TiempoEsperaEnvio>60</tikR:TiempoEsperaEnvio>
                    <tikR:EstadoEnvio>Incorrecto</tikR:EstadoEnvio>
                    <tikR:RespuestaLinea>
                        <tikR:IDFactura>
                            <tik:IDEmisorFactura>A00000000</tik:IDEmisorFactura>
                            <tik:NumSerieFactura>NO-EXISTE</tik:NumSerieFactura>
                            <tik:FechaExpedicionFactura>11-10-2025</tik:FechaExpedicionFactura>
                        </tikR:IDFactura>
                        <tikR:Operacion>
                            <tik:TipoOperacion>Anulacion</tik:TipoOperacion>
                        </tikR:Operacion>
                        <tikR:EstadoRegistro>Incorrecto</tikR:EstadoRegistro>
                        <tikR:CodigoErrorRegistro>3002</tikR:CodigoErrorRegistro>
                        <tikR:DescripcionErrorRegistro>No existe el registro de facturación.</tikR:DescripcionErrorRegistro>
                    </tikR:RespuestaLinea>
                </tikR:RespuestaRegFactuSistemaFacturacion>
            </env:Body>
        </env:Envelope>
        XML);
        $response = AeatResponse::from($xml);

        $this->assertEquals(null, $response->csv);
        $this->assertEquals(null, $response->submittedAt);
        $this->assertEquals(60, $response->waitSeconds);
        $this->assertEquals(ResponseStatus::Incorrect, $response->status);
        $this->assertEquals(1, count($response->items));

        $this->assertEquals(false, $response->items[0]->isCorrection);
        $this->assertEquals('NO-EXISTE', $response->items[0]->invoiceId->invoiceNumber);
        $this->assertEquals(RecordType::Cancellation, $response->items[0]->recordType);
        $this->assertEquals(ItemStatus::Incorrect, $response->items[0]->status);
        $this->assertEquals('3002', $response->items[0]->errorCode);
        $this->assertEquals('No existe el registro de facturación.', $response->items[0]->errorDescription);
    }

    public function testHandlesServerErrors(): void {
        $xml = UXML::fromString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
            <env:Body>
                <env:Fault>
                    <faultcode>env:Server</faultcode>
                    <faultstring>Codigo[20009].Error interno en el servidor</faultstring>
                </env:Fault>
            </env:Body>
        </env:Envelope>
        XML);
        try {
            AeatResponse::from($xml);
            $this->fail('Did not throw exception for server error response');
        } catch (AeatException $e) {
            $this->assertStringContainsString('Codigo[20009].Error interno en el servidor', $e->getMessage());
        }
    }
}
