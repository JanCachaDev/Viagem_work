<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk\Vfr;

use DOMElement;
use DOMXPath;
use RuntimeException;

final readonly class VfrElement
{
    public function __construct(
        private DOMXPath $xpath,
        private DOMElement $root,
    ) {
    }

    public function value(string $path, ?DOMElement $context = null): ?string
    {
        $node = $this->xpath->query($path, $context ?? $this->root)->item(0);

        return $node === null ? null : trim($node->textContent);
    }

    public function element(string $path): DOMElement
    {
        $node = $this->xpath->query($path, $this->root)->item(0);

        return $node instanceof DOMElement ? $node : throw new RuntimeException("Chybí prvek {$path}");
    }

    /** @return list<DOMElement> */
    public function elements(string $path): array
    {
        $result = [];
        foreach ($this->xpath->query($path, $this->root) as $node) {
            if ($node instanceof DOMElement) {
                $result[] = $node;
            }
        }

        return $result;
    }
}
