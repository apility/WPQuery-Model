<?php

namespace Apility\WPQuery;

use Cache;
use Exception;
use Serializable;
use JsonSerializable;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Jsonable;

abstract class Model implements Serializable, JsonAble, JsonSerializable
{
    protected $host;
    protected $table;
    protected $apiKey;
    protected $guzzle;
    protected $hidden = [];
    protected $modified = [];
    protected $attributes = [];
    protected $primaryKey = 'ID';

    public function __construct(array $attributes = [])
    {
        $this->apiKey = env('WPQUERY_API_KEY');
        if (is_null($this->apiKey) || empty($this->apiKey)) {
            throw new Exception('No API key supplied');
        }
        $this->host = rtrim(env('WPQUERY_HOST'), '/') . '/';
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
                '__wpquery__' . '/' . static::class . '/__all__',
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

    public static function where($key, $value)
    {
        try {
            $model = new static;
            return Cache::remember(
                '__wpquery__' . '/' . static::class . '/' . $key . ':' . $value,
                3600,
                function () use ($key, $value, $model) {
                    $response = $model->guzzle->get($key . '/' . $value . '?apikey=' . $model->apiKey)->getBody();
                    return (new Collection(json_decode($response, true)))->map(function ($entry) {
                        return new static($entry);
                    });
                }
            );
        } catch (Exception $ex) {
            throw $ex;
            /* return new Collection(); */
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
                '__wpquery__' . '/' . static::class . '/' . $id,
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
        if (method_exists($this, 'get' . $prop . 'attribute')) {
            if (isset($this->attributes[$prop])) {
                return $this->{'get' . $prop . 'attribute'}($this->attributes[$prop]);
            }
            return $this->{'get' . $prop . 'attribute'}();
        }
        return null;
        /* trigger_error(
            'Undefined property: ' . get_class($this) . '::$' . $prop,
            E_USER_NOTICE
        ); */
    }
    
    public function __set($prop, $value)
    {
        if ($prop !== $this->primaryKey) {
            if (isset($this->attributes[$prop])) {
                if ($value !== $this->attributes[$prop]) {
                    $this->attributes[$prop] = $value;
                    if (method_exists($this, 'set' . $prop . 'attribute')) {
                        $this->{'set' . $prop . 'attribute'}($value);
                    }
                    $this->modified[] = $prop;
                    $this->modified = array_unique($this->modified);
                }
            } else {
                if (method_exists($this, 'set' . $prop . 'attribute')) {
                    $this->{'set' . $prop . 'attribute'}($value);
                } else {
                    $this->attributes[$prop] = $value;
                    $this->modified[] = $prop;
                }
            }
        }
    }

    public function save()
    {
        if (!empty($this->modified)) {
            try {
                $modified = [];
                foreach ($this->modified as $key) {
                    if ($key !== $this->primaryKey) {
                        $modified[$key] = $this->attributes[$key];
                    }
                }
                if (isset($this->attributes[$this->primaryKey])) {
                    $this->guzzle->put(
                        $this->attributes[$this->primaryKey] . '?apikey=' . $this->apiKey,
                        ['json' => $modified]
                    );
                    Cache::forget('__wpquery__' . '/' . static::class . '/__all__');
                    Cache::forget('__wpquery__' . '/' . static::class . '/' . $this->attributes[$this->primaryKey]);
                } else {
                    $result = json_decode($this->guzzle->post(
                        '?apikey=' . $this->apiKey,
                        ['json' => $modified]
                    )->getBody());
                    $this->attributes[$this->primaryKey] = $result->id;
                }
                $attributes = json_decode(
                    $this->guzzle->get(
                        $this->attributes[$this->primaryKey] . '?apikey=' . $this->apiKey
                )->getBody(), true);
                $this->modified = [];
                return true;
            } catch (Exception $ex) {
                throw new Exception('Unable to save. Is write support disabled?');
            }
        }
        return false;
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

    public function jsonSerialize()
    {
        return $this->__debugInfo();
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    
    public function __toString()
    {
        return $this->toJson();
    }

    public function serialize()
    {
        return serialize($this->attributes);
    }

    public function unserialize($data)
    {
        $this->attributes = unserialize($data);
    }
}
