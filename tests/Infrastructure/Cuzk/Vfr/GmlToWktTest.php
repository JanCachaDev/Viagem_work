<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cuzk\Vfr;

use App\Infrastructure\Cuzk\Vfr\GmlToWkt;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

final class GmlToWktTest extends TestCase
{
    private const GML_NS = 'xmlns:gml="http://www.opengis.net/gml/3.2"';

    public function testPolygonWithHole(): void
    {
        $wkt = $this->convert('
            <gml:Polygon ' . self::GML_NS . '>
                <gml:exterior><gml:LinearRing><gml:posList>0 0 10 0 10 10 0 10 0 0</gml:posList></gml:LinearRing></gml:exterior>
                <gml:interior><gml:LinearRing><gml:posList>2 2 4 2 4 4 2 2</gml:posList></gml:LinearRing></gml:interior>
            </gml:Polygon>');

        self::assertSame('POLYGON((0 0,10 0,10 10,0 10,0 0),(2 2,4 2,4 4,2 2))', $wkt);
    }

    public function testRingComposedOfLineStringsIsJoinedWithoutDuplicatePoints(): void
    {
        $wkt = $this->convert('
            <gml:Polygon ' . self::GML_NS . '><gml:exterior><gml:Ring>
                <gml:curveMember><gml:LineString><gml:posList>0 0 10 0 10 10</gml:posList></gml:LineString></gml:curveMember>
                <gml:curveMember><gml:LineString><gml:posList>10 10 0 10 0 0</gml:posList></gml:LineString></gml:curveMember>
            </gml:Ring></gml:exterior></gml:Polygon>');

        self::assertSame('POLYGON((0 0,10 0,10 10,0 10,0 0))', $wkt);
    }

    public function testArcStringProducesCurvePolygon(): void
    {
        $wkt = $this->convert('
            <gml:Polygon ' . self::GML_NS . '><gml:exterior><gml:Ring>
                <gml:curveMember><gml:LineString><gml:posList>0 0 10 0</gml:posList></gml:LineString></gml:curveMember>
                <gml:curveMember><gml:Curve><gml:segments>
                    <gml:ArcString><gml:posList>10 0 5 5 0 0</gml:posList></gml:ArcString>
                </gml:segments></gml:Curve></gml:curveMember>
            </gml:Ring></gml:exterior></gml:Polygon>');

        self::assertSame('CURVEPOLYGON(COMPOUNDCURVE((0 0,10 0),CIRCULARSTRING(10 0,5 5,0 0)))', $wkt);
    }

    public function testCircleBecomesClosedCircularString(): void
    {
        $wkt = $this->convert('
            <gml:Polygon ' . self::GML_NS . '><gml:exterior><gml:Ring>
                <gml:curveMember><gml:Curve><gml:segments>
                    <gml:Circle><gml:posList>1 0 0 1 -1 0</gml:posList></gml:Circle>
                </gml:segments></gml:Curve></gml:curveMember>
            </gml:Ring></gml:exterior></gml:Polygon>');

        self::assertSame('CURVEPOLYGON(CIRCULARSTRING(1 0,-1 0,1 0))', $wkt);
    }

    public function testMultiSurface(): void
    {
        $wkt = $this->convert('
            <gml:MultiSurface ' . self::GML_NS . '>
                <gml:surfaceMember><gml:Polygon><gml:exterior><gml:LinearRing><gml:posList>0 0 1 0 1 1 0 0</gml:posList></gml:LinearRing></gml:exterior></gml:Polygon></gml:surfaceMember>
                <gml:surfaceMember><gml:Polygon><gml:exterior><gml:LinearRing><gml:posList>5 5 6 5 6 6 5 5</gml:posList></gml:LinearRing></gml:exterior></gml:Polygon></gml:surfaceMember>
            </gml:MultiSurface>');

        self::assertSame('MULTISURFACE(POLYGON((0 0,1 0,1 1,0 0)),POLYGON((5 5,6 5,6 6,5 5)))', $wkt);
    }

    private function convert(string $xml): string
    {
        $doc = new DOMDocument();
        $doc->loadXML(trim($xml));
        self::assertInstanceOf(DOMElement::class, $doc->documentElement);

        return (new GmlToWkt())->convert($doc->documentElement);
    }
}
