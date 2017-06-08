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
    var $debug = false;
    
    var $timeout = 30;
    
    private $headers;
    
    private $server = "https://eu1.linnworks.net/";
    
    private $auth_url = "https://api.linnworks.net/";
    private $client;
    private $token;
    private $lui;
    
    private $authorization;
    
    
    public function __construct($config) {
    
        $this->application_id = $config->application_id;
        $this->application_secret = $config->application_secret;
        
        $this->secret = $config->secret;
        
        $this->timeout = (isset($config->timeout)?$config->timeout:$this->timeout);
    
        $this->dev = (isset($config->dev)?$config->dev:$this->dev);
        $this->debug = (isset($config->debug )?$config->debug :$this->debug );
    
        $this->headers = [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent'  => "Launch Solutions API Client",
                "Referer" => "https://www.linnworks.net/",
                'Accept-Encoding' => 'gzip, deflate',
                //  'Authorization' => $this->authorization,
                //      'Content-Length'=>strlen($data),
                //      'content' => $data
        ];
        
    
    }
    
    public function setSecret($secret) {
        $this->secret = $secret;
    }
    public function setAuthorization($authorization) {
        $this->authorization = $authorization;
    }
    public function Authorize() {
        
        $client = new \GuzzleHttp\Client([
                'base_uri' => $this->server,
                'timeout'  => $this->timeout,
                'debug' => $this->debug
        ]);
     
        
        $data = http_build_query(array(
                'applicationId'=> $this->application_id,
                'applicationSecret'=> $this->application_secret,
                'token' => $this->secret
        
        ));
        
        // get auth token
        $response = $client->request('POST', $this->auth_url . "api/Auth/AuthorizeByApplication", [
                'headers'=>$this->headers,
                'query'=>$data
        ]
                );
        

        $body = json_decode($response->getBody());
        
        $this->authorization = $body->Token;
        $this->lui = $body->UserId;
        
        return $body;
        
    }
    
    public function getClient() {
    
        if (!$this->client) {
            $this->client = new \GuzzleHttp\Client([
                    'base_uri' => $this->server,
                    'timeout'  => $this->timeout,
                    'debug' => $this->debug
            ]);
    
            if (!$this->authorization) {
    
                $this->Authorize();
            }
    
    
            $this->headers['Authorization'] = $this->authorization;
        }
    
        return $this->client;
    
    
    }
    
    
    public function sendPost ($endpoint, $data)
    {
    
        $this->server = "https://eu1.linnworks.net/api/";
        
        
        $response = $this->getClient()->request('POST', $this->server.$endpoint, [
                'headers'=>$this->headers,
                'query'=>$data
        ]
                );
    
    
        $this->raw_result = $response->getBody();
        $this->result = json_decode($this->raw_result);
    
    
        return $this->result ;
    }
    
    
    public function getOrder ($endpoint, $data)
    {

        $response = $this->getClient()->request('POST', $this->server."Orders/GetOrder", [
                'headers'=>$headers,
                'query'=>$data
        ]
                );
    
    
        $order = json_decode($response->getBody());
    
    
        return $order;
    
    
    }
    
    
    
    
}