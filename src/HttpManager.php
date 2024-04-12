<?php

namespace Ispahbod\HttpManager;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;

class HttpManager
{
    protected Client $client;
    protected array $queues = [];

    public function __construct()
    {
        $this->client = new Client();
    }

    public function queue($method, $url, $options = []): self
    {
        $this->queues[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
        ];
        return $this;
    }

    public function sendSync(): array
    {
        $responses = [];
        foreach ($this->queues as $request) {
            try {
                $response = $this->client->request($request['method'], $request['url'], $request['options']);
                $responses[] = $response;
            } catch (GuzzleException $e) {
                $responses[] = new Response(400, [], $e->getMessage());
            }
        }
        $this->queues = []; 
        return $responses;
    }

    public function sendAsync(): array
    {
        $promises = [];
        foreach ($this->queues as $request) {
            $promises[] = $this->client->requestAsync($request['method'], $request['url'], $request['options']);
        }
        try {
            $responses = Utils::unwrap($promises);
        } catch (GuzzleException $e) {
            $responses = [new Response(400, [], $e->getMessage())];
        }
        $this->queues = []; 
        return $responses;
    }
}
