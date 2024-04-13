<?php

namespace Ispahbod\HttpManager;

use JsonException;
use Symfony\Component\DomCrawler\Crawler;

class HttpResponse
{
    protected int $statusCode;
    protected array $headers;
    protected string $body;

    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

    public function getHeader(string $name): ?string
    {
        return $this->hasHeader($name) ? $this->headers[$name][0] : null;
    }

    public function getHeaderLines(string $name): ?array
    {
        return $this->hasHeader($name) ? $this->headers[$name] : null;
    }

    public function getCookies(): array
    {
        if (!$this->hasHeader('Set-Cookie')) {
            return [];
        }
        $cookies = [];
        foreach ($this->getHeaderLines('Set-Cookie') as $cookie) {
            $parts = explode(';', $cookie);
            foreach ($parts as $part) {
                $cookieParts = explode('=', $part, 2);
                if (count($cookieParts) === 2) {
                    $cookies[trim($cookieParts[0])] = trim($cookieParts[1]);
                }
            }
        }
        return $cookies;
    }

    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    public function getContentLength(): ?int
    {
        $length = $this->getHeader('Content-Length');
        return $length !== null ? (int)$length : null;
    }

    public function getAuthorizationToken(): ?string
    {
        return $this->getHeader('Authorization');
    } 

    public function isCacheable(): bool
    {
        $cacheControl = $this->getHeader('Cache-Control');
        return $cacheControl !== null && !str_contains($cacheControl, 'no-store') && !str_contains($cacheControl, 'no-cache');
    }

    public function isJsonResponse(): bool
    {
        $contentType = $this->getContentType();
        return str_contains($contentType, 'application/json');
    }

    /**
     * @throws JsonException
     */
    public function getJsonBody(): ?array
    {
        if ($this->isJsonResponse()) {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getContent(): string
    {
        if (method_exists($this->body, 'getContents')) {
            return $this->body->getContents();
        }
        return $this->body;
    }

    public function createCrawler(): ?Crawler
    {
        if ($this->isHtmlResponse()) {
            return new Crawler($this->body);
        }
        return null;
    }

    public function isHtmlResponse(): bool
    {
        $contentType = $this->getContentType();
        return str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml');
    }

    public function getSize(): ?int
    {
        return method_exists($this->body, 'getSize') ? $this->body->getSize() : null;
    }

    public function getMetadata(string $key = null)
    {
        if (method_exists($this->body, 'getMetadata')) {
            return $key === null ? $this->body->getMetadata() : $this->body->getMetadata($key);
        }
        return null;
    }

    public function isEmpty(): bool
    {
        return $this->getSize() === 0;
    }

    public function isNotEmpty(): bool
    {
        return $this->getSize() !== 0;
    }
}


