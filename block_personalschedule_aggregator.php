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
 * @package   block_personalschedule_aggregator
 * @copyright 2019 onwards Vladislav Kovalev  snouwer@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once($CFG->dirroot . '/course/lib.php');
include_once($CFG->libdir . '/gradelib.php');
include_once($CFG->libdir . '/coursecatlib.php');

class block_personalschedule_aggregator extends block_base
{
    const PERSONALIZATION_MODNAME = 'personalschedule';

    function init()
    {
        $this->title = get_string('pluginname', 'block_personalschedule_aggregator');
    }

    function has_config()
    {
        return false;
    }

    function get_content()
    {
        global $CFG, $USER;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = "";
        $this->content->footer = "";

        if (empty($CFG->disablemycourses) and isloggedin() and !isguestuser()) {
            if ($courses = enrol_get_my_courses()) {

                // Sets block title and adds icon to it.
                $titleIconPicUrl = new moodle_url('/blocks/personalschedule_aggregator/pix/icon.png');
                $blockNameLocalized = get_string('pluginname', 'block_personalschedule_aggregator');
                $titleHtml = html_writer::tag('img',
                    " ".$blockNameLocalized, array(
                        'src' => $titleIconPicUrl,
                        'alt' => get_string('titleiconalt', 'block_personalschedule_aggregator')
                    ));
                $this->title = $titleHtml;

                $tableCurrentInfo = new html_table();
                $tableCurrentInfo->head = array(
                    get_string('currentinfo_curtime', 'block_personalschedule_aggregator'),
                    get_string('currentinfo_period', 'block_personalschedule_aggregator'),
                    get_string('currentinfo_day', 'block_personalschedule_aggregator')
                );

                $curTime = time();
                $periodIdx = mod_personalschedule_proposer::personal_items_get_period_idx($curTime);
                $dayIdx = mod_personalschedule_proposer::personal_items_get_day_idx($curTime);
                $tableCurrentInfo->data = array(array(
                    date("G:i", $curTime),
                    mod_personalschedule_proposer_ui::personalschedule_get_period_localize_from_idx($periodIdx),
                    mod_personalschedule_proposer_ui::personalschedule_get_day_localize_from_idx($dayIdx),
                    ));

                $currentDayInfoTableHtml = html_writer::table($tableCurrentInfo);
                $this->content->text .= $currentDayInfoTableHtml;

                foreach ($courses as $course) {
                    if (!$course->visible) continue;
                    $personalscheduleCms = $this->get_personalschedule_cms_by_course_id($course->id);
                    if (!empty($personalscheduleCms)) {
                        foreach ($personalscheduleCms as $personalscheduleCm) {
                            $this->content->text .= mod_personalschedule_proposer_ui::get_proposed_table(
                                $course, $USER->id, $personalscheduleCm, $curTime, $dayIdx, $periodIdx);
                        }
                    } else {
                        $this->content->text .= get_string(
                            'coursenothavepersonalization', 'block_personalschedule_aggregator');
                    }
                }

            } else {
                $this->content->text .= get_string(
                    'nocourses', 'block_personalschedule_aggregator');
            }
        }

        return $this->content;
    }

    /**
     * Tries to get all cm_info of mod_personalschedule from the course, but filters not visible instances.
     * If there aren't instances of the module, then returns empty array.
     * If instances are not available for the users, then returns empty array.
     * @param int $course_id Course ID.
     * @return cm_info[] Array with cm_info of mod_personalschedule instances in the course.
     * @throws moodle_exception
     */
    function get_personalschedule_cms_by_course_id($course_id)
    {
        $modinfo = get_fast_modinfo($course_id);

        $foundCms = $modinfo->get_instances_of(self::PERSONALIZATION_MODNAME);

        foreach ($foundCms as $cm) {
            if (!$cm->uservisible) {
                unset($cm, $foundCms);
            }
        }

        if (empty($foundCms)) {
            return array();
        }

        return $foundCms;
    }
}

