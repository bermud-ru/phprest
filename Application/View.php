<?php
/**
* View.php
*
* @category RIA (Rich Internet Application) / SPA (Single-page Application) PHPRole extend (Frontend RESTfull)
* @author Андрей Новиков <andrey (at) novikov (dot) be>
* @data 01/08/2016
*
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
    public function script(array $params =[]) {
        if (!isset($params['id']) && !isset($params['name'])) throw new \Exception('Application\View::script() - необходимо определить id или name!');
        $id = $params['id'] ?? $params['name'];
        $charset = $params['charset'] ?? 'UTF-8';
        $opt = $params['opt'] ?? [];
        $content = "<script id=\"$id\" type=\"text/x-template\" charset=\"$charset\">";
        if ($params['src']) $content .= $this->context($params['src'], array_merge($opt,['script'=>null,'ext'=>'']));
        else if ($params['code'] || $params['body']) $content .= $params['code'] ?? $params['body'];
        else trigger_error("Application\View::script([name='$id']) пустой скрипт", E_USER_WARNING);
        $content .= "</script>";

        return $content;
    }
}

?>