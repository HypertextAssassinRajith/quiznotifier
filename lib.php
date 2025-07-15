<?php

class local_quizwebhook_observer {

    // observer for quiz submission
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        $data = $event->get_data();
        $attemptid = $data['objectid'];
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $user = $DB->get_record('user', ['id' => $attempt->userid]);
        $course = $DB->get_record('course', ['id' => $quiz->course]);

        $context = \context_course::instance($course->id);
        $roles = get_user_roles($context, $user->id, true);
        $role_names = array_map(fn($r) => $r->shortname, $roles);

        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id]);
        $total_questions = count($slots);

        $correct_count = 0;
        foreach ($slots as $slot) {
            $qa = $DB->get_record('question_usages', ['id' => $attempt->uniqueid]);
            $question_attempt = $DB->get_record('question_attempts', [
                'questionusageid' => $qa->id,
                'slot' => $slot->slot
            ]);
            if ($question_attempt && $question_attempt->fraction == 1.0) {
                $correct_count++;
            }
        }

        $calculated_grade = $total_questions > 0 ? round(($correct_count / $total_questions) * 100, 2) : null;

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
                'correct_answers' => $correct_count,
                'total_questions' => $total_questions,
                'grade_percentage' => $calculated_grade,
            ],
        ];

        $webhook_url = rtrim(get_config('local_quizwebhook', 'webhookurl'), '/') . '/moodle/quiz/webhook';
        self::send_webhook($payload, $webhook_url);

    }

    // observer for course module is created
    public static function course_module_created(\core\event\course_module_created $event) {
        global $DB;

        $data = $event->get_data();
        $cmid = $data['objectid'];
        $courseid = $data['courseid'];

        // Get course information
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        
        // Get module information
        $context = \context_course::instance($courseid);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cmid);

        // Get enrolled users
        $enrolled_users = []; 
        $user_records = get_enrolled_users($context);
        foreach ($user_records as $user) {
            $enrolled_users[] = [
                'id' => $user->id,
                'username' => $user->username,
                'fullname' => fullname($user),
                'email' => $user->email,
            ];
        }

        // Get updated course name (if available)
        $updated_course_name = $course->fullname;
        if (property_exists($course, 'displayname') && !empty($course->displayname)) {
            $updated_course_name = $course->displayname;
        }

        $payload = [
            'event' => 'course_module_created',
            'course' => [
                'id' => $course->id,
                'fullname' => $updated_course_name,
                'shortname' => $course->shortname,
            ],
            'module' => [
                'id' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
            ],
            'enrolled_users' => $enrolled_users,
            'timestamp' => time(),
        ];

        $webhook_url = rtrim(get_config('local_quizwebhook', 'webhookurl'), '/') . '/moodle/lms/announce';
        self::send_webhook($payload, $webhook_url);
    }


    // webhook sending method
    private static function send_webhook($payload, $url) {
        if (empty($url)) {
            return;
        }

        $json = json_encode($payload);

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
