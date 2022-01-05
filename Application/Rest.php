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

class Rest extends \Application\Request
{
    protected $method = 'GET';
    protected $action = null;
    protected $checkPermission = true;
    protected $opt = [];
    protected $groups = [];
    protected $is_filter = false;

    // Контейнер сообщений об ошибках
    public $error = [];

    /**
     * @function restParams
     * Params init
     *
     * @param array $params
     * @param $source
     * @return mixed
     */
    protected function restParams(array $params, array &$source)
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

        return new \Application\Jsonb($source, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
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
                        $fields = array_intersect_key($this->params, array_flip($v['name']));
//                        $p = array_intersect_key($this->params, $fields);
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
                            $value = $this->params[$k1];
                            if ((is_null($value) || $value === '') && isset($v['default'])) {
                                $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this), $this->arguments($v['default'])) : $v['default'];
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
                    $value = $this->params[$v['name']];

                    if ((is_null($value) || $value === '') && isset($v['default'])) {
                        $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this), $this->arguments($v['default'])) : $v['default'];
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

    /**
     * @function responseParams
     *
     * @return array|false|mixed|string[]
     */
    protected function responseParams()
    {
         $arg = $this->arguments($this->action);

        if (count($this->error)) {
            return ['result' => 'error', 'message' => $this->error];
        } else {
            return call_user_func_array($this->action->bindTo($this), $arg);
        }
    }

    /**
     * @function getResult
     *
     * @param array $opt
     * @return Jsonb
     * @throws \Exception
     */
    public function getResult($opt, string $method = null)
    {
        $this->method = strtolower($method ?? $_SERVER['REQUEST_METHOD']);
        $o = is_array($opt) ? new \Application\Jsonb($opt, ['owner' => $this]) : $opt;
        $result = ['result'=> 'error', 'message' => 'Methods handler not defined!'];

        if ( $m = $o->get($this->method) ) { // property_exists

            $this->checkPermission = isset($m['permission']) ? boolval($m['permission']) :
                (isset($o->permission) ? boolval($o->permission) : true);
            $this->groups = $m['groups'] ?? method_exists($o, 'groups') ? $o->groups : [];

            if ($this->checkPermission && !$this->isAllow()) {
                $result = ['result' => 'error', 'message' => 'Отказано в доступе / Permission denied'];
            } else {
                if (!isset($m['action']) || !is_callable($m['action'])) {
                    $result = ['result' => 'error', 'message' => "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI] action[$this->method] not supported"];
                } else {
                    $this->action = $m['action'];
                    $this->opt = $o;
                }
                $result = $this->responseParams();
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
    public function run(array $opt=[])
    {
        $type = isset($opt['type']) ? isset($opt['type']) : 'json';
        $result = ['result'=> 'error', 'message' => 'Methods handler not defined!'];
        if (isset($opt['route']) && is_callable($opt['route'])) {
            $result = call_user_func_array($opt['route']->bindTo($this), @is_array($opt['params']) ? $opt['params'] : []);
        }

        if (isset($result['type'])) { $type = strtolower($result['type']); unset($result['type']); }
        return $this->response($type, $result);
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
        if (isset($this->acl) && count($this->groups)) return $this->acl->in($this->groups);
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
        if ( is_array($a) && \Application\Parameter::is_assoc($a) ) {
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
        // $this->{$item->name} ::(__get) and $this now  extends \Application\Request wtih own properties su as PARAMS
        return array_map(function ($item) {
                return $item->value = $this->{$item->name};
            },
            (new \ReflectionFunction($fn))->getParameters()
        );
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
//            case 'owner':
//                $value = $this;
//                break;
//            case 'header':
//                $value = $this->header;
//                break;
//            case 'cfg':
//                $value = $this->cfg;
//                break;
            case (strpos($name, 'db') === 0 ? true: false):
                try {
                    $value = isset($this->{$name}) ? $this->{$name} : new \Application\PDA($this->cfg->{$name});
                } catch (\Exception $e) {
                    $this->error[$name] = addslashes($e->getMessage());
                    $value = null;
                }
                break;
            case 'error':
                $value = $this->error;
                break;
//            case 'acl':
//                $value = $this->acl ?? null;
//                break;
            default:
                list($key, $params) = $this->paramsByKey("/^!*$name$/", $this->opt->get($this->method));
                if (is_null($key)) list($key, $params) = $this->paramsByKey("/^!*$name$/", $this->opt->get());

                if (is_array($params) && isset($params[0]) && is_array($params[0])) {
                    $swap = $this->getParams($params, strpos($key, '!') !== false);
                    $value = $this->restParams($params,$swap );
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
        return call_user_func_array($this->opt->{$name}, $arguments);
    }

    /**
     * PHPRoll Native method
     *
     * @param $method
     * @return Jsonb|array|string[]
     * @throws \Exception
     */
    public function __invoke(string $method)
    {
        return new \Application\Jsonb($this->getResult($this->cfg, $method), ['owner' => $this]);
    }

    /**
     * Получаем значение параменных в запросе
     *
     */
    protected function initParams()
    {
        if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== FALSE) {
            $params = $_POST;
        } else if (strpos($_SERVER['CONTENT_TYPE'], 'json') !== FALSE) {
            $params = json_decode($this->RAWRequet(), true);
        } else {
            mb_parse_str($this->RAWRequet(), $params);
        }
//        $this->params = new \Application\Jsonb($params, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
        $this->params = $params;
    }

    /**
     * Генерация заголовка ответа и форматирование кода ответа
     * @param $type
     * @param $params
     * @return mixed
     */
    public function response(string $type, $params = null)
    {
        $code = $params['code'] ?? 200;
        if (array_key_exists($code, \Application\PHPRoll::HTTP_RESPONSE_CODE))  {
            header("HTTP/1.1 {$code} " . \Application\PHPRoll::HTTP_RESPONSE_CODE[$code], false);
        }
        http_response_code(intval($code));
        header('Expires: '. date('r'), false);

        if (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') == false) {
            header('Cache-Control: no-cache', false);
            header('Pragma: no-cache', false);
        } else {
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0', false);
            header('Pragma: public', false);
        }

        $context = null;
        switch ($type)
        {
            case 'file':
                header('Access-Control-Max-Age: 0', false);
                header('Content-Description: File Transfer');
                header('Content-Transfer-Encoding: binary',false);
                header('Connection: Keep-Alive', false);
                header('Cache-Control: must-revalidate');
                $this->set_response_header();

                if ( is_resource($params['file']) ) {
                    fseek($params['file'], 0);
                    fpassthru($params['file']);
                    fclose($params['file']);
                    exit;
                }
                break;

            case 'json': default:
                header('Content-Description: json data container');
                header('Content-Type: Application/json; charset=utf-8;');
                header('Access-Control-Max-Age: 0', false);
                header('Access-Control-Allow-Origin: *', false);
                //header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, HEAD, OPTIONS, DELETE', false);
                header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers, Xhr-Version', false);
                header('Content-Encoding: utf-8');
                $this->set_response_header();

                switch (gettype($params)) {
                    case 'object':
                    case 'array':
                        $context = json_encode($params,JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                        break;
                    case 'string':
                    default:
                        $context = $params;
                }
                break;
        }

        return $context;
    }
}
?>