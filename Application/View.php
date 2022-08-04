<?php
/**
 * View.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) PHPRole extend (Frontend RESTfull)
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 01/08/2016
 * @status beta
 * @version 0.1.2
 * @revision $Id: View.php 0004 2017-07-24 23:44:01Z $
 */

namespace Application;

class View extends \Application\PHPRoll
{
    private $is_jscript = false;

    /**
     * @function xtemplate
     *
     * @param array $params
     * @return string
     */
    public function xtemplate(array $params =[]): string
    {
        if (!isset($params['id']) && !isset($params['name'])) throw new \Exception('Application\View::script() - необходимо определить id или name!');
        $id = $params['id'] ?? $params['name'];
        if (empty($id)) trigger_error("Application\View::script(...) пустой идентификатор скрипта", E_USER_WARNING);
        $charset = isset($params['charset']) ? 'charset="'.$params['charset'].'"' : '';
        $arguments = isset($params['arguments']) ? 'arguments="'.htmlspecialchars($params['arguments'],ENT_QUOTES).'"' : '';
        $before = isset($params['before']) ? 'before="'.htmlspecialchars($params['before'],ENT_QUOTES).'"' : '';
        $after = isset($params['after']) ? 'after="'.htmlspecialchars($params['after'],ENT_QUOTES).'"' : '';
        $opt = $params['opt'] ?? [];
        $vars = '';
        $a = array_intersect_key( $params, array_flip( preg_grep( '/^tmpl\-.*/i', array_keys( $params ))));
        if (count($a)) array_walk($a, function(&$item, $key) use(&$vars) { $vars = $key.'="'.htmlspecialchars($item,ENT_QUOTES).'" '; });
        $content =<<<EOT
<script id="$id" type="text/x-template" $charset $arguments $vars $before $after>
EOT;
        if ($params['src']) {
            $opt =  $opt + ['script'=>null,'ext'=>''];
            $content .= $this->context($params['src'], $opt);
        }
        else if ($params['code'] || $params['body']) $content .= $params['code'] ?? $params['body'];
        else trigger_error("Application\View::script([name='$id']) пустой скрипт", E_USER_WARNING);
        $content .= "</script>";

        return $content;
    }

    /**
     * @function partial
     *
     * @param string | array $script
     * @param boolean $permit
     * @param boolean $deny
     * @return string
     */
    public function partial($script, array $params = ['permit'=>true, 'deny'=>true]): ?string
    {
        $option = ['self' => &$this, 'script' => $script, 'is_allow' => $params['permit'], 'opt'=>$params['opt']??[]];

        try {
            if (is_array($script)) {
                $idx = (count($script) == 2) && !$params['permit'] ? 1 : ($params['permit'] ? 0 : null);
                if (!is_null($idx)) {
                    $option = ['self' => &$this, 'opt'=>$params['opt']??[]];
                    if (array_key_exists('grinder',$params)) $option['grinder'] = $params['grinder'];
                    return $this->context($script[$idx], $option);
                }
            } elseif (is_string($script)) {
                if (array_key_exists('grinder',$params)) $option['grinder'] = $params['grinder'];
                return $params['permit'] || !$params['permit'] && $params['deny'] ? $this->context($script, $option) : null;
            }
        } catch (\Application\ContextException $e) {
            if ($this->is_jscript) {
                \Application\IO::console_error($e,['','']);
            } else {
                return $this->context($this->cfg->str('404'), $option);
            }
        }

        if ((is_array($script) || $script instanceof \Countable) && count($script) == 2) trigger_error("Application\View::partial($script) пустой идентификатор скрипта", E_USER_WARNING);
        return null;
    }

    /**
     * @function jscode
     *
     * @param $script
     * @param array $params
     * @return string|null
     */
    public function jscode($script, array $params = ['permit'=>true, 'deny'=>true]): ?string
    {
        $this->is_jscript = true;
        $params['grinder'] = function ($contex) {
            return  preg_replace('/\/\*([^*]|[\r\n]|(\*+([^*\/]|[\r\n])))*\*+\/|\/\/[^\r\n]*/im','', $contex) ;
        };

        return $this->partial($script, $params);
    }

    /**
     * @function getPattern
     * for RESTfull request
     *
     * @param array $opt
     * @return array|string
     */
    public function getPattern(array $opt)
    {
        if ($this->query_type == '@') { $opt['script'] = null; }
        return parent::tpl(array_filter(explode("/", substr($this->uri, 1))), $opt);
    }

    /**
     * @function is
     * 
     * @param $type
     * @return bool
     */
    public function is($type){
        if (is_array($type)) return in_array(strtoupper($_SERVER['REQUEST_METHOD']), $type);
        return strtoupper($type) == strtoupper($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @function php2js
     *
     * @param $v
     * @param string $def
     * @return string
     */
    static public function php2js($v, $def='null')
    {
        if ($v === null || $v === '') return $def;
        $data = \Application\Parameter::ize($v,\Application\PDA::QUERY_STRING_QUOTES|\PDO::NULL_EMPTY_STRING|\Application\PDA::ARRAY_STRINGIFY);
        if (is_array($v) && isset($v['result']) && in_array($v['result'], ['error','warn']))
            return "function() { console.{$v['result']}(`{$v['message']}`); return str2json($data) }();";

        return "str2json($data)";
    }

    /**
     *  Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ($name)
    {
        switch ($name) {
            case substr($name, 0, 2) === 'db':
                try {
                    return $this->{$name} = new \Application\PDA($this->cfg->{$name});
                } catch (\Exception $e) {
                    $this->error[$name] = addslashes($e->getMessage());
                }
                break;
            case "acl":
                return $this->cfg->acl($this->cfg->roles);
            default:
                if ($this->cfg->{$name})  return $this->cfg->{$name};
        }
        throw new \Exception(__CLASS__."->$name property not foudnd!");
    }

    /**
     * Native method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (is_callable($this->cfg->{$name})) {
            return call_user_func_array($this->cfg->{$name}->bindTo($this), $arguments);
        }
        throw new \Exception(__CLASS__."->$name(...) method not foudnd");
    }

}

?>