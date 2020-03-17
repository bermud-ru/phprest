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
    protected $opt = [];
    protected $user_id = null;
    protected $group_idx = [];
    protected $groups = [];
    protected $user_object = [];

    const default = ['user_id'=>'user_id','gorop_idx'=>'group_idx','params'=>[]];
    const user_param = ['name'=>'user_id', 'type'=>'int', 'default'=> null];
    const group_param = ['name'=>'group_idx', 'type'=>'int', 'default'=> null];

    /**
     * ACL constructor.
     *
     * @param PHPRoll $app
     * @param bool $attach
     * @param array $opt
     */
    public function __construct(\Application\PHPRoll &$app, $user_object, array $opt=\Application\ACL::default)
    {
        if (is_array($user_object)) {
            $user_param = \Application\ACL::user_param + (isset($opt['user_id']) ? ['alias'=>$opt['user_id']] : []);
            $this->user_id = new \Application\Parameter($user_param, $user_object);
            $group_param = \Application\ACL::group_param + (isset($opt['group_idx']) ? ['alias'=>$opt['group_idx']] : []);
            $this->group_idx = new \Application\Parameter($group_param, $user_object);
        }
        $this->groups = isset($opt['groups']) ? $opt['groups'] : [];
        $this->user_object = $user_object;
        $this->opt = $opt;
        $app->acl = $this;
    }

    /**
     * Get user ID
     *
     * @return int|null
     */
    public function u(bool $named = true)
    {
        return $this->user_id ? $this->user_id->__toInt() : null;
    }

    /**
     * Get group ID or Name
     *
     * @param bool $named
     * @return int|mixed|null
     */
    public function g(bool $named = true)
    {
        if (empty($this->group_idx)) return null;

        $group_id = $this->group_idx->__toInt();

        if ($named) {
            $groups = array_flip($this->groups);
            return isset($groups[$group_id]) ? $groups[$group_id] : null;
        }
        return $group_id;
    }

    /**
     * Check exist user goup id in
     * @param array $groups
     * @return bool
     */
    public function in(array $groups)
    {
        $group_name = $this->g(); $group_id = $this->g(false);
        if (is_null($group_id)) return false;

        foreach ($groups as $k=>$v) {
            if (is_string($v)) {
                if ($v == $group_name) return true;
            } else {
                if (isset($this->groups[$v]) && $this->groups[$v] == $group_id) return true;
            }
        }
        return false;
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
        if (isset($this->user_object[$name])) {
            return \Application\Parameter::ize($this->user_object[$name]);
        }
        return null;
    }

    /**
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return [ 'ACL' => \Application\Parameter::ize($this) ];
    }

    /**
     * Prepare for vardump() resutl;
     * @return array
     */
    public function __debugInfo() {
        return [ 'ACL' => \Application\Parameter::ize($this) ];
    }

}

?>