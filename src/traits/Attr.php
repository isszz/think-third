<?php

namespace isszz\third\traits;

trait Attr
{
    protected $attrs = [];

    /**
     * Return the attrs.
     *
     * @return array
     */
    public function getAttrs()
    {
        return $this->attrs;
    }

    /**
     * Return the extra attr.
     *
     * @param string $name
     * @param string $default
     *
     * @return mixed
     */
    public function getAttr($name, $default = null)
    {
        return isset($this->attrs[$name]) ? $this->attrs[$name] : $default;
    }

    /**
     * Set extra attrs.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function setAttr($name, $value)
    {
        $this->attrs[$name] = $value;
        return $this;
    }

    /**
     * Map the given array onto the user's properties.
     *
     * @param array $attrs
     *
     * @return $this
     */
    public function merge(array $attrs)
    {
        $this->attrs = array_merge($this->attrs, $attrs);
        return $this;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->attrs);
    }

    public function offsetGet($offset)
    {
        return $this->getAttr($offset);
    }
    
    public function offsetSet($offset, $value)
    {
        $this->setAttr($offset, $value);
    }
    
    public function offsetUnset($offset)
    {
        unset($this->attrs[$offset]);
    }
    
    public function __get($property)
    {
        return $this->getAttr($property);
    }
    
    public function toArray()
    {
        return $this->getAttrs();
    }
    
    public function toJSON()
    {
        return json_encode($this->getAttrs(), JSON_UNESCAPED_UNICODE);
    }
}
