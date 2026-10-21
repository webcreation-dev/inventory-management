<?php

namespace Modules\PrintManager\Contracts;

interface PrinterInterface
{
    public function getDriverName(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function preparePrint(array $payload, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     */
    public function prepareTestPrint(array $context = []): array;
}
