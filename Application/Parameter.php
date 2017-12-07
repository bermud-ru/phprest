<?php
/**
 * Parameter.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Parameter helper for \Application\Rest
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 01/08/2016
 * @status beta
 * @version 0.1.2
 * @revision $Id: Parameter.php 0004 2017-07-24 23:44:01Z $
 *
 */

namespace Application;

class Parameter implements \JsonSerializable
{
    protected $index = null;
    protected $name = null;
    protected $alias = null;
    protected $default = null;
//    protected $key = false;
    protected $validator = null;
    protected $message = null;
    protected $alert = '';
    protected $required = false;
    protected $isEmpty = false;
    protected $notValid = false;
    protected $before = null;
    protected $after = null;
    protected $formatter = null;
    protected $raw = null;

    public $params = null;
    public $opt = [];
    public $value = null;

    const MESSAGE = "\Application\Parameter::message %(name)s %(value)s!";
    /**
     * Parameter constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */
    public function __construct( array &$params, array $opt ){
        $this->params = &$params;
        $this->opt = $opt;

        foreach ($opt as $k => $v) if (property_exists($this, $k)) $this->{$k} = $v;
//TODO more then one aliases
        $this->raw = isset($params[$this->alias]) ? $params[$this->alias] : $params[$this->name];
        $this->value = is_null($this->raw) ? $this->default : $this->raw ;
        if (is_callable($this->before)) $this->value = call_user_func_array($this->before, $this->arguments($this->before));

        if (is_callable($this->required)) $this->required = call_user_func_array($this->required, $this->arguments($this->required));
        if ($this->required && is_null($this->value)) {
            $this->isEmpty = true;
            $this->setMessage($opt['message'] ?? \Application\Parameter::MESSAGE, ['name' => $this->name, 'value'=>'NULL']);
        }

        if (!empty($this->validator) && ($this->required || !empty($this->params[$this->name]))) {
            if (is_callable($this->validator)) {
                $this->notValid = !call_user_func_array($this->validator, $this->arguments($this->validator));
            } elseif (is_string($this->validator) && !preg_match($this->validator, $this->value)) {
                $this->notValid = true;
            }
            if ($this->notValid && !(isset($params['required']) && $this->required)) $this->setMessage($opt['message'] ?? \Application\Parameter::MESSAGE, ['name' => $this->name, 'value'=>$this->value]);
        }

        if (is_callable($this->after)) $this->value = call_user_func_array($this->after, $this->arguments($this->after));
//TODO more then one aliases
        if ($this->alias) $this->params[$this->alias] = $this;
        else $this->params[$this->name] = $this;

    }

    /**
     * Init contex of property
     *
     * @param $p
     */
    protected function property($name, $default = null)
    {
        if (!property_exists($this, $name)) {
            trigger_error("Application\Parameter::$name not exist!", E_USER_WARNING);
            return $default;
        }

        $result = &$this->{$name};
        if (is_callable($result)) {
            $result = call_user_func_array($result, $this->arguments($result));
            if ($result === false) {
                trigger_error("Application\Parameter::$name run time error!", E_USER_WARNING);
                return $default;
            }
        }

        return $result ?? $default;
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
                case 'params':
                    $item->value = &$this->params;
                    break;
                case 'self':
                    $item->value = &$this;
                    break;
                case 'raw':
                    $item->value = &$this->raw;
                    break;
                default:
                    $name = explode(\Application\PHPRoll::KEY_SEPARATOR, strtolower($this->name));
                    if (strtolower($item->name) == end($name) || (!empty($this->alias) && strtolower($item->name) == strtolower(\Application\Db::field($this->alias))) ) {
                        $item->value = &$this->value;
                    } else {
                        $item->value = null;
                    }
            } return $item->value;
        }, (new \ReflectionFunction($fn))->getParameters());
    }

    /**
     * Set error message
     * @param $e
     */
    public function setMessage($message, $opt)
    {
        $this->alert = \Application\PHPRoll::formatter($message ? $message: "Parameter error %(name)s!", $opt);
    }

    /**
     * Event on error inti parameter
     *
     * @param array $error
     */
    public function onError(array &$error)
    {
        if ($this->alert) {
            if (!isset($error['error'])) $error['error'] = [];
            $error['error'] = array_merge($error['error'], [$this->name => $this->alert]);
        }
    }

    /**
     * __toString
     *
     * @return string
     */
    public function __toString(): string
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter, $this->arguments($this->formatter));
        elseif (is_array($this->value)) return json_encode($this->value);

        return strval($this->value);
    }

    /**
     * __toInt
     *
     * @return int
     */
    public function __toInt(): int
    {
        if (is_numeric($this->value)) return intval($this->value);

        trigger_error("Application\Parameter::__toInt() can't resolve numeric value!", E_USER_WARNING);
        return null;
    }

    /**
     * __toJSON
     *
     * @param string $json
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     * @return null|object
     */
    public function __toJSON(bool $assoc = true , int $depth = 512 , int $options = 0): ?array
    {
        $json = json_decode($this->__toString(), $assoc, $depth, $options);
        return json_last_error() === JSON_ERROR_NONE ? $json : null;
    }

    /**
     * \JsonSerializable interface release
     * @return mixed|null
     */
    public function jsonSerialize() 
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter, $this->arguments($this->formatter));
        elseif (is_array($this->value)) return json_encode($this->value);

        return $this->value;//$this->raw;
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
     *
     * @return array
     */
    public function __debugInfo() {
        return [ $this->alias ?? $this->name => $this->restore ? $this->raw : $this->value ];
    }
}
?>