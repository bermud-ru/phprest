<?php
/**
 * Rest.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)  PHPRole extend (Backend RESTfull)
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 07/07/2016
 *
 */
namespace Application;

class Rest
{
    protected $owner = null;
    protected $params = [];
    protected $filter = [];
    protected $acl = [];
    protected $action = null;

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
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        if (!isset($opt[$method]) && !isset($opt[$method]['action']) && !is_callable($opt[$method]['action'])) {
            $this->error['404'] = "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI] action[$method] not supported";
        } else {
            $params= $opt[$method]['params'] ?? $opt['params'] ?? [];
            $filter= $opt[$method]['filter'] ?? $opt['filter'] ?? [];
            $this->init(array_merge($params, $filter));
            $this->params = $this->getParams($params);
            if (count($filter)) $this->filter = array_filter($this->getParams($filter), function($v){return $v !='';});
            $this->acl = $opt[$method]['acl'] ?? $opt['acl'] ?? [];
            if (count($this->acl)) $this->owner->user = $this->user = new \Application\ACL($owner);
            $this->action = $opt[$method]['action'];
        }
    }

    /**
     * Params init
     */
    protected function init($params){
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                (new \Application\Parameter($this->owner, $v))->onError($this->error);
            } else {
                if (isset($this->owner->params[$k])) {
                    $this->owner->params[$k] = $v;
                } else {
                    $this->error[$k] = 'params not exist';
                }
            }
        }
    }

    /**
     * dispatcher
     * Диспетчер REST запросов [GET| PUT| POST| DELETE]
     *
     * @param array $opt
     * @return mixed
     */
    public function dispatcher(array $opt=[]) {
        if (!$this->isAllow($opt['field'] ?? '')) return $this->response('error', [ '403' => 'Отказано в доступе / Permission denied']);
        if (!count($this->error)) {
            try {
                return $this->response($opt['type'] ?? 'json',call_user_func_array($this->action, $this->arguments($this->action)));
            } catch (\Exception $e) {
                return $this->response('error', ['500' => $e->getMessage()]);
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
    protected function isAllow(string $field){
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
    protected function arguments(callable &$fn): array
    {
        return array_map(function (&$item) {
            switch (strtolower($item->name)){
                case 'app': $item->value = $this->owner; break;
                case 'header': $item->value = $this->owner->header; break;
                case 'params': $item->value = $this->params; break;
                case 'filter': $item->value = $this->filter; break;
                case 'db':
                    $this->owner->config['db'] = !empty($item->value) ? $item->value : $this->owner->config['db'];
                    $item->value = isset($this->owner->db) ? $this->owner->db : new \Application\Db($this->owner, true);
                    break;
                case 'self': $item->value = $this; break;
                case 'owner': $item->value = $this->owner; break;
                case 'user': $item->value = $this->user ?? []; break;
            }
            return $item->value;
        }, (new \ReflectionFunction($fn))->getParameters());
    }

    /**
     * Получаем массив Поле-Значение REST action
     * @return array
     */
    public function getParams(array $params) {
        if (count($params))
            $source = &$this->owner->params;
            return array_intersect_key($source, array_flip(array_map(function ($v) use(&$source) {
                if (isset($v['alias']) && isset($source[$v['name']])) {
                    $source[$v['alias']] = $source[$v['name']];
                    unset($source[$v['name']]);
                    return $v['alias'];
                }
                return $v['name'];}, $params)));
        return $this->owner->params;
    }

    /**
     * REST response
     *
     * @param $data
     */
    public function response(string $type, $data){
        switch ($type){
            case 'json':
                if (isset($data['error'])) {
                    //unset($data['result']);
                    return $this->owner->response('error', $data);
                }
                return $this->owner->response($type, $data);
            case 'error': return $this->owner->response($type, ['error'=>$data]);
            case 'array':
            default:
                return $data;
        }
    }

}
?>