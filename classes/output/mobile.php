<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mobile output class for block_course_recommendation
 *
 * @package    block_course_recommendation
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_course_recommendation\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Mobile output class for course recommendation block
 */
class mobile {

    /**
     * Returns the course recommendation block for the mobile app.
     *
     * @param array $args Arguments from mobile app
     * @return array HTML, javascript and other data
     */
    public static function mobile_block_view($args) {
        global $DB, $USER, $OUTPUT, $CFG;
        
        $context = \context_system::instance();
        
        // Check if user is logged in
        if (!isloggedin() || isguestuser()) {
            return [
                'templates' => [
                    [
                        'id' => 'main',
                        'html' => get_string('login_required', 'block_course_recommendation')
                    ]
                ]
            ];
        }
        
        // Get recommended courses
        $courses = self::get_recommended_courses($USER->id);
        
        if (empty($courses)) {
            return [
                'templates' => [
                    [
                        'id' => 'main',
                        'html' => get_string('no_recommendations', 'block_course_recommendation')
                    ]
                ]
            ];
        }
        
        // Prepare data for template
        $data = [
            'courses' => array_values($courses),
            'title' => get_string('recommended_courses', 'block_course_recommendation'),
            'courseimgurl' => $CFG->wwwroot . '/theme/image.php?theme=boost&component=core&image=i/course'
        ];
        
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('block_course_recommendation/mobile_view', $data)
                ]
            ],
            'javascript' => 'this.courseClicked = function(courseId) { 
                window.open("' . $CFG->wwwroot . '/course/view.php?id=" + courseId, "_system"); 
            };'
        ];
    }
    
    /**
     * Get recommended courses for the user
     *
     * @param int $userid The ID of the current user
     * @return array Array of recommended course objects
     */
    private static function get_recommended_courses($userid) {
        global $DB;

        // Get the database prefix
        $prefix = $DB->get_prefix();

        // Get the current user's enrolled courses
        $sql = "SELECT e.courseid
                FROM {$prefix}user_enrolments ue
                JOIN {$prefix}enrol e ON e.id = ue.enrolid
                WHERE ue.userid = :userid";
        $params = ['userid' => $userid];
        $userenrolments = $DB->get_records_sql($sql, $params);
        
        if (empty($userenrolments)) {
            return [];
        }
        
        $usercoursesids = array_keys($userenrolments);
        list($insql, $inparams) = $DB->get_in_or_equal($usercoursesids, SQL_PARAMS_NAMED);
        
        // Find similar users (users enrolled in at least one of the same courses)
        $sql = "SELECT DISTINCT ue.userid
                FROM {$prefix}user_enrolments ue
                JOIN {$prefix}enrol e ON e.id = ue.enrolid
                WHERE e.courseid $insql
                AND ue.userid != :userid";
        $params = array_merge($inparams, ['userid' => $userid]);
        $similarusers = $DB->get_records_sql($sql, $params);
        
        if (empty($similarusers)) {
            return [];
        }
        
        $similaruserids = array_keys($similarusers);
        list($usersql, $userparams) = $DB->get_in_or_equal($similaruserids, SQL_PARAMS_NAMED);
        
        // Get courses that similar users are enrolled in but the current user is not
        $sql = "SELECT c.*, cc.name as categoryname, 
                COUNT(DISTINCT ue.userid) as frequency
                FROM {$prefix}course c
                JOIN {$prefix}course_categories cc ON cc.id = c.category
                JOIN {$prefix}enrol e ON e.courseid = c.id
                JOIN {$prefix}user_enrolments ue ON ue.enrolid = e.id
                WHERE ue.userid $usersql
                AND c.id NOT $insql
                AND c.visible = 1
                GROUP BY c.id, cc.name
                ORDER BY frequency DESC, c.fullname";
        $params = array_merge($userparams, $inparams);
        $potentialcourses = $DB->get_records_sql($sql, $params, 0, 20); // Get top 20 for further filtering
        
        if (empty($potentialcourses)) {
            return [];
        }
        
        // Get user's course categories
        $sql = "SELECT DISTINCT cc.id, cc.name
                FROM {$prefix}course c
                JOIN {$prefix}course_categories cc ON cc.id = c.category
                WHERE c.id $insql";
        $usercategories = $DB->get_records_sql($sql, $inparams);
        
        // Score courses based on category similarity and other factors
        $scoredcourses = [];
        foreach ($potentialcourses as $course) {
            $score = $course->frequency * 10; // Base score from collaborative filtering
            
            // Boost score if course is in the same category as user's enrolled courses
            if (isset($usercategories[$course->category])) {
                $score += 50;
            }
            
            // Format summary for mobile display
            $summary = strip_tags($course->summary);
            if (strlen($summary) > 200) {
                $summary = substr($summary, 0, 197) . '...';
            }
            $course->formatted_summary = $summary;
            
            // Store the score with the course
            $course->score = $score;
            $scoredcourses[] = $course;
        }
        
        // Sort by score
        usort($scoredcourses, function($a, $b) {
            return $b->score - $a->score;
        });
        
        // Return top 6 courses
        return array_slice($scoredcourses, 0, 6);
    }
}
