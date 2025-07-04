<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_quizwebhook', get_string('pluginname', 'local_quizwebhook'));

    $settings->add(new admin_setting_configtext(
        'local_quizwebhook/webhookurl',
        get_string('webhookurl', 'local_quizwebhook'),
        get_string('webhookurl_desc', 'local_quizwebhook'),
        '',
        PARAM_URL
    ));

    $ADMIN->add('localplugins', $settings);
}
