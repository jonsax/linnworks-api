<?php

namespace Jonsax\Linnworks;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class Factory
{
    private $application_id;
    private $application_secret;
    private $secret;
    var $dev = false;
    var $debug = 0;

    var $timeout = 60;

    private $headers;
    private $server = "https://eu1.linnworks.net/";
    private $auth_url = "https://api.linnworks.net/";
    private $client;
    private $token;
    private $lui;

    private $authorization;

    public function __construct($config)
    {
        $this->application_id = $config->application_id;
        $this->application_secret = $config->application_secret;

        $this->secret = $config->secret;

        $this->timeout = (isset($config->timeout) ? $config->timeout : $this->timeout);

        $this->dev = (isset($config->dev) ? $config->dev : $this->dev);
        $this->debug = (isset($config->debug) ? $config->debug : $this->debug);

        $this->headers = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent' => "Launch Solutions API Client",
            "Referer" => "https://www.linnworks.net/",
            'Accept-Encoding' => 'gzip, deflate',
            //  'Authorization' => $this->authorization,
            //      'Content-Length'=>strlen($data),
            //      'content' => $data
        ];
        
    }

    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    public function setAuthorization($authorization)
    {
        $this->authorization = $authorization;
    }

    public function Authorize()
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->server,
            'timeout' => $this->timeout,
            'debug' => $this->debug,
            'http_errors' => false
        ]);

        $data = http_build_query(array(
            'applicationId' => $this->application_id,
            'applicationSecret' => $this->application_secret,
            'token' => $this->secret
        ));

        // get auth token
        $response = $client->request('POST', $this->auth_url . "api/Auth/AuthorizeByApplication", [
                'headers' => $this->headers,
                'query' => $data
            ]
        );

        $statuscode = $response->getStatusCode();
        $body = json_decode($response->getBody());

        if ($statuscode != 200) {
            if (isset($body->Message)) {
                $error = 'Problem connecting to linnworks server: ' . $body->Message . " (" . $statuscode . ")";
            } else {
                $error = 'Problem connecting to linnworks server: (' . $statuscode . ")";
            }
            throw new \Exception($error);
        }

        if (isset($body->Server)) {
            $this->server = $body->Server . "/";
        }

        $this->authorization = $body->Token;
        $this->lui = $body->UserId;
        return $body;

    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new \GuzzleHttp\Client([
                'base_uri' => $this->server,
                'timeout' => $this->timeout,
                'debug' => $this->debug,
                'http_errors' => false
            ]);

            if (!$this->authorization) {

                $this->Authorize();
            }

            $this->headers['Authorization'] = $this->authorization;
        }
        return $this->client;

    }


    public function sendPost($endpoint, $data, $body = false)
    {
        $request = [
            'headers' => $this->headers,
            'debug' => $this->debug,
            'query' => $data
        ];

        if ($body) {

            $request['body'] = $body;
        }

        $response = $this->getClient()->request('POST', $this->server . $endpoint, $request
        );

        $statuscode = $response->getStatusCode();

        if ($statuscode != 200 && $statuscode != 404) {

            if (isset($body->Message)) {

                $error = 'Problem connecting to linnworks server: ' . $body->Message . " (" . $statuscode . ")";

            } else {

                $error = 'Problem connecting to linnworks server: (' . $statuscode . ")";

            }
            throw new \Exception($error);

        }

        $this->raw_result = $response->getBody();
        $this->result = json_decode($this->raw_result);

        return $this->result;
    }

    public function sendBodyPost($endpoint, $body)
    {
        $request = [
            'headers' => $this->headers,
            'debug' => $this->debug,
        ];

        if ($body) {
            $request['body'] = $body;
        }

        $response = $this->getClient()->request('POST', $this->server . $endpoint, $request
        );

        $statuscode = $response->getStatusCode();

        if ($statuscode != 200 && $statuscode != 404) {
            if (isset($body->Message)) {
                $error = 'Problem connecting to linnworks server: ' . $body->Message . " (" . $statuscode . ")";
            } else {
                $error = 'Problem connecting to linnworks server: (' . $statuscode . ")";
            }
            throw new \Exception($error);

        }

        $this->raw_result = $response->getBody();
        $this->result = json_decode($this->raw_result);

        return $this->result;
    }


    public function getOrder($endpoint, $data)
    {
        $response = $this->getClient()->request('POST', $this->server . "Orders/GetOrder", [
                'headers' => $headers,
                'query' => $data
            ]
        );

        $order = json_decode($response->getBody());
        return $order;

    }

}