<?php

namespace Bhutanio\Movietvdb\Clients;

use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as RedisClient;

abstract class Client
{
    protected $guzzle;

    protected $redis;

    protected $apiUrl;

    protected $apiKey;

    protected $apiSecure = false;

    public function __construct($apiUrl, $apiKey = null)
    {
        $this->redis = new RedisClient();
        $this->apiUrl = ($this->apiSecure ? 'https://' : 'http://') . $apiUrl;
        $this->apiKey = $apiKey;
        $this->guzzle = new GuzzleClient();
    }

    public function request($url, array $options = [])
    {
        $key = md5($url . serialize($options));
        if ($cache = $this->cache($key)) {
            return $cache;
        }

        $response = $this->guzzle->request('GET', $url, $options);
        $this->validateStatus($response->getStatusCode());

        $content = $response->getBody()->getContents();

        return $this->cache($key, $content);
    }

    public function toArray($string)
    {
        return json_decode($string, true);
    }

    public function toJson(array $array, $options = 0)
    {
        return json_encode($array, $options);
    }

    public function cache($key, $data = null)
    {
        $key = 'movietvdb:' . $key;

        if ($data) {
            $this->redis->setex($key, 86400, serialize($data));

            return $data;
        }

        if ($cache = $this->redis->get($key)) {
            return unserialize($cache);
        }

        return $data;
    }

    protected function validateImdbId($key)
    {
        if (!preg_match('/tt\\d{7}/', $key)) {
            throw new \InvalidArgumentException('Invalid IMDB ID');
        }
    }

    private function validateStatus($statusCode)
    {
        if ($statusCode < 200 && $statusCode > 299) {
            throw new \HttpResponseException('Invalid Status Code');
        }
    }
}