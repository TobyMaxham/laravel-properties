<?php

namespace TobyMaxham\LaravelProperties\Traits;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use TobyMaxham\LaravelProperties\Exceptions\InvalidObjectTypeException;
use TobyMaxham\LaravelProperties\Exceptions\InvalidAttributeTypeException;

/**
 * @mixin Model
 *
 * @property array|null $properties
 */
trait UseProperties
{
    /**
     * @return mixed
     */
    public function getProperty(string $key, $default = null)
    {
        $properties = $this->properties;
        if (null == $properties || ! is_array($properties)) {
            return $default;
        }

        return Arr::get($properties, $key);
    }

    /**
     * @param  string  $key
     * @param          $value
     * @return void
     * @throws InvalidAttributeTypeException
     * @throws InvalidObjectTypeException
     * @throws Throwable
     */
    public function setProperty(string $key, $value): void
    {
        throw_unless($this instanceof Model, InvalidObjectTypeException::class);

        $properties = $this->properties;
        if (null == $properties) {
            $properties = [];
        } elseif (!is_array($properties)) {
            throw new InvalidAttributeTypeException();
        }

        Arr::set($properties, $key, $value);
        $this->properties = $properties;
    }

    /**
     * @return array|null
     */
    public function emptyProperties()
    {
        $value = $this->properties;
        $this->properties = [];

        return $value;
    }
}
