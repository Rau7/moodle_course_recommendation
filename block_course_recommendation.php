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
 * Course recommendation block.
 *
 * @package    block_course_recommendation
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course recommendation block class
 *
 * @package    block_course_recommendation
 * @copyright  2025 Alp Toker
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_course_recommendation extends block_base {

    /**
     * Initialize the block
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_course_recommendation');
    }

    /**
     * This block can be added to any page type
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'all' => true
        );
    }

    /**
     * Allow multiple instances of this block
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Has config
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Return the content of this block
     *
     * @return stdClass the content
     */
    public function get_content() {
        global $DB, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        if (!isloggedin() || isguestuser()) {
            $this->content = new stdClass();
            $this->content->text = get_string('login_required', 'block_course_recommendation');
            $this->content->footer = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get recommended courses
        $courses = $this->get_recommended_courses($USER->id);

        if (empty($courses)) {
            $this->content->text = get_string('no_recommendations', 'block_course_recommendation');
            return $this->content;
        }

        $this->content->text = $this->render_courses($courses);
        return $this->content;
    }

    /**
     * Get recommended courses for the user
     *
     * @param int $userid The ID of the current user
     * @return array Array of recommended course objects
     */
    private function get_recommended_courses($userid) {
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

    /**
     * Render the recommended courses
     *
     * @param array $courses Array of course objects
     * @return string HTML content
     */
    private function render_courses($courses) {
        global $CFG;
        $textX = '';

        foreach ($courses as $course) {
            $courseid = $course->id;
            $courseimg = $this->get_course_image($courseid);
            if (empty($courseimg)) {
                $courseimg = $CFG->wwwroot . '/theme/image.php?theme=boost&component=core&image=i/course';
            }
            $coursename = $course->fullname;
            $coursecat = $course->categoryname;
            $courselink = $CFG->wwwroot . '/course/view.php?id=' . $courseid;
            $textX .= <<<EOD
                  <a class="card dashboard-card" href="$courselink">
                    <div class="card-img dashboard-card-img" style='background-image: url("$courseimg");'></div>
                    <div class="card-body pr-1 course-info-container c-card-cont">
                        <p class="c-name">$coursename</p>
                        <p class="c-cat-name">$coursecat</p>
                    </div>
                  </a>
EOD;
        }
        return <<<EOD
            <div class="card-deck dashboard-card-deck">
                $textX
            </div>
EOD;
    }

    /**
     * Get the course image URL using Moodle's overviewfiles logic
     * @param int $courseid
     * @return string
     */
    private function get_course_image($courseid) {
        global $CFG;
        $url = '';
        require_once($CFG->libdir . '/filelib.php');
        $context = \context_course::instance($courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0);
        foreach ($files as $f) {
            if ($f->is_valid_image()) {
                $url = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(), $f->get_filearea(), null, $f->get_filepath(), $f->get_filename(), false);
            }
        }
        return $url;
    }
}
