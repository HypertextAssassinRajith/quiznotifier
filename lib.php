<?php

class local_quizwebhook_observer {
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        $data = $event->get_data();
        $attemptid = $data['objectid'];
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $user = $DB->get_record('user', ['id' => $attempt->userid]);
        $course = $DB->get_record('course', ['id' => $quiz->course]);

        // Get user's roles
        $context = \context_course::instance($course->id);
        $roles = get_user_roles($context, $user->id, true);
        $role_names = array_map(function($r) { return $r->shortname; }, $roles);

        // Get grade
        $grade = $DB->get_record('quiz_grades', [
            'quiz' => $quiz->id,
            'userid' => $user->id
        ]);

        $payload = [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'fullname' => fullname($user),
                'email' => $user->email,
                'roles' => $role_names,
            ],
            'course' => [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
            ],
            'quiz' => [
                'id' => $quiz->id,
                'name' => $quiz->name,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'state' => $attempt->state,
                'sumgrades' => $attempt->sumgrades,
                'timefinish' => $attempt->timefinish,
                'graded_score' => $grade ? $grade->grade : null,
            ],
        ];

        $json = json_encode($payload);

        $url = 'https://webhook.site/bc78109f-5e2f-4938-a808-8e8e42594ac1';

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 5,
            ],
        ];

        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }
}
