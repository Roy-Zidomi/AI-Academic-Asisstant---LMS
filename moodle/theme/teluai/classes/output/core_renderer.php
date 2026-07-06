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

namespace theme_teluai\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom core renderer for theme_teluai to override specific output components.
 *
 * @package    theme_teluai
 * @copyright  2026 AI-AA LMS Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Overrides the user_menu function to customize the user menu.
     * When a user switches their role, it will show only the role name (e.g. "Teacher") instead of "Username Role".
     *
     * @param stdClass $user
     * @param bool $withlinks
     * @return string
     */
    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        if (is_null($user)) {
            $user = $USER;
        }

        if (is_null($withlinks)) {
            $withlinks = empty($this->page->layout_options['nologinlinks']);
        }

        $usermenuclasses = 'usermenu';
        if (!$withlinks) {
            $usermenuclasses .= ' withoutlinks';
        }

        $returnstr = "";

        if (during_initial_install()) {
            return $returnstr;
        }

        $loginpage = $this->is_login_page();
        $loginurl = get_login_url();

        $opts = user_get_user_navigation_info($user, $this->page);

        if (!empty($opts->unauthenticateduser)) {
            $returnstr = get_string($opts->unauthenticateduser['content'], 'moodle');
            if (!$loginpage && (!$opts->unauthenticateduser['guest'] || $withlinks)) {
                $returnstr .= " (<a href=\"$loginurl\">" . get_string('login') . '</a>)';
            }

            return \html_writer::div(
                \html_writer::span(
                    $returnstr,
                    'login nav-link'
                ),
                $usermenuclasses
            );
        }

        $avatarclasses = "avatars";
        $avatarcontents = \html_writer::span($opts->metadata['useravatar'], 'avatar current');
        $usertextcontents = $opts->metadata['userfullname'];

        if (!empty($opts->metadata['asotheruser'])) {
            $avatarcontents .= \html_writer::span(
                $opts->metadata['realuseravatar'],
                'avatar realuser'
            );
            $usertextcontents = $opts->metadata['realuserfullname'];
            $usertextcontents .= \html_writer::tag(
                'span',
                get_string(
                    'loggedinas',
                    'moodle',
                    \html_writer::span(
                        $opts->metadata['userfullname'],
                        'value'
                    )
                ),
                array('class' => 'meta viewingas')
            );
        }

        // Role switch override: Only show the switched role name, not the user fullname!
        if (!empty($opts->metadata['asotherrole'])) {
            $usertextcontents = $opts->metadata['rolename'];
        }

        // User login failures.
        if (!empty($opts->metadata['userloginfail'])) {
            $usertextcontents .= \html_writer::span(
                $opts->metadata['userloginfail'],
                'meta loginfailures'
            );
        }

        // MNet.
        if (!empty($opts->metadata['asmnetuser'])) {
            $mnet = strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['mnetidprovidername'])));
            $usertextcontents .= \html_writer::span(
                $opts->metadata['mnetidprovidername'],
                'meta mnet mnet-' . $mnet
            );
        }

        $returnstr .= \html_writer::span(
            \html_writer::span($usertextcontents, 'usertext mr-1') .
            \html_writer::span($avatarcontents, $avatarclasses),
            'userbutton'
        );

        $divider = new \action_menu_filler();
        $divider->primary = false;

        $am = new \action_menu();
        $am->set_menu_trigger(
            $returnstr,
            'nav-link'
        );
        $am->set_action_label(get_string('usermenu'));
        $am->set_nowrap_on_items();
        if ($withlinks) {
            $navitemcount = count($opts->navitems);
            $idx = 0;
            foreach ($opts->navitems as $key => $value) {

                switch ($value->itemtype) {
                    case 'divider':
                        $am->add($divider);
                        break;

                    case 'invalid':
                        break;

                    case 'link':
                        $pix = null;
                        if (isset($value->pix) && !empty($value->pix)) {
                            $pix = new \pix_icon($value->pix, '', null, array('class' => 'iconsmall'));
                        } else if (isset($value->imgsrc) && !empty($value->imgsrc)) {
                            $value->title = \html_writer::img(
                                $value->imgsrc,
                                $value->title,
                                array('class' => 'iconsmall')
                            ) . $value->title;
                        }

                        $al = new \action_menu_link_secondary(
                            $value->url,
                            $pix,
                            $value->title,
                            array('class' => 'icon')
                        );
                        if (!empty($value->titleidentifier)) {
                            $al->attributes['data-title'] = $value->titleidentifier;
                        }
                        $am->add($al);
                        break;
                }

                $idx++;

                if ($idx == 1 || $idx == $navitemcount - 1) {
                    $am->add($divider);
                }
            }
        }

        return \html_writer::div(
            $this->render($am),
            $usermenuclasses
        );
    }
}
