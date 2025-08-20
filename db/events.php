<?php
defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => 'local_quizwebhook_observer::attempt_submitted',
        'includefile' => '/local/quizwebhook/lib.php',
        'internal'    => false,
        'priority'    => 1000,
    ],

    [
        'eventname'   => '\core\event\course_module_created',
        'callback'    => 'local_quizwebhook_observer::course_module_created',
        'includefile' => '/local/quizwebhook/lib.php',
        'internal'    => false,
        'priority'    => 1000,
    ],

    [
        'eventname'   => '\core\event\course_created',
        'callback'    => 'local_quizwebhook_observer::course_created',
        'includefile' => '/local/quizwebhook/lib.php',
        'internal'    => false,
        'priority'    => 1000,
    ],
];
