<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk\Vfr;

use DOMElement;
use InvalidArgumentException;

/**
 * Převádí GML 3.2 plochy z VFR (Polygon, MultiSurface, včetně oblouků) na WKT.
 * Oblouky zůstávají jako CIRCULARSTRING, linearizaci dělá PostGIS (ST_CurveToLine).
 */
final class GmlToWkt
{
    public function convert(DOMElement $geometry): string
    {
        return match ($geometry->localName) {
            'Polygon' => $this->polygon($geometry),
            'MultiSurface' => $this->multiSurface($geometry),
            default => throw new InvalidArgumentException("Nepodporovaná geometrie: {$geometry->localName}"),
        };
    }

    private function multiSurface(DOMElement $multi): string
    {
        $polygons = [];
        foreach ($this->children($multi, 'surfaceMember') as $member) {
            foreach ($this->children($member, 'Polygon') as $polygon) {
                $polygons[] = $this->polygon($polygon);
            }
        }
        if ($polygons === []) {
            throw new InvalidArgumentException('Prázdný MultiSurface');
        }

        return 'MULTISURFACE(' . implode(',', $polygons) . ')';
    }

    private function polygon(DOMElement $polygon): string
    {
        $rings = [];
        $curved = false;
        foreach ($polygon->childNodes as $boundary) {
            if (!$boundary instanceof DOMElement || !in_array($boundary->localName, ['exterior', 'interior'], true)) {
                continue;
            }
            foreach ($boundary->childNodes as $ring) {
                if ($ring instanceof DOMElement) {
                    [$wkt, $isCurved] = $this->ring($ring);
                    $rings[] = $wkt;
                    $curved = $curved || $isCurved;
                }
            }
        }

        return ($curved ? 'CURVEPOLYGON(' : 'POLYGON(') . implode(',', $rings) . ')';
    }

    /** @return array{string, bool} */
    private function ring(DOMElement $ring): array
    {
        if ($ring->localName === 'LinearRing') {
            return [$this->linear($this->posList($ring)), false];
        }

        $segments = [];
        foreach ($this->children($ring, 'curveMember') as $member) {
            foreach ($member->childNodes as $curve) {
                if ($curve instanceof DOMElement) {
                    array_push($segments, ...$this->curveSegments($curve));
                }
            }
        }

        $hasArc = in_array(true, array_column($segments, 'arc'), true);
        if (!$hasArc) {
            $points = [];
            foreach ($segments as $segment) {
                $points = $this->appendPoints($points, $segment['points']);
            }

            return [$this->linear($points), false];
        }

        $parts = array_map(
            fn (array $s) => $s['arc'] ? 'CIRCULARSTRING' . $this->linear($s['points']) : $this->linear($s['points']),
            $segments,
        );

        return [count($parts) === 1 && $segments[0]['arc'] ? $parts[0] : 'COMPOUNDCURVE(' . implode(',', $parts) . ')', true];
    }

    /** @return list<array{arc: bool, points: list<array{string, string}>}> */
    private function curveSegments(DOMElement $curve): array
    {
        if ($curve->localName === 'LineString') {
            return [['arc' => false, 'points' => $this->posList($curve)]];
        }
        if ($curve->localName !== 'Curve') {
            throw new InvalidArgumentException("Nepodporovaná křivka: {$curve->localName}");
        }

        $result = [];
        foreach ($this->children($curve, 'segments') as $segments) {
            foreach ($segments->childNodes as $segment) {
                if (!$segment instanceof DOMElement) {
                    continue;
                }
                $points = $this->posList($segment);
                $result[] = match ($segment->localName) {
                    'LineStringSegment' => ['arc' => false, 'points' => $points],
                    'ArcString' => ['arc' => true, 'points' => $points],
                    'Circle' => ['arc' => true, 'points' => self::fullCircle($points)],
                    default => throw new InvalidArgumentException("Nepodporovaný segment: {$segment->localName}"),
                };
            }
        }

        return $result;
    }

    /**
     * GML Circle je zadaný třemi body na kružnici, WKT potřebuje uzavřený CIRCULARSTRING
     * (start, protilehlý bod, start).
     *
     * @param list<array{string, string}> $points
     * @return list<array{string, string}>
     */
    public static function fullCircle(array $points): array
    {
        if (count($points) !== 3) {
            throw new InvalidArgumentException('Circle musí mít 3 body');
        }
        [[$ax, $ay], [$bx, $by], [$cx, $cy]] = array_map(fn ($p) => [(float) $p[0], (float) $p[1]], $points);

        $d = 2 * ($ax * ($by - $cy) + $bx * ($cy - $ay) + $cx * ($ay - $by));
        if (abs($d) < 1e-12) {
            throw new InvalidArgumentException('Body kružnice leží na přímce');
        }
        $a2 = $ax ** 2 + $ay ** 2;
        $b2 = $bx ** 2 + $by ** 2;
        $c2 = $cx ** 2 + $cy ** 2;
        $ux = ($a2 * ($by - $cy) + $b2 * ($cy - $ay) + $c2 * ($ay - $by)) / $d;
        $uy = ($a2 * ($cx - $bx) + $b2 * ($ax - $cx) + $c2 * ($bx - $ax)) / $d;

        $opposite = [self::number(2 * $ux - $ax), self::number(2 * $uy - $ay)];

        return [$points[0], $opposite, $points[0]];
    }

    /** @return list<array{string, string}> */
    private function posList(DOMElement $element): array
    {
        $posList = $element->getElementsByTagNameNS('*', 'posList')->item(0);
        if ($posList === null) {
            throw new InvalidArgumentException("Chybí posList v {$element->localName}");
        }
        $values = preg_split('/\s+/', trim($posList->textContent));
        if (count($values) % 2 !== 0) {
            throw new InvalidArgumentException('Lichý počet souřadnic');
        }

        return array_chunk($values, 2);
    }

    /**
     * @param list<array{string, string}> $points
     * @param list<array{string, string}> $next
     * @return list<array{string, string}>
     */
    private function appendPoints(array $points, array $next): array
    {
        if ($points !== [] && $next !== [] && end($points) === $next[0]) {
            array_shift($next);
        }

        return [...$points, ...$next];
    }

    /** @param list<array{string, string}> $points */
    private function linear(array $points): string
    {
        return '(' . implode(',', array_map(fn ($p) => $p[0] . ' ' . $p[1], $points)) . ')';
    }

    /** @return iterable<DOMElement> */
    private function children(DOMElement $parent, string $localName): iterable
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                yield $child;
            }
        }
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
