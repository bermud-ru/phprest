<?php
/**
 * Rest.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)  PHPRole extend (Backend RESTfull)
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 07/07/2016
 * @status beta
 * @version 0.1.2
 * @revision $Id: Rest.php 0004 2017-07-24 23:44:01Z $
 *
 */
namespace Application;

class Rest
{
    protected $acl = [];
    protected $method = 'GET';
    protected $action = null;
    protected $checkPermission = true;
    protected $request = [];
    protected $opt = [];
    protected $is_filter = false;

    public $owner = null;
    // Авторизованный пользватель, выполняющий Rest action
    public $user = null;
    // Контейнер сообщений об ошибках
    public $error = [];

    /**
     * Rest constructor.
     *
     * @param \Application\PHPRoll $owner
     * @param array $opt
     */
    public function __construct(\Application\PHPRoll &$owner, array $opt)
    {
        $this->owner = $owner;
        $this->request = $this->owner->params ?? [];
        if (isset($owner->acl) && $owner->acl) $this->user = $owner->acl; else $this->user = new \Application\ACL($owner, true);

        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        if (!isset($opt[$this->method]) && !isset($opt[$this->method]['action']) && !is_callable($opt[$this->method]['action'])) {
            $this->error = ['code' => '404','message'=>"//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI] action[$this->method] not supported"];
        } else {
            $this->checkPermission = isset($opt[$this->method]['permission']) ? boolval($opt[$this->method]['permission']) :
                                   ( isset($opt['permission']) ? boolval($opt['permission']) : true );
            $this->opt = $opt;
            $this->acl = array_merge($opt['acl'] ?? [], $opt[$this->method]['acl'] ?? []);
            $this->action = $opt[$this->method]['action'];
        }
    }

    /**
     * @function initParams
     * Params init
     *
     * @param array $params
     * @param $source
     * @return mixed
     */
    protected function initParams(array $params, &$source)
    {
        foreach ($params as $k => $v) {
            if ( is_array($v) && (isset($source[$v['name']]) || (isset($v['alias']) && isset($source[$v['alias']])) ) ) {
                $param = (new \Application\Parameter($v,$source))->setOwner($this);
                if ($this->is_filter && $param->value === null) {
                    unset($source[(isset($v['alias']) ? $v['alias']:$v['name'])]);
                } else {
                    $source[(isset($v['alias']) ? $v['alias'] : $v['name'])] = $param;
                }
            }
        }
        return $source;
    }

    /**
     * @function getParams
     * Получаем массив Поле-Значение REST action
     *
     * @param array $params
     * @param bool $empty
     * @return array
     */
    protected function getParams(array &$params, $is_filter = true): array
    {
        $result = [];

        if (count($params)) {
            foreach ($params as $k => $v) {
                if (is_array($v['name'])) {
                    $fields = array_flip($v['name']);
                    if ($is_filter) {
                        $p = array_intersect_key($this->request, $fields);
                        $s = array_slice(($t = array_filter($p, function ($v) {
                            return ($v !== null && $v !== '');
                        }, ARRAY_FILTER_USE_BOTH)), 0, 1);
//                        if (count($s)) $v['name'] = key($s); else $v['name'] = key($p);
                        foreach ($s as $k1 => $v1) if (!isset($result[$k1])) {
                            $opt =  ['name'=>$k1];
                            if (isset($v['alias'])) {
                                $opt['alias'] = preg_replace('/\(.*\)/U', $k1, $v['alias']);
                                $result[ $opt['alias']] = $v1;
                            } else {
                                $result[ $k1] = $v1;
                            }
                            $params[] = array_merge($v, $opt);
                        }
                    } else {
                        foreach ($fields as $k1 => $v1) {
                            $value = isset($this->request[$v1]) ? $this->request[$v1] : null;

                            if ((is_null($value) || $value == '') && isset($v['default'])) {
                                $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this->owner), $this->arguments($v['default'])) : $v['default'];
                            }
                            $opt = ['name'=>$v1];
                            if (isset($v['alias'])) {
                                $opt['alias'] = preg_replace('/\(.*\)/U', $v1, $v['alias']);
                                $result[$opt['alias']] = $value;
                            } else {
                                $result[ $v1] = $value;
                            }
                            $params[] = array_merge($v, $opt);
                        }
                    }
                } else {
                    $value = isset($this->request[$v['name']]) ? $this->request[$v['name']] : null;

                    if ((is_null($value) || $value == '') && isset($v['default'])) {
                        $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this->owner), $this->arguments($v['default'])) : $v['default'];
                    }

                    $this->is_filter = $is_filter;

