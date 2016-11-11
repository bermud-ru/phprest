<?php
/**
 * config.php
 *
 * @category SPA
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 07/12/2015
 *
 */
namespace Application;

return array(
    'app' => 'http://localhost',
    'basedir' => __DIR__,
    'view' => __DIR__.'/Application/view/',
    '404' => '404.phtml',
    'route' => function($app, $path)
    {
        $result = null;

//        $result = null;
//
//        if (isset($path[0])) {
//            //if (preg_match('/.phtml/i', $path[0])) $owner->set
////            switch ($path[0]) {
////                case 'login.phtml':
////                    $error = [];
////                    if (empty($owner->params['login']['email']) || !filter_var($owner->params['login']['email'], FILTER_VALIDATE_EMAIL))
////                        $error['email'] = 'E-mail как имя пользователя';
////                    if (empty($owner->params['login']['passwd'])) $error['passwd'] = 'Пароль не может быть пустым';
////                    if (empty($error))
////                        $result = $owner->responce('json', ['result'=>'ok', 'data'=>$owner->params['login']]);
////                    else
////                        $result = $owner->responce('error', ['code'=>403, 'form'=>$error]);
////                    break;
////            }
//        }
        return $result;
    },
    'mail'=>['from'=>'andrey@novikov.be']
);
?>