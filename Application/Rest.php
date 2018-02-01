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
    protected $owner = null;
    protected $acl = [];
    protected $opt = ['params'=>[],'filter'=>[]];
    protected $method = 'GET';
    protected $action = null;
    protected $checkPermission = true;
    protected $request = [];

    // Авторизованный пользватель, выполняющий Rest action
    public $user = null;
    // Контейнер сообщений об ошибках
    public $error = [];

    public $params = [];
    public $filter = [];

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

            $this->opt['params'] = in_array(strtoupper($this->method),['POST','PUT']) ? array_merge($opt['common'] ?? [], $opt[$this->method]['params'] ?? $opt['params'] ?? []) : $opt[$this->method]['params'] ?? $opt['params'] ?? [];
            $this->opt['filter'] = $opt[$this->method]['filter'] ?? [];

            $this->acl = array_merge($opt['acl'] ?? [], $opt[$this->method]['acl'] ?? []);
            $this->action = $opt[$this->method]['action'];
        }
    }

    /**
     * Params init
     *
     * @param array $params
     * @param $source
     * @return mixed
     */
    protected function init(array $params, &$source, $is_empty = true)
    {
        foreach ($params as $k => $v) {
            if ( is_array($v) && (isset($source[$v['name']]) || (isset($v['alias']) && isset($source[$v['alias']]) && ($is_empty || !empty($source[$v['alias']])))) ) {
                $item = (new \Application\Parameter($source, $v))->setOwner($this);
                if (!$is_empty && is_null($item->value)) {
                    if (isset($v['alias']) && isset($source[$v['alias']])) {
                        unset($source[$v['alias']]);
                    } elseif(isset($source[$v['name']])) {
                        unset($source[$v['name']]);
                    }
                }
            }
        }

        return $source;
    }

    /**
     * Получаем массив Поле-Значение REST action
     *
     * @param array $params
     * @param bool $empty
     * @return array
     */
    protected function getParams(array &$params, $empty = true): array
    {
        $result = [];
        if (count($params)) {
            foreach ($params as $k => $v) {
                if (is_array($v['name'])) {
                    $p = array_intersect_key($this->request,array_flip($v['name']));
                    $s = array_slice(($t = array_filter($p, function($v) {return($v !== null && $v !== '');},ARRAY_FILTER_USE_BOTH)),0,1);
                    if (count($s)) $v['name'] = key($s); else $v['name'] = key($p);
                }

                $value = isset($this->request[$v['name']]) ? $this->request[$v['name']] : null;
                if ((is_null($value) || $value === '') && isset($v['default'])) {
                     $value = (is_callable($v['default'])) ? call_user_func_array($v['default'], $this->arguments($v['default'])) : $v['default'];
                }

                if (!is_null($value) || $empty || (isset($v['alias']) && strpos($v['alias'], '^') !== false)) {
                    if (isset($v['alias'])) $result[$v['alias']] = $value;
                    else $result[$v['name']] = $value;
                }
//                elseif (isset($v['required']) && !$v['required'] || !isset($v['required'])) {
//                    $result[$v['name']] = null;
//                }

            }
        }
        return $result;
    }

    /**
     * dispatcher
     * Диспетчер REST запросов [GET| PUT| POST| DELETE]
     *
     * @param array $opt
     * @return mixed
     */
    public function dispatcher(array $opt=[])
    {

        if ($this->checkPermission && !$this->isAllow($opt['field'] ?? ''))
            return $this->response('error', ['code' => '403', 'message' => 'Отказано в доступе / Permission denied']);

        $p = [];$args = $this->arguments($this->action, $p);
        if (!count($this->error) || in_array('error', $p)) {
            try {
                $result = call_user_func_array($this->action, $args) ?? [];
                if (isset($result['error'])) $result['code'] = 417;
                return $this->response(isset($result['error']) ? 'error' : ($opt['type'] ?? 'json'), $result);
            } catch (\Exception $e) {
                return $this->response('error', ['code' => '400', 'message'=> $e->getMessage()]);
            }
        }

        return $this->response('error', $this->error);
    }

    /**
     * isAllow
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
     * Prepare args for closure
     *
     * @param callable $fn
     * @return array
     */
    protected function arguments(callable &$fn, &$args = []): array
    {
        return array_map(function (&$item) use(&$args) {
            array_push($args, $item->name);
            switch (strtolower($item->name)){
                case 'header':
                    $item->value = $this->owner->header;
                    break;
                case 'params':
                    if (count($this->opt['params']) && empty($this->params)) {
                        $this->params = $this->getParams($this->opt['params'], true);
                        $this->init($this->opt['params'], $this->params);
                    }
                    $item->value = &$this->params;
                    break;
                case 'filter':
                    if (count($this->opt['filter']) && empty($this->filter)) {
                        $this->filter = $this->getParams($this->opt['filter'], false);
                        $this->init($this->opt['filter'], $this->filter, false);
                    }
                    $item->value = &$this->filter;
                    break;
                case 'db':
                    $item->value = isset($this->owner->db) ? $this->owner->db : new \Application\PDA($this->owner, true);
                    break;
                case 'self':
                    $item->value = $this;
                    break;
                case 'error':
                    $item->value = $this->error;
                    break;
                case 'owner':
                    $item->value = $this->owner;
                    break;
                case 'user':
                    $item->value = $this->user ?? [];
                    break;
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
    public function __invoke(array $request, array $params, $flag = true ): array
    {
        $this->request = $request;

        $response = $this->getParams($params, $flag);
        $this->init($params, $response);

        return $response;
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
        if (property_exists($this->owner, $name)) {
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
        if (method_exists($this->owner, $name)) return call_user_func_array(array($this->owner, $name), $arguments);
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