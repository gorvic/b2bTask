<?php
namespace Model;

use function is_object, is_array;
use function property_exists, method_exists;
use function ucfirst, lcfirst;

class Castable
{
    public function __construct($object = null)
    {
        if (method_exists($this, "init")) {
            $this->init();
        }
        $this->cast($object);
    }

    public function cast($object): void
    {
        if (is_array($object) || is_object($object)) {
            foreach ($object as $key => $value) {
                $setter = "set".ucfirst($key);
                $property = lcfirst($key);
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                } elseif (property_exists($this, $property)) {
                    $this->$property = $value;
                }
            }
        }
    }
}