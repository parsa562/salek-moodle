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
 * A question bank column which gathers together all the actions into a menu.
 *
 * @package   core_question
 * @copyright 2019 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\local\bank;

use \core\plugininfo\qbank;

/**
 * A question bank column which gathers together all the actions into a menu.
 *
 * This question bank column, if added to the question bank, will
 * replace all of the other columns which implement the
 * {@see menu_action_column_base} interface and replace them with a single
 * column containing an Edit menu.
 *
 * @copyright 2019 The Open University
 * @author    2021 Safat Shahin <safatshahin@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_menu_column extends column_base {
    public function get_title() {
        return get_string('actions');
    }

    public function get_name(): string {
        return 'editmenu';
    }

    protected function display_content($question, $rowclasses): void {
        global $OUTPUT;
        $actions = $this->qbank->get_question_actions();

        $menu = new \action_menu();
        $menu->set_menu_trigger(get_string('edit'));
        $menu->set_boundary('window');

        if ($this->qbank->use_card_layout()) {
            // Salek: edit and preview as direct icon buttons; the menu (with every action) behind a "more" button.
            $direct = '';
            foreach (['qbank_editquestion\\edit_action', 'qbank_previewquestion\\preview_action'] as $key) {
                $link = isset($actions[$key]) ? $actions[$key]->get_action_menu_link($question) : null;
                if ($link) {
                    $direct .= \html_writer::link($link->url,
                        $OUTPUT->pix_icon($link->icon->pix, '', $link->icon->component),
                        ['class' => 'qbank-row-action', 'title' => $link->text, 'aria-label' => $link->text]);
                }
            }
            echo \html_writer::start_div('qbank-row-actions') . $direct;
            $menu->set_kebab_trigger(get_string('moreactions'), $OUTPUT, 'qbank-row-action');
        }
        foreach ($actions as $action) {
            $action = $action->get_action_menu_link($question);
            if ($action) {
                $menu->add($action);
            }
        }

        $qtypeactions = \question_bank::get_qtype($question->qtype, false)
                ->get_extra_question_bank_actions($question);
        foreach ($qtypeactions as $action) {
            $menu->add($action);
        }

        echo $OUTPUT->render($menu);
        if ($this->qbank->use_card_layout()) {
            echo \html_writer::end_div();
        }
    }

    public function get_required_fields(): array {
        return ['q.qtype'];
    }

    /**
     * Get menuable actions.
     *
     * @return menu_action_column_base Menuable actions.
     */
    public function get_actions(): array {
        return $this->actions;
    }

    public function get_extra_classes(): array {
        return ['pe-3'];
    }

}
