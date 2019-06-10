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
                    $option = ['self' => &$this, 'opt'=>$params['opt']||[]];
                    return $this->context($script[$idx], $option);
                }
            } elseif (is_string($script)) {
                return $params['permit'] || !$params['permit'] && $params['deny'] ? $this->context($script, $option) : null;
            }
        } catch (\Application\ContextException $e) {
            return $this->context($this->config['404'], $option);
        }

        if (count($script) == 2) trigger_error("Application\View::partial($script) пустой идентификатор скрипта", E_USER_WARNING);
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
        if (isset($this->header['Xhr-Version'])) { $opt['script'] = null; }
        return parent::tpl($this->path, $opt);
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

}

?>