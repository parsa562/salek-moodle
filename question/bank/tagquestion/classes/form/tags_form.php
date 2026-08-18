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

namespace qbank_tagquestion\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

/**
 * The mform class for  manage question tags.
 *
 * @package   qbank_tagquestion
 * @copyright 2018 Simey Lameze <simey@moodle.com>
 * @author    2021 Safat Shahin <safatshahin@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tags_form extends \moodleform {

    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->disable_form_change_checker();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'categoryid');
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('static', 'questionname', get_string('questionname', 'question'));
        $mform->addElement('static', 'questioncategory', get_string('categorycurrent', 'question'));
        $mform->addElement('static', 'context', '');

        if (\core_tag_tag::is_enabled('core_question', 'question')) {
            $tags = $customdata['currenttags'] ?? [];
            $tagstrings = [];
            foreach ($tags as $tag) {
                $tagname = $tag->get_display_name();
                $tagstrings[$tagname] = $tagname;
            }

            $showstandard = \core_tag_area::get_showstandard('core_question', 'question');
            if ($showstandard != \core_tag_tag::HIDE_STANDARD) {
                $namefield = empty($CFG->keeptagnamecase) ? 'name' : 'rawname';
                $standardtags = $DB->get_records('tag',
                        ['isstandard' => 1,
                         'tagcollid' => \core_tag_area::get_collection('core', 'question')],
                        $namefield, 'id,' . $namefield);
                foreach ($standardtags as $standardtag) {
                    $tagstrings[$standardtag->$namefield] = $standardtag->$namefield;
                }
            }

            natcasesort($tagstrings);

            $options = [
                'tags' => ($showstandard != \core_tag_tag::STANDARD_ONLY),
                'multiple' => true,
                'noselectionstring' => get_string('anytags', 'quiz'),
            ];
            $mform->addElement('autocomplete', 'tags',  get_string('tags'), $tagstrings, $options);
        }
    }

}
