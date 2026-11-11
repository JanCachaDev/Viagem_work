<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cuzk;

use App\Domain\Parcel\LiveCadastreUnavailable;
use App\Infrastructure\Cuzk\InspireWfsLiveCadastre;
use App\Infrastructure\Http\CurlHttpClient;
use PHPUnit\Framework\TestCase;

final class InspireWfsLiveCadastreTest extends TestCase
{
    private InspireWfsLiveCadastre $wfs;

    protected function setUp(): void
    {
        $this->wfs = new InspireWfsLiveCadastre(new CurlHttpClient());
    }

    public function testParsesCadastralParcel(): void
    {
        $state = $this->wfs->parse(<<<XML
            <FeatureCollection xmlns="http://www.opengis.net/wfs/2.0" xmlns:cp="http://inspire.ec.europa.eu/schemas/cp/4.0" numberMatched="1">
              <member><cp:CadastralParcel>
                <cp:areaValue uom="m2">1537</cp:areaValue>
                <cp:beginLifespanVersion>2025-01-10T11:35:46Z</cp:beginLifespanVersion>
                <cp:label>1854/42</cp:label>
              </cp:CadastralParcel></member>
            </FeatureCollection>
            XML);

        self::assertNotNull($state);
        self::assertSame('1854/42', $state->label);
        self::assertSame(1537, $state->area);
        self::assertSame('2025-01-10', $state->validSince);
    }

    public function testEmptyResultMeansParcelDoesNotExist(): void
    {
        self::assertNull($this->wfs->parse('<FeatureCollection xmlns="http://www.opengis.net/wfs/2.0" numberMatched="0"/>'));
    }

    public function testServiceErrorIsReported(): void
    {
        $this->expectException(LiveCadastreUnavailable::class);
        $this->wfs->parse('<ExceptionReport xmlns="http://www.opengis.net/ows/1.1"><Exception/></ExceptionReport>');
    }

    public function testInvalidXmlIsReported(): void
    {
        $this->expectException(LiveCadastreUnavailable::class);
        $this->wfs->parse('<html>502 Bad Gateway');
    }
}
