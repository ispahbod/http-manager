<?php

namespace Ispahbod\HttpManager;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Ispahbod\HttpManager\HttpResponse;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Psr\Http\Message\ResponseInterface;

class HttpManager
{
    protected HttpClient $httpClient;
    protected array $requestQueue = [];
    protected float $timeout = 0.0; // Added a property to store timeout

    public function __construct(float $timeout = 0.0) // Allow timeout to be set on instantiation
    {
        $this->httpClient = new HttpClient(['timeout' => $timeout]);
        $this->timeout = $timeout;
    }

    public function setTimeout(float $timeout): self // Method to update timeout
    {
        $this->timeout = $timeout;
        $this->httpClient = new HttpClient(['timeout' => $this->timeout]); // Re-instantiate HttpClient with new timeout
        return $this;
    }

    public function addToQueue($httpMethod, $requestUrl, $requestOptions = []): self
    {
        $this->requestQueue[] = [
            'method' => strtoupper($httpMethod),
            'url' => $requestUrl,
            'options' => array_merge($requestOptions, ['timeout' => $this->timeout]), // Merge timeout into requestOptions
        ];
        return $this;
    }

    public function executeSingleRequest($httpMethod, $requestUrl, $requestOptions = []): HttpResponse
    {
        $startTime = microtime(true);
        try {
            $response = $this->httpClient->request($httpMethod, $requestUrl, array_merge($requestOptions, ['timeout' => $this->timeout])); // Merge timeout into requestOptions
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            return new HttpResponse($response->getStatusCode(), $response->getHeaders(), $response->getBody(), $executionTime);
        } catch (GuzzleException $exception) {
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            return new HttpResponse(400, [], $exception->getMessage(), $executionTime);
        }
    }

    public function executeSinglePostRequest($requestUrl, $requestOptions = []): HttpResponse
    {
      return  $this->executeSingleRequest('post',$requestUrl,$requestOptions);
    }
    public function executeSingleGetRequest($requestUrl, $requestOptions = []): HttpResponse
    {
      return  $this->executeSingleRequest('get',$requestUrl,$requestOptions);
    }
    public function executeSynchronousRequests(): ResponseCollection
    {
        $startTime = microtime(true);
        $collectedResponses = new ResponseCollection();
        foreach ($this->requestQueue as $queuedRequest) {
            $response = $this->executeSingleRequest($queuedRequest['method'], $queuedRequest['url'], $queuedRequest['options']);
            $collectedResponses->add($response);
        }
        $endTime = microtime(true);
        $totalExecutionTime = $endTime - $startTime;
        $this->requestQueue = [];
        return $collectedResponses->addExecutionTime($totalExecutionTime);
    }

    public function executeAsynchronousRequests(): ResponseCollection
    {
        $startTime = microtime(true);
        $promiseQueue = [];
        foreach ($this->requestQueue as $queuedRequest) {
            $promiseQueue[] = $this->httpClient->requestAsync($queuedRequest['method'], $queuedRequest['url'], $queuedRequest['options']);
        }
        $collectedResponses = new ResponseCollection();
        try {
            $responses = PromiseUtils::unwrap($promiseQueue);
            foreach ($responses as $response) {
                $formattedResponse = new HttpResponse($response->getStatusCode(), $response->getHeaders(), $response->getBody());
                $collectedResponses->add($formattedResponse);
            }
        } catch (GuzzleException $exception) {
            $collectedResponses->add(new HttpResponse(400, [], $exception->getMessage()));
        }
        $endTime = microtime(true);
        $totalExecutionTime = $endTime - $startTime;
        $this->requestQueue = [];
        return $collectedResponses->addExecutionTime($totalExecutionTime);
    }
}
