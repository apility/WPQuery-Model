<?php

namespace Apility\WPQuery;

use Cache;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;

abstract class Model extends \Jenssegers\Model\Model
{
    protected $host;
    protected $table;
    protected $apiKey;
    protected $guzzle;
    protected $hidden = [];
    protected $attributes = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->apiKey = env('WPQUERY_API_KEY');
        if (is_null($this->apiKey) || empty($this->apiKey)) {
            throw new Exception('No API key supplied');
        }
        $this->host = rtrim($this->host, '/') . '/';
        $this->host .= 'wp-json/wpquery/v1/query/' . $this->table . '/';
        $this->guzzle = new Client([
            'base_uri' => $this->host
        ]);
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public static function all()
    {
        $model = new static;
        $apiKey = $model->apiKey;
        $guzzle = $model->guzzle;
        try {
            return Cache::remember(
                '__wpquery__' . '/' . $model->host . '__all__',
                3600,
                function () use ($model) {
                    $response = $model->guzzle->get('?apikey=' . $model->apiKey)->getBody();
                    return (new Collection(json_decode($response, true)))->map(function ($entry) {
                        return new static($entry);
                    });
                });
        } catch (Exception $ex) {
            return new Collection();
        }
    }

    public static function findOrFail($id)
    {
        $found = self::find($id);
        if (is_null($found)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("");
        }
        return $found;
    }

    public static function find($id)
    {
        try {
            $model = new static;
            return Cache::remember(
                '__wpquery__' . '/' . $model->host . $id,
                3600,
                function () use ($id, $model) {
                    $response = $model->guzzle->get($id . '?apikey=' . $model->apiKey)->getBody();
                    return new static(json_decode($response, true));
                }
            );
        } catch (Exception $ex) {
            return null;
        }
    }

    public function __get($prop)
    {
        if (isset($this->attributes[$prop])) {
            if (method_exists($this, 'get' . $prop . 'attribute')) {
                return $this->{'get' . $prop . 'attribute'}($this->attributes[$prop]);
            }
            return $this->attributes[$prop];
        }
        trigger_error(
            'Undefined property: ' . get_class($this) . '::$' . $prop,
            E_USER_NOTICE
        );
    }
    
    public function __set($prop, $val)
    {
        // Immutable
    }

    public static function first()
    {
        return self::all()[0];
    }

    public function __debugInfo()
    {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden)) {
                $attributes[$key] = $this->__get($key);
            }
        }
        return $attributes;
    }
    
    public function __toString()
    {
        return json_encode($this->__debugInfo());
    }
}
