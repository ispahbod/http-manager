<?php

namespace Ispahbod\HttpManager;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Psr7\Response as HttpResponse;

class RequestManager
{
    protected HttpClient $httpClient;
    protected array $requestQueue = [];

    public function __construct()
    {
        $this->httpClient = new HttpClient();
    }

    public function addToQueue($httpMethod, $requestUrl, $requestOptions = []): self
    {
        $this->requestQueue[] = [
            'method' => $httpMethod,
            'url' => $requestUrl,
            'options' => $requestOptions,
        ];
        return $this;
    }

    public function executeSingleRequest($httpMethod, $requestUrl, $requestOptions = []): HttpResponse
    {
        try {
            $response = $this->httpClient->request($httpMethod, $requestUrl, $requestOptions);
            return new HttpResponse($response->getStatusCode(), $response->getHeaders(), $response->getBody());
        } catch (GuzzleException $exception) {
            return new HttpResponse(400, [], $exception->getMessage());
        }
    }
    public function executeSynchronousRequests(): ResponseCollection
    {
        $collectedResponses = new ResponseCollection();
        foreach ($this->requestQueue as $queuedRequest) {
            $response = $this->executeSingleRequest($queuedRequest['method'], $queuedRequest['url'], $queuedRequest['options']);
            $collectedResponses->add($response);
        }
        $this->requestQueue = [];
        return $collectedResponses;
    }

    public function executeAsynchronousRequests(): ResponseCollection
    {
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
        $this->requestQueue = [];
        return $collectedResponses;
    }
}
