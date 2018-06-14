<?php
namespace Jonsax\Linnworks;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\ResultSet\HydratingResultSet;
use Zend\Db\TableGateway\Feature\RowGatewayFeature;


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
 
    
    private $db;
    private $dbparams;
    
    private $linnworksproductsTable;
    
    private $productsTable;
    private $logTable;
    
    
    public function __construct($config) {
    
    //    $this->application_id = $config->linnworks->application_id;
    //    $this->application_secret = $config->linnworks->application_secret;
        
        foreach ($config->linnworks as $key => $value) {
            $this->$key = $value;
        }
        
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
        
        $this->dbparams = $config->doctrine->connection->orm_default->params;
        
    }
    
    public function setSecret($secret) {
        $this->secret = $secret;
    }
    public function setAuthorization($authorization) {
        $this->authorization = $authorization;
    }
    
    public function Authorize ()
    {
        $client = new \GuzzleHttp\Client(
            [
                'base_uri' => $this->server,
                'timeout' => $this->timeout,
                'debug' => $this->guzzle_debug
            ]);
        
        
        $query = array(
                'applicationId' => $this->application_id,
                'applicationSecret' => $this->application_secret,
                'token' => $this->token
                
        );
        $data = http_build_query($query);
        
        // get auth token
        $response = $client->request('POST',
            $this->auth_url . "api/Auth/AuthorizeByApplication",
            [
                'headers' => $this->headers,
                'query' => $data
            ]);
        
        $body = json_decode($response->getBody());
        // print_r($body);
        
        $this->authorization = $body->Token;
        $this->lui = $body->UserId;
        $this->server = $body->Server."/api/";
        
        $this->getLogTable()->insert(array(
            "created_at"=> date("Y-m-d H:i:s"),
            "request" => json_encode($data),
            "response" => json_encode($body),
            "url" => "api/Auth/AuthorizeByApplication",
            "status" => 1
        ));
        
        return $body;
    }
    
    public function XAuthorize() {
        
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
    
    //    $this->server = "https://eu1.linnworks.net/api/";

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
    
    public function getDb ()
    {
        
        if (! $this->db) {
            
            $this->db = new \Zend\Db\Adapter\Adapter(
                array(
                    'driver' => 'pdo_mysql',
                    'database' => $this->dbparams->dbname,
                    'username' => $this->dbparams->user,
                    'password' => $this->dbparams->password,
                    'hostname' => $this->dbparams->host
                ));
        }
        
        return $this->db;
    }
    
    public function getLogTable ()
    {
        if (! $this->logTable) {
            $this->logTable = new TableGateway('log', $this->getDb(), null,
                new HydratingResultSet());
        }
        
        return $this->logTable;
    }
    
    
    
    
    public function getProductsTable ()
    {
        if (! $this->productsTable) {
            $this->productsTable= new TableGateway('products', $this->getDb(),
                null, new HydratingResultSet());
        }
        
        return $this->productsTable;
        
    }
    
    
    public function getLinnworksProductsTable ()
    {
        
        if (! $this->linnworksproductsTable) {
            $this->linnworksproductsTable= new TableGateway('linnworks_products', $this->getDb(),
                null, new HydratingResultSet());
        }
        
        return $this->linnworksproductsTable;
        
    }

    
}