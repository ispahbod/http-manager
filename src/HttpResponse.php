<?php

namespace Ispahbod\HttpManager;

use JsonException;
use Symfony\Component\DomCrawler\Crawler;

class HttpResponse
{
    protected int $statusCode;
    protected array $headers;
    protected string $body;
    protected float $responseTime;

    public function __construct(int $statusCode, array $headers, string $body, float $responseTime)
    {
        $this->statusCode = $statusCode;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
        $this->responseTime = $responseTime;
    }
    public function getResponseTime(): float
    {
        return round($this->responseTime, 3);
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
        return array_key_exists(strtolower($name), $this->headers);
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        return $this->hasHeader($name) ? $this->headers[$name][0] : null;
    }

    public function getHeaderLines(string $name): ?array
    {
        $name = strtolower($name);
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
            return new Crawler($this->getContent());
        }
        return null;
    }

    public function isHtmlResponse(): bool
    {
        $contentType = $this->getContent();
        return str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml');
    }

    public function getSize(): ?int
    {
        return method_exists($this->body, 'getSize') ? $this->body->getSize() : null;
    }

    public function isEmpty(): bool
    {
        return $this->getSize() === 0;
    }

    public function isNotEmpty(): bool
    {
        return $this->getSize() !== 0;
    }

    // New methods added below

    public function getPageTitle(): ?string
    {
        $crawler = $this->createCrawler();
        if ($crawler) {
            return $crawler->filter('title')->first()->text(null, false);
        }
        return null;
    }

    public function getPageDescription(): ?string
    {
        $crawler = $this->createCrawler();
        if ($crawler) {
            return $crawler->filterXpath('//meta[@name="description"]')->attr('content');
        }
        return null;
    }

    public function getPageFavicon(): ?string
    {
        $crawler = $this->createCrawler();
        if ($crawler) {
            return $crawler->filterXpath('//link[@rel="icon"]')->attr('href');
        }
        return null;
    }
    public function getPageSocialImage(): ?string
    {
        $crawler = $this->createCrawler();
        if ($crawler) {
            return $crawler->filterXpath('//meta[@property="og:image"]')->attr('content');
        }
        return null;
    }
    public function getPageSize(): ?int
    {
        return $this->getContentLength();
    }

    public function getAllMetaData(): array
    {
        $crawler = $this->createCrawler();
        $metaData = [];
        if ($crawler) {
            $crawler->filter('meta')->each(function (Crawler $node) use (&$metaData) {
                $name = $node->attr('name') ?? $node->attr('property');
                if ($name) {
                    $metaData[$name] = $node->attr('content');
                }
            });
        }
        return $metaData;
    }
}