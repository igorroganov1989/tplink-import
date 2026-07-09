<?php

declare(strict_types=1);

namespace Yd\TplinkImport\Service\Parser;

use Bitrix\Main\Web\HttpClient;
use Yd\TplinkImport\Domain\Config\ImportConfig;

final class HttpFetcher
{
    private bool $useCache;

    public function __construct(?bool $useCache = null)
    {
        $this->useCache = $useCache ?? !class_exists(HttpClient::class);
    }

    public function get(string $url): string
    {
        if ($this->useCache) {
            $cached = $this->readCache($url);
            if ($cached !== null) {
                return $cached;
            }
        }

        $body = class_exists(HttpClient::class)
            ? $this->getViaBitrix($url)
            : $this->getViaCurl($url);

        if ($this->useCache) {
            $this->writeCache($url, $body);
        }

        return $body;
    }

    private function getViaBitrix(string $url): string
    {
        $http = new HttpClient([
            'socketTimeout' => ImportConfig::HTTP_SOCKET_TIMEOUT,
            'streamTimeout' => ImportConfig::HTTP_STREAM_TIMEOUT,
            'disableSslVerification' => false,
            'redirect' => true,
            'redirectMax' => 5,
            'headers' => [
                'User-Agent' => ImportConfig::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ru-KZ,ru;q=0.9,en;q=0.8',
            ],
        ]);

        $body = $http->get($url);
        if ($body === false) {
            $error = $http->getError();
            throw new \RuntimeException(sprintf('HTTP request failed for %s: %s', $url, $error[0] ?? 'unknown'));
        }

        $status = $http->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('HTTP %d for %s', $status, $url));
        }

        return $body;
    }

    private function cacheDirectory(): string
    {
        return dirname(__DIR__, 5) . '/cache/http';
    }

    private function cachePath(string $url): string
    {
        return $this->cacheDirectory() . '/' . hash('sha256', $url) . '.html';
    }

    private function readCache(string $url): ?string
    {
        $path = $this->cachePath($url);
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }

    private function writeCache(string $url, string $body): void
    {
        $directory = $this->cacheDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        file_put_contents($this->cachePath($url), $body);
    }

    private function getViaCurl(string $url): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('curl_init failed for ' . $url);
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => ImportConfig::HTTP_SOCKET_TIMEOUT,
            CURLOPT_TIMEOUT => ImportConfig::HTTP_STREAM_TIMEOUT,
            CURLOPT_USERAGENT => ImportConfig::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: ru-KZ,ru;q=0.9,en;q=0.8',
            ],
        ]);

        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new \RuntimeException(sprintf('HTTP request failed for %s: %s', $url, $error));
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('HTTP %d for %s', $status, $url));
        }

        return $body;
    }
}
