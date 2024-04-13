<?php

namespace Ispahbod\HttpManager;

use GuzzleHttp\Psr7\Response as HttpResponse;
use Countable;
use IteratorAggregate;
use ArrayIterator;

class ResponseCollection implements Countable, IteratorAggregate
{
    protected array $responses = [];
    protected float $executionTime = 0.0;

    public function add(HttpResponse $response): self
    {
        $this->responses[] = $response;
        return $this;
    }

    public function addExecutionTime(float $executionTime): self
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->responses);
    }

    public function count(): int
    {
        return count($this->responses);
    }

    public function isEmpty(): bool
    {
        return empty($this->responses);
    }

    public function getFirstResponse(): ?HttpResponse
    {
        return $this->responses[0] ?? null;
    }

    public function getLastResponse(): ?HttpResponse
    {
        return end($this->responses) ?: null;
    }

    public function filterResponses(callable $callback): array
    {
        return array_filter($this->responses, $callback);
    }

    public function getResponses(): array
    {
        return $this->responses;
    }

    public function getSuccessfulResponses(): array
    {
        return $this->filterResponses(function ($response) {
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        });
    }

    public function getFailedResponses(): array
    {
        return $this->filterResponses(function ($response) {
            return $response->getStatusCode() >= 400;
        });
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }
}