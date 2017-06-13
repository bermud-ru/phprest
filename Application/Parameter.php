<?php
/**
 * Parameter.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Parameter helper for \Application\Rest
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 01/08/2016
 *
 */

namespace Application;

class Parameter implements \JsonSerializable
{
    protected $owner = null;
    protected $index = null;
    protected $name = null;
    protected $alias = null;
    protected $default = null;
    protected $key = false;
    protected $validator = null;
    protected $message = null;
    protected $alert = '';
    protected $requered = false;
    protected $isEmpty = false;
    protected $notValid = false;
    protected $before = null;
    protected $after = null;
    protected $formatter = null;
    protected $raw = null;

    public $value = null;

    /**
     * Parameter constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */
    public function __construct( \Application\PHPRoll &$owner, array $opt ){
        $this->owner = $owner;

        foreach ($opt as $k => $v) if (property_exists($this, $k)) $this->{$k} = $v;

        $this->raw = isset($this->owner->params[$this->name]) ? $this->owner->params[$this->name] : null;
        $this->value = empty($this->raw) ? $this->default : $this->raw ;
        if (is_callable($this->before)) $this->value = call_user_func_array($this->before, $this->arguments($this->before));

        if ($this->requered && empty($this->owner->params[$this->name])) {
            $this->isEmpty = true;
            $this->setMessage($this->formatter(isset($opt['message']) ? $opt['message'] : "Parameter error %(name)s !", ['name' => $this->name]));
        }

        if (!empty($this->validator) && ($this->requered || $this->key || !empty($this->owner->params[$this->name]))) {
            if (is_callable($this->validator)) {
                $this->notValid = !call_user_func_array($this->validator, $this->arguments($this->validator));
            } elseif (is_string($this->validator) && !preg_match($this->validator, $this->value)) {
                $this->notValid = true;
            }
            if ($this->notValid ) $this->setMessage($this->formatter(isset($opt['message']) ? $opt['message'] : "Parameter error %(name)s!",['name' => $this->name, 'value'=>$this->value]));
        }

        if (is_callable($this->after)) $this->value = call_user_func_array($this->after, $this->arguments($this->after));
        if (!isset($this->owner->params[$this->alias])) $this->owner->params[$this->alias] = $this;
        else $this->owner->params[$this->name] = $this;
    }

    /**
     * Prepare args for closure
     *
     * @param callable $fn
     * @return array
     */
    protected function arguments(callable &$fn): array
    {
        return array_map(function (&$item) {
            switch (strtolower($item->name)) {
                case 'header': $item->value = $this->owner->header; break;
                case 'params': $item->value = $this->owner->params; break;
                case 'db':
                    $this->owner->config['db'] = !empty($item->value) ? $item->value : $this->owner->config['db'];
                    $item->value = isset($this->owner->db) ? $this->owner->db : new \Application\Db($this->owner, true);
                    break;
                case 'self': $item->value = $this; break;
                case 'owner': $item->value = $this->owner; break;
                case 'raw': $item->value = $this->raw; break;
                case 'user': $item->value = $this->owner->acl ?? []; break;
                default:
                    $name = explode(\Application\PHPRoll::KEY_SEPARATOR, strtolower($this->name));
                    if (strtolower($item->name) == end($name) || (!empty($this->alias) && strtolower($item->name) == strtolower($this->alias)) ) {
                    $item->value = $this->value;
                } else { $item = null; }
            } return $item->value;
        }, (new \ReflectionFunction($fn))->getParameters());
    }

    /**
     * Set error message
     * @param $e
     */
    public function setMessage($e)
    {
        $this->alert = $e;
    }

    /**
     * Event on error inti parameter
     *
     * @param array $error
     */
    public function onError(array &$error)
    {
       if ($this->alert) $error[$this->name] = $this->alert;
    }

    /**
     *
     * @return null
     */
    public function __toString(): string
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter, $this->arguments($this->formatter));
        return strval($this->value);
    }

    /**
     * \JsonSerializable interface release
     * @return mixed|null
     */
    public function jsonSerialize() 
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter, $this->arguments($this->formatter));
        return $this->raw;
    }
    /**
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array 
    {
        return [$this->alias ?? $this->name => $this->restore ? $this->raw : $this->value];
    }

    /**
     * formatter
     *
     * @param string $pattern
     * @param array $properties
     * @return bool|mixed
     */
    public static function formatter($pattern, array $properties)
    {
        if ($pattern && count($properties)) {
            $keys = array_keys($properties);
            $keysmap = array_flip($keys);
            $values = array_values($properties);
            while (preg_match('/%\(([a-zA-Z0-9_ -]+)\)/', $pattern, $m)) {
                if (!isset($keysmap[$m[1]]))  $pattern = str_replace($m[0], '% - $', $pattern);
                else $pattern = str_replace($m[0], '%' . ($keysmap[$m[1]] + 1) . '$', $pattern);
            }
            array_unshift($values, $pattern);
            return call_user_func_array('sprintf', $values);
        } else {
            return $pattern;
        }
    }

    /**
     * PHPRoll Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ( $name )
    {
        if ($this->owner instanceof \Application\PHPRoll && property_exists($this->owner, $name)) {
            return $this->owner->{$name};
        }
        throw new \Exception(__CLASS__."->$name property not foudnd!");
    }

    /**
     * PHPRoll Native method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($this->owner instanceof \Application\PHPRoll && method_exists($this->owner, $name)) return call_user_func_array(array($this->owner, $name), $arguments);
        throw new \Exception(__CLASS__."->$name(...) method not foudnd");
    }

    /**
     * PHPRoll Native static method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (method_exists(\Application\PHPRoll, $name)) return call_user_func_array(array(\Application\PHPRoll, $name), $arguments);
        throw new \Exception(__CLASS__."::$name(...) method not foudnd");
    }

}
?>