<?php
/**
 * error.php
 *
 * @category REST model
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 08/09/2016
 *
 */
namespace Application;

return [
    'params' => [
    ],
    'get'=>[
        'action'=>function() {
                return ['result' => 'error', 'data' => []];
        }
    ]
];
?>
