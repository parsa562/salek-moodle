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

namespace qbank_deletequestion;

/**
 * Class helper of qbank_deletequestion.
 *
 * @package qbank_deletequestion
 * @copyright 2023 The Open University
 * @since Moodle 4.2
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get the confirmation message of delete question.
     *
     * @param array $questionids List of id questions.
     * @param bool $deleteallversions Delete all question version or not.
     * @return array List confirmation message.
     */
    public static function get_delete_confirmation_message(array $questionids, bool $deleteallversions): array {
        global $DB;
        $questionnames = '';
        $inuse = false;
        $questionversions = [];
        $countselectedquestion = count($questionids);

        $versionsofeachquestionbankentry = $deleteallversions
            ? \question_bank::get_all_versions_of_questions($questionids)
            : \question_bank::get_version_of_questions($questionids);

        foreach ($versionsofeachquestionbankentry as $entryid => $versions) {
            // Re-order to the oldest first.
            $versionsofeachquestionbankentry[$entryid] = array_reverse($versions, true);
            // Flip the array to list question by question id. [ qid => version ].
            $questionversions += array_flip($versions);
        }
        // Flatten an array.
        $questionids = array_merge(...$versionsofeachquestionbankentry);

        // Get the names of all the questions.
        $questions = $DB->get_records_list('question', 'id', $questionids, '', 'id, name');

        // Which of the versions are in use, asked for all of them at once (Salek: it was asked for
        // each version, a few database queries each; the question bank builds this message for
        // every question it lists, so a page of 100 questions of about 20 versions each sent
        // about 5000 queries and took seconds).
        $inuseids = self::get_questions_in_use($questionids);

        // Build the message.
        foreach ($questionids as $questionid) {
            if (isset($inuseids[$questionid])) {
                $questionnames .= '* ';
                $inuse = true;
            }
            $questionname = format_string($questions[$questionid]->name);

            $a = new \stdClass();
            $a->name = $questionname;
            $a->version = $questionversions[$questionid];
            $questionnames .= get_string('questionnameandquestionversion', 'question', $a) . '<br />';
        }

        // Add the in-use message if required.
        if ($inuse) {
            $questionnames .= '<br />'.get_string('questionsinuse', 'question');
        }

        // Add in the right tile and message text.
        $confirmtitle = [
            'confirmtitle' => $countselectedquestion > 1 ? get_string('deleteversiontitle_plural',
                'question') : get_string('deleteversiontitle', 'question'),
        ];
        $message = get_string('deleteselectedquestioncheck', 'question', $questionnames);
        if ($deleteallversions) {
            $confirmtitle = [
                'confirmtitle' => get_string('deletequestiontitle', 'question'),
            ];
            $message = get_string('deletequestioncheck', 'question', $questionnames);
            if ($countselectedquestion > 1) {
                $confirmtitle = [
                    'confirmtitle' => get_string('deletequestiontitle_plural', 'question'),
                ];
                $message = get_string('deletequestionscheck', 'question', $questionnames);
            }
        }

        return [$confirmtitle, $message];
    }

    /**
     * Which of these questions are in use: for each of them, the answer questions_in_use() gives,
     * but asked for all of them at once where it can be.
     *
     * A question is in use when an activity refers to it (question_reference_manager) or when a
     * plugin says so (the questions_in_use callback: in core only the quiz, for its attempts that
     * are not previews). The quiz's answer is asked here for all the questions in one query; any
     * other plugin's (and the deprecated question_list_instances callbacks), which only answer
     * yes or no for a list, are asked for each question still not found in use, as before.
     *
     * @param array $questionids Question ids.
     * @return array The ids of those in use, as keys.
     */
    protected static function get_questions_in_use(array $questionids): array {
        global $DB;

        if (!$questionids) {
            return [];
        }
        // (question_engine::questions_in_use() without usages to look in: always false.)

        // Referred to by an activity.
        $inuse = array_fill_keys(
            \core_question\question_reference_manager::questions_with_references($questionids), true);

        // The plugins that say whether they use questions.
        $callbacksbytype = get_plugins_with_function('questions_in_use');
        $quiz = ($callbacksbytype['mod']['quiz'] ?? '') === 'quiz_questions_in_use';
        $others = false;
        foreach ($callbacksbytype as $type => $callbacks) {
            foreach ($callbacks as $plugin => $function) {
                if (!($type === 'mod' && $plugin === 'quiz' && $quiz)) {
                    $others = true;
                }
            }
        }
        if (get_plugin_list_with_function('mod', 'question_list_instances')) {
            $others = true;
        }

        // The quiz: answered in an attempt that is not a preview (quiz_questions_in_use()).
        $remaining = array_values(array_diff($questionids, array_keys($inuse)));
        if ($quiz && $remaining) {
            [$test, $params] = $DB->get_in_or_equal($remaining);
            $used = $DB->get_fieldset_sql("
                    SELECT DISTINCT qa.questionid
                      FROM {question_attempts} qa
                     WHERE qa.questionid $test
                           AND qa.questionusageid IN (SELECT quiza.uniqueid
                                                        FROM {quiz_attempts} quiza
                                                       WHERE quiza.preview = 0)", $params);
            $inuse += array_fill_keys($used, true);
        }

        // Any other plugin: for each question still not in use, as questions_in_use() asks.
        if ($others) {
            foreach ($questionids as $questionid) {
                if (!isset($inuse[$questionid]) && questions_in_use([$questionid])) {
                    $inuse[$questionid] = true;
                }
            }
        }

        return $inuse;
    }

    /**
     * Delete questions has (single/multiple) version.
     *
     * @param array $questionids List of questionid.
     * @param bool $deleteallversions Delete all question version or not.
     */
    public static function delete_questions(array $questionids, bool $deleteallversions): void {
        if ($deleteallversions) {
            // Get all the question id from multidimensional array.
            $listofquestions = \question_bank::get_all_versions_of_questions($questionids);
            // Flatten an array.
            $questionids = array_merge(...$listofquestions);
        }
        foreach ($questionids as $questionid) {
            $questionid = (int) $questionid;
            question_require_capability_on($questionid, 'edit');
            question_delete_question($questionid);
        }
    }
}
