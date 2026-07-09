<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Import;

final class ImportLogger
{
    public function write(array $payload): string
    {
        $directory = $_SERVER['DOCUMENT_ROOT'] . '/local/logs';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create log directory: ' . $directory);
        }

        $filename = sprintf(
            'tplink_import_%s.json',
            (new \DateTimeImmutable('now'))->format('Y-m-d_H-i-s')
        );
        $path = $directory . '/' . $filename;

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode import log JSON');
        }

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException('Failed to write import log: ' . $path);
        }

        return $path;
    }
}
