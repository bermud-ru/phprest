<?php
/**
 * ACL.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Users control access PHPRole extend (Backend RESTfull)
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 2/08/2016
 * @status beta
 * @version 0.1.2
 * @revision $Id: ACL.php 0004 2017-07-24 23:44:01Z $
 *
 */

namespace Application;


class ACL
{
    public $user = [];
    protected $acl = [];

    /**
     * ACL constructor.
     *
     * @param PHPRoll $app
     * @param bool $attach
     * @param array $opt
     */
    public function __construct(\Application\PHPRoll &$app, $attach = false, array $opt=[])
    {
        if (count($opt)) {
            $this->user = $opt;
        } else {
            if (isset($app->config['user']) && is_string($app->config['user'])) {
                $params = [];
                preg_match_all('/:([a-zA-Z0-9\._]+)/', $app->config['user'], $fields);
                if ( isset($fields[1]) ) {
                    if (!array_diff($params, array_keys($app->header))) $params = array_intersect_key($app->header, array_flip($fields[1]));
                    else $params = array_intersect_key($app->params, array_flip($fields[1]));
                }
                try {
                    $db = isset($app->db) ? $app->db : new \Application\PDA($app->config['db']);
                    //echo '<textarea>'; var_dump($params); echo '</testarea>';exit;
                    if ($db) $this->user = count($params) ? $db->stmt($app->config['user'], $params)->fetch() : null;
                } catch (\Exception $e) {
//                    $app->response_header['Action-Status'] = 'ERROR ACL '.addslashes ($e->getMessage());
//                    trigger_error("Application\ACL: " . addslashes ($e->getMessage()) , E_USER_WARNING);
                    $this->user = null; $db = null;
                }
                if ($db && $attach) {
                    $app->db = $db; $app->acl = $this;
                }
            }
        }

        if (isset($app->config['acl'])) $this->acl = $app->config['acl'];
    }

    /**
     * @function group
     *
     * @param string $field
     * @return string | null
     */
    public function group(string $field)
    {
        if ((empty($this->user) || !count($this->user)) && !in_array($field, is_array($this->user)?$this->user : [])) return null;
        return in_array($this->user[$field], $this->acl) ? array_flip($this->acl)[$this->user[$field]] : null;
    }

    /**
     * Access to User property as ACL object property
     *
     * @param string $field
     * @return mixed|null
     */
    public function __get(string $field)
    {
        return count($this->user) && isset($this->user[$field]) ? \Application\Parameter::ize($this->user[$field]) : null;
    }

    /**
     * @param string $field
     * @return mixed|null
     */
    public function __invoke(string $field)
    {
        return !empty($this->user) && count($this->user) && isset($this->user[$field]) ? \Application\Parameter::ize($this->user[$field]) : null;
    }

    /**
     * @function in
     *
     * @param string $field
     * @param array $roles
     * @return mixed
     */
    public function in(string $field, array $names):bool 
    {
        if ($this->user === false) return false;
        if (!count($this->user) && !in_array($field, $this->user ?? [])) return false;
        if (\Application\PHPRoll::is_assoc($names)) {
            return in_array($this->user[$field], array_values($names));
        }
        return in_array($this->user[$field], array_values(array_intersect_key($this->acl, array_flip($names))));
    }
}

?>