                    if (($is_filter && $value !== null && $value !== '') || !$is_filter) {
                        $result[(isset($v['alias']) ? preg_replace('/\(.*\)/U', $v['name'], $v['alias']) : $v['name'])] = $value;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @function dispatcher
     * Диспетчер REST запросов [GET| PUT| POST| DELETE]
     *
     * @param array $opt
     * @return mixed
     */
    public function dispatcher(array $opt=[])
    {
        if ($this->checkPermission && !$this->isAllow($opt['field'] ?? '')) {
            $this->owner->response_header['Action-Status'] = 'Permission denied!';
            $result = ['result'=> 'error', 'message' => 'Отказано в доступе / Permission denied'];
        } else {
            $arg = $this->arguments($this->action);
            if (count($this->error)) {
                $result = ['result'=> 'error', 'message' => $this->error];
            } else {
                $result = call_user_func_array($this->action->bindTo($this), $arg);
            }
        }

        $type = 'json';
        if (isset($result['Content-Type'])) {
            $type = strtolower($result['Content-Type']);
            unset($result['Content-Type']);
        }
        return $this->owner->response($type, $result);
    }

    /**
     * @function isAllow
     * Check ACL allow
     *
     * @param string $field
     * @return bool
     */
    protected function isAllow(string $field): bool
    {
        if (!count($this->acl)) return true;
        if (!$this->user || empty($field)) return false;
        return $this->user->in($field, $this->acl);
    }

    /**
     * @function filter
     *
     * @param array $p
     * @param callable|null $cb
     * @return array
     */
    public function filter(array &$p, callable  $cb = null): array
    {
//        if (is_null($cb)) $cb = function($v){return $v !== false && !is_null($v) && ($v != '' || $v == '0'); };
        if (is_null($cb)) $cb = function($v){return $v !== null && $v !== ''; };

        return array_filter($p, $cb,ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @function paramsBykey
     *
     * @param $pattern
     * @param $a
     * @return array
     */
    protected function paramsBykey($pattern, $a)
    {
        $keys = array_values(preg_grep($pattern, array_keys($a)));

        if (count($keys)) return [$keys[0],$a[$keys[0]]];
        return [null,null];
    }

    /**
     * @function arguments
     * Prepare args for closure
     *
     * @param callable $fn
     * @return array
     */
    protected function arguments(callable &$fn): array
    {
        return array_map(function (&$item) {
            $name  = strtolower($item->name);
            switch ($name){
                case 'header':
                    $item->value = $this->owner->header??[];
                    break;
                case 'cfg':
                    $item->value = $this->owner->config??[];
                    break;
                case 'db':
                    try {
                        $item->value = isset($this->owner->{$item->name}) ? $this->owner->{$item->name} : new \Application\PDA($this->owner->config[$item->name]);
                    } catch (\Exception $e) {
                        $this->error['ACL'] = addslashes($e->getMessage());
                        $item->value = null;
                    }
                    break;
                case 'error':
                    $item->value = $this->error;
                    break;
                case 'owner':
                    $item->value = $this->owner;
                    break;
                case 'user':
                    $item->value = $this->user ?? [];
//                    $item->value = $this->owner->user ?? [];
                    break;
                default:
                    list($key, $params) = $this->paramsBykey("/^!*$name$/i", $this->opt[$this->method]);
                    if (is_null($key)) list($key, $params) = $this->paramsBykey("/^!*$name$/i", $this->opt);
                    if (is_null($key)) {
                        $item->value = null;
                    } else {
                        $is_filter = strpos($key, '!') !== false; //TODO: fprefix & sufix
                        if (is_array($params)) {
                            $swap = $this->getParams($params, $is_filter);
                            $item->value = $this->initParams($params,$swap );
                        }
                        else $item->value = [];
                    }
            }
            return $item->value;
        }, (new \ReflectionFunction($fn))->getParameters());
    }

    /**
     * Build params set from custom request array and params rules
     *
     * @param array $request
     * @param array $params
     * @param bool $flag
     * @return array
     */
//    public function __invoke(array $request, array $params, $flag = true ): array
//    {
//        $this->request = $request;
//        $swap = $this->getParams($params, $flag);
//        return $this->initParams($params,$swap );
//    }

    /**
     * PHPRoll Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ( $name )
    {
//        if (property_exists($this->owner, $name)) {
//            return $this->owner->{$name};
//        }
//        throw new \Exception(__CLASS__."->$name property not foudnd!");
        $value = null;

        switch (strtolower($name)) {
//            case 'owner':
//                $item->value = $this->owner;
//                break;
            case 'header':
                $value = $this->owner->header??[];
                break;
            case 'cfg':
                $value = $this->owner->config??[];
                break;
            case (strpos($name, 'db') === 0 ? true: false):
                $value = isset($this->owner->{$name}) ? $this->owner->{$name} : new \Application\PDA($this->owner->config[$name]);
                break;
            case 'error':
                $value = $this->error;
                break;
            case 'user':
                $value = $this->user ?? [];
                break;
            default:
                list($key, $params) = $this->paramsBykey("/^!*$name$/i", $this->opt[$this->method]);
                if (is_null($key)) list($key, $params) = $this->paramsBykey("/^!*$name$/i", $this->opt);

                if (is_null($key)) {
                    $value = null;
                } else {
                    $is_filter = strpos($key, '!') !== false; //TODO: fprefix & sufix
                    if (is_array($params)) {
                        $swap = $this->getParams($params, $is_filter);
                        $value = $this->initParams($params,$swap );
                        var_dump(['params'=>$params, 'sourse'=>$swap] );exit;
                    }
                    else $value = [];
                }
        }

        return $value;
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
//        if (method_exists($this->owner, $name)) return call_user_func_array(array($this->owner, $name), $arguments);
//        throw new \Exception(__CLASS__."->$name(...) method not foudnd");

        if (isset($this->opt[$name]) && is_callable($this->opt[$name])) return call_user_func_array($this->opt[$name]->bindTo($this), $arguments);
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