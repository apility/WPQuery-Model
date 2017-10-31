<?php

namespace Apility\WPQuery;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

abstract class Model extends \Jenssegers\Model\Model
{
    protected $host;
    protected $table;
    protected $guzzle;
    protected $attributes = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->apiKey = env('WPQUERY_API_KEY');
        $this->host .= "wp-json/wpquery/v1/query/$this->table/";
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
        return Cache::remember(
            get_class($model) . '/' . $model->host . '/__all__',
            3600,
            function () use ($model) {
                $response = $model->guzzle->get("?apikey=$model->apiKey")->getBody();
                $collection = new Collection(json_decode($response, true));
                return $collection->map(function ($entry) {
                    return new static($entry);
                });
            }
        );
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
                get_class($model) . '/' . $model->host . '/' . $id,
                3600,
                function () use ($model) {
                    $response = $model->guzzle->get("$id?apikey=$model->apiKey")->getBody();
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
        return $this->attributes;
    }
    
    public function __toString()
    {
        return json_encode($this->__debugInfo());
    }
}
