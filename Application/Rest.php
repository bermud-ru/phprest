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
    // Контейнер сообщений об ошибках
    public $error = [];

    protected $COOKIE = null;

    /**
     * Конструктор
     *
     * @param array|\Application\Request $config данные из файла конфигурации или class
     * @param array|null $header внешний заголовок
     */
    public function __construct($params, ?array $header = null)
    {
        if (is_array($params)) {
            parent::__construct($params, $header);
        } else {
            foreach (['cfg','uri','header','params','acl'] as $property)
                if (property_exists($params,$property)) $this->{$property} = $params->{$property};
        }
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
//        $this->params = $params;
        $this->params = new \Application\Jsonb($params, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
    }

    /**
     * @function getParams
     * Получаем массив Поле-Значение REST action
     *
     * @param array $params
     * @return array
     */
    protected function params4rest(array $params): array
    {
        $result = [];
        if (count($params)) {
            foreach ($params as $k => $v) {
                if (is_array($v['name'])) {
                    $fields = array_flip($v['name']);
                    foreach ($fields as $k1 => $v1) {
                        $value = $this->params->{$k1};
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
                } else{
                    $value = $this->params->{$v['name']};
                    if ((is_null($value) || $value === '') && isset($v['default'])) {
                        $value = (is_callable($v['default'])) ? call_user_func_array($v['default']->bindTo($this), $this->arguments($v['default'])) : $v['default'];
                    }
                    $result[(isset($v['alias']) ? $v['alias'] : $v['name'])] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @function model
     *
     * @param array $opt
     * @return mixed
     */
    public function model(array $cfg=[], ?array $extra=null, $REQUEST_METHOD=null)
    {
        $REQUEST_METHOD = strtolower($REQUEST_METHOD ?? $_SERVER['REQUEST_METHOD']);
//        $model = new \Application\Jsonb($cfg, ['owner' => $this]);
        $model = new \Application\Jsonb($cfg, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
        $result = new \Application\Jsonb(['result'=> 'error', 'message' => 'Methods handler not defined!']);

        if ( $method = $model->get($REQUEST_METHOD) ) { // property_exists
            if ($model->groups && !$this->isAllow($model)) {
                $result->message = 'Отказано в доступе / Permission denied';
            } else {
                $method = new \Application\Jsonb($method, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
                if (!is_callable($method->action)) {
                    $result->message = "//$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI] action[{$method->action}] not supported";
                } else {
                    if ($extra) $this->params->append($extra);
                    $arg = $this->arguments($method->action, $model, $method, $REQUEST_METHOD);
                    if (count($this->error)) {
                        $result ->message = $this->error;
                    } else {
                        $result = call_user_func_array($method->action->bindTo($this), $arg);
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
    public function run(array $opt=[])
    {
        $result = new \Application\Jsonb(['result'=> 'error', 'message' => 'Methods handler not defined!']);
        if (isset($opt['route']) && is_callable($opt['route'])) {
            $result = call_user_func_array($opt['route']->bindTo($this), isset($opt['params']) ? $opt['params'] : []);
        }

        return $this->response('json', $result);
    }

    /**
     * @function isAllow
     * Check acl allow
     *
     * @param string $field
     * @return bool
     */
    protected function isAllow($model): bool
    {
        if (count($model->groups)) {
            $this->cfg->acl($this->cfg->roles);
            return $this->acl->in($model->groups);
        }
        return !boolval(count($model->groups));
    }

    /**
     * @function is_restParams
     *
     * @param mixed $a
     * @return bool
     */
    public function is_restParams($a): bool
    {
        if (is_array($a)) foreach ($a as $v) if (is_array($v)) { return true; } else { return false; }
        return false;
    }

    /**
     * @function arguments
     * Prepare args for closure
     *
     * @param callable $fn
     * @return array
     */
    protected function arguments(callable &$fn, $model, $method, $req): array
    {
        return array_map(function ($item) use ($model, $method, $req) {
           $item->value = null;
           switch ($item->name) {
//               case str_starts_with($item->name, 'db'):
               case substr($item->name, 0, 2 ) === 'db':
                    try {
                        $item->value = isset($this->{$item->name}) ? $this->{$item->name} : new \Application\PDA($this->cfg->{$item->name});
                    } catch (\Exception $e) {
                        $this->error[$item->name] = addslashes($e->getMessage());
                    }
                    break;
               case 'error':
                    $item->value = $this->error;
                    break;
               default:
                    $item->value = $method->{$item->name} ?? $model->{$item->name};
                    if ($this->is_restParams($item->value)) {
                        $requestPool = $this->params4rest($item->value);
                        foreach ($item->value as $v) { (new \Application\Parameter($v, $requestPool))->setOwner($this); }
                        $p = \Application\Parameter::filter($requestPool, function($v) { return $v instanceof \Application\Parameter; });
                        $item->value = new \Application\Jsonb($p, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
                    }
               }
               return $item->value;
            },
            (new \ReflectionFunction($fn))->getParameters()
        );
    }

    /**
     * COOKIE
     *
     * @param $param
     * @param null $def
     * @return mixed|null
     */
    public function cookie($param, $def = null)
    {
        if (is_null($this->COOKIE)) {
            $this->COOKIE = [];
            $cookies = $this->header['Cookies'];
            if ($cookies && ($a = explode(";", $cookies))) {
                foreach ($a as $i) {
                    $pair = explode("=",trim($i));
                    $this->COOKIE[trim(reset($pair))] = trim(end($pair));
                }

            }
        }

        return isset($this->COOKIE[$param]) ? $this->COOKIE[$param] : $def;
    }

    /**
     * Генерация заголовка ответа и форматирование кода ответа
     * @param $type
     * @param $params
     * @return mixed
     */
    public function response(string $type, $params = null)
    {
        $code = 200;
        if (array_key_exists($code, \Application\PHPRoll::HTTP_RESPONSE_CODE))  {
            header("HTTP/1.1 {$code} " . \Application\PHPRoll::HTTP_RESPONSE_CODE[$code], false);
        }
        http_response_code(intval($code));
        header('Expires: '. date('r'), false);
//        header('Access-Control-Allow-Credentials: true');

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