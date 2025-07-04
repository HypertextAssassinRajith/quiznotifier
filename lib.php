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

        // Get all questions
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id]);
        $total_questions = count($slots);

        // Count correct answers
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

        // Calculate grade based on correct answers out of total questions
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

        $json = json_encode($payload);

        $url = get_config('local_quizwebhook', 'webhookurl');

        if (empty($url)) {
            // No webhook URL set, so skip sending
            return;
        }


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
