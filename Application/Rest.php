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
    protected $method = 'GET';
    protected $action = null;
    protected $checkPermission = true;
    protected $request = [];
    protected $opt = [];
    protected $groups =[];
    protected $is_filter = false;

    public $owner = null;
    // Контейнер сообщений об ошибках4321q
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

        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        if (!isset($opt[$this->method]) || !isset($opt[$this->method]['action']) || !is_callable($opt[$this->method]['action'])) {
            $this->error = ['REST' => "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI] action[$this->method] not supported"];
        } else {
            $this->checkPermission = isset($opt[$this->method]['permission']) ? boolval($opt[$this->method]['permission']) :
                ( isset($opt['permission']) ? boolval($opt['permission']) : true );
            $this->groups = $opt[$this->method]['groups'] ?? $opt['groups'] ?? [];
            $this->action = $opt[$this->method]['action'];
            $this->opt = new \Application\Jsonb($opt, ['owner'=>$owner]);
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
    protected function initParams(array $params, array &$source)
    {
        foreach ($params as $k => $v) {
            if ( is_array($v) && (array_key_exists($v['name'], $source)) || (isset($v['alias']) && array_key_exists($v['alias'], $source)) ) {
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
                        $fields =array_intersect_key($this->request->get(),array_flip($v['name']));
//                        $p = array_intersect_key($this->request->get(), $fields);
//                        $s = array_slice(($t = array_filter($p, function ($v) {
//                            return ($v !== null && $v !== '');
//                        }, ARRAY_FILTER_USE_BOTH)), 0, 1);
//                        if (count($s)) $v['name'] = key($s); else $v['name'] = key($p);
                        foreach ($fields as $k1 => $v1) if (!isset($result[$k1])) {
                            $opt = ['name'=>$k1];
                            if (isset($v['alias'])) {
                                $opt['alias'] = preg_replace('/\(.*\)/U', $k1, $v['alias']);
                                $result[$opt['alias']] = $v1;
                            } else {
                                $result[$k1] = $v1;
                            }
                            $params[] = array_merge($v, $opt);
                        }
                    } else {
                        foreach ($fields as $k1 => $v1) {
                            $value = $this->request->get([$k1]);
                            if ((is_null($value) || $value == '') && isset($v['default'])) {
                                $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this->owner), $this->arguments($v['default'])) : $v['default'];
                            }

                            $opt = ['name'=>$k1];
                            if (isset($v['alias'])) {
                                $opt['alias'] = preg_replace('/\(.*\)/U', $k1, $v['alias']);
                                $result[$opt['alias']] = $value;
                            } else {
                                $result[$k1] = $value;
                            }
                            $params[] = array_merge($v, $opt);
                        }
                    }
                } else {
                    $value = $this->request->get($v['name']);

                    if ((is_null($value) || $value === '') && isset($v['default'])) {
                        $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this->owner), $this->arguments($v['default'])) : $v['default'];
                    }

                    $this->is_filter = $is_filter;

                    if (($is_filter && $value !== null && $value !== '') || !$is_filter) {
                        $result[(isset($v['alias']) ? $v['alias'] : $v['name'])] = $value;
                    }
                }
            }
        }
        return $result;
    }
    
    private function makeResult(array $opt=[])
    {
        $result = ['result'=> 'error', 'message' => 'Run-Time error!'];

        if ($this->checkPermission && !$this->isAllow()) {
//            $this->owner->response_header['Action-Status'] = rawurlencode ( '{"result":"error","message":"Отказано в доступе / Permission denied!"}');
            $result = ['result'=> 'error', 'message' => 'Отказано в доступе / Permission denied'];
        } else {
            if (count($this->error))  {
                $result = ['result'=> 'error', 'message' => $this->error];
            } else {
                $arg = $this->arguments($opt['action']);
                if (count($this->error)) {
                    $result = ['result' => 'error', 'message' => $this->error];
                } else {
                    $result = call_user_func_array($this->action->bindTo($this), $arg);
                }
            }
        }

        return $result;
    }

    public function getResult(array $opt=[])
    {
        $result = $this->makeResult(['action'=>$this->action]+$opt);
        return new \Application\Jsonb($result, ['owner'=>$this, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
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
       $result = $this->makeResult(['action'=>$this->action]+$opt);

        $type = 'json';
        if (isset($result['Content-Type'])) {
            $type = strtolower($result['Content-Type']);
            unset($result['Content-Type']);
        }
        return $this->owner->response($type, $result);
    }

    /**
     * @function isAllow
     * Check acl allow
     *
     * @param string $field
     * @return bool
     */
    protected function isAllow(): bool
    {
        if (isset($this->owner->acl) && count($this->groups)) return $this->owner->acl->in($this->groups);
        return !boolval(count($this->groups));
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
     * @function paramsByKey
     *
     * @param $pattern
     * @param $a
     * @return array
     */
    protected function paramsByKey($pattern, $a)
    {
        if ( is_array($a) && \Application\PHPRoll::is_assoc($a) ) {
            $keys = array_values(preg_grep($pattern, array_keys($a)));
            if (count($keys)) return [$keys[0], $a[$keys[0]]];
        }
        return [null, null];
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
        return array_map(function (&$item) { return $item->value = $this->{$item->name}; }, (new \ReflectionFunction($fn))->getParameters());
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
        $value = null;

        switch (strtolower($name)) {
            case 'owner':
                $value = $this->owner;
                break;
            case 'header':
                $value = $this->owner->header;
                break;
            case 'cfg':
                $value = $this->owner->cfg;
                break;
            case (strpos($name, 'db') === 0 ? true: false):
                try {
                    $value = isset($this->owner->{$name}) ? $this->owner->{$name} : new \Application\PDA($this->owner->cfg->{$name});
                } catch (\Exception $e) {
                    $this->error[$name] = addslashes($e->getMessage());
                    $value = null;
                }
                break;
            case 'error':
                $value = $this->error;
                break;
            case 'acl':
                $value = $this->owner->acl ?? null;
                break;
            default:
                list($key, $params) = $this->paramsByKey("/^!*$name$/i", $this->opt->{$this->method} ?? []);
                if (is_null($key)) list($key, $params) = $this->paramsByKey("/^!*$name$/i", $this->opt->get() ?? []);

                if (is_array($params)) {
                    $swap = $this->getParams($params, strpos($key, '!') !== false);
                    $value = $this->initParams($params,$swap );
                }  else  {
                    $value = $this->opt->get($name);
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
        return $this->opt->call($name, $arguments);
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