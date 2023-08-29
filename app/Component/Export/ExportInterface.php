<?php

namespace App\Component\Export;

interface ExportInterface
{
    function extension(): string;

    public function exportToFile(iterable $data, ?array $title = null): string;

    public function exportToOss(iterable $data, ?array $title = null, ?string $path = null): string;
}