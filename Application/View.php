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
     * script
     *
     * @param array $params
     * @return string
     */
    public function script(array $params =[]): string 
    {
        if (!isset($params['id']) && !isset($params['name'])) throw new \Exception('Application\View::script() - необходимо определить id или name!');
        $id = $params['id'] ?? $params['name'];
        if (empty($id)) trigger_error("Application\View::script(...) пустой идентификатор скрипта", E_USER_WARNING);
        $charset = isset($params['charset']) ? 'charset="'.$params['charset'].'"' : '';
        $arguments = isset($params['arguments']) ? 'arguments="'.$params['arguments'].'"' : '';
        $before = isset($params['before']) ? 'before="'.$params['before'].'"' : '';
        $after = isset($params['after']) ? 'after="'.$params['after'].'"' : '';
        $opt = $params['opt'] ?? [];
        $vars = '';
        $a = array_intersect_key( $params, array_flip( preg_grep( '/^tmpl\-.*/i', array_keys( $params ))));
        if (count($a)) array_walk($a, function(&$item, $key) use(&$vars) { $vars = $key.'="'.$item.'" '; });
        $content =<<<EOT
<script id="$id" type="text/x-template" $charset $arguments $vars $before $after>
EOT;
        if ($params['src']) $content .= $this->context($params['src'], array_merge($opt,['script'=>null,'ext'=>'']));
        else if ($params['code'] || $params['body']) $content .= $params['code'] ?? $params['body'];
        else trigger_error("Application\View::script([name='$id']) пустой скрипт", E_USER_WARNING);
        $content .= "</script>";

        return $content;
    }

    /**
     * partial
     *
     * @param string | array $script
     * @param boolean $permit
     * @return string
     */
    public function partial($script, $permit = true): string 
    {
        $permit = boolval($permit);

        if (is_array($script)) {
            $idx = (count($script) == 2) && !$permit ? 1 : ($permit ? 0 : null);
            if (!is_null($idx)) return $this->context($script[$idx], ['self' => &$this]);
        } elseif (is_string($script)) {
            return $this->context($script, ['self' => &$this, 'script' => $script, 'is_allow' => $permit]);
        }

        if (count($script) == 2) trigger_error("Application\View::partial($script) пустой идентификатор скрипта", E_USER_WARNING);
        return '';
    }

    /**
     * for RESTfull request
     *
     * @param array $opt
     * @return array|string
     */
    public function getPattern(array $opt)
    {
        if (isset($this->header['Xhr-Version'])) {
            $opt['script'] = null;
        }

        return parent::tpl($this->path, $opt);
    }

    public function is($type){
        if (is_array($type)) return in_array(strtoupper($_SERVER['REQUEST_METHOD']), $type);
        return strtoupper($type) == strtoupper($_SERVER['REQUEST_METHOD']);
    }

}

?>