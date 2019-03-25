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
 * Class for backpack persistence.
 *
 * @package    core_badges
 * @copyright  2019 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_badges;
defined('MOODLE_INTERNAL') || die();

use moodle_url;
use stdClass;
use OpenBadgesBackpackHandler;

/**
 * Class for storing and updating a backpack in the database.
 *
 * @copyright  2019 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backpack {

    /** @var int Backpack id */
    public $id;

    /** @var int userid for the backpack */
    public $userid;

    /** @var string email Email address for the account on the backpack */
    public $email;

    /** @var string backpackurl Url to access the backpack */
    public $backpackurl;

    /** @var string backpackuid Unique id for this user in the backpack */
    public $backpackuid;

    /** @var bool autosync ... */
    public $autosync;

    /** @var string password Password for this user in the backpack site */
    public $password;

    /**
     * Constructs with backpack details.
     *
     * @param int $backpackid
     */
    public function __construct($backpackid) {
        global $DB;
        $this->id = $backpackid;

        $data = $DB->get_record('badge_backpack', array('id' => $backpackid));

        if (empty($data)) {
            print_error('error:backpackproblem', 'badges', $backpackid);
        }

        $this->userid = $data->userid;
        $this->email = $data->email;
        $this->backpackurl = $data->backpackurl;
        $this->backpackuid = $data->backpackuid;
        $this->autosync = $data->autosync;
        $this->password = $data->password;
    }

    /**
     * Save/update backpack information in 'badge_backpack' table only.
     * Not for general use - allowed for unit tests.
     *
     * @return bool Returns true on success.
     */
    public function save() {
        global $DB;

        if (defined('PHPUNIT_TEST') and PHPUNIT_TEST) {

            $record = new stdClass();
            $record->id = $this->id;
            $record->userid = $this->userid;
            $record->email = $this->email;
            $record->backpackurl = $this->backpackurl;
            $record->backpackuid = $this->backpackuid;
            $record->autosync = $this->autosync;
            $record->password = $this->password;

            return $DB->update_record('badge_backpack', $record);
        } else {
            throw new coding_exception('Updating a backpack is not supported');
        }
    }

    /**
     * Insert a new backpack in the database and return a backpack for it.
     *
     * @param string $email Email stored in the backpack
     * @param string $backpackurl Url to access the backpack
     * @param string $backpackuid User id stored in the backpack
     * @param bool $autosync
     * @param string $password Password to access this backpack
     * @return backpack
     */
    public static function create(string $email,
                                  string $backpackurl,
                                  string $backpackuid,
                                  bool $autosync,
                                  string $password): backpack {
        global $DB, $USER;

        $backpackrecord = new stdClass();
        $backpackrecord->userid = $USER->id;
        $backpackrecord->email = clean_param($email, PARAM_EMAIL);
        $backpackrecord->backpackurl = clean_param($backpackurl, PARAM_URL);
        $backpackrecord->backpackuid = $backpackuid;
        $backpackrecord->autosync = $autosync;
        $backpackrecord->password = $password;

        $newbackpackid = $DB->insert_record('badge_backpack', $backpackrecord);
        return new backpack($newbackpackid);
    }

    /**
     * Check if a valid secret was passed to verify a backpack.
     *
     * @param string $secret Secret param to verify the backpack.
     * @return array(moodle_url, message, messagetype);
     */
    public static function verify_backpack_email(string $secret) {
        $redirect = '/badges/mybackpack.php';
        // Confirm the secret and create the backpack connection.
        $storedsecret = get_user_preferences('badges_email_verify_secret');
        if (!is_null($storedsecret)) {
            if ($secret === $storedsecret) {
                $storedemail = get_user_preferences('badges_email_verify_address');

                $data = new stdClass();
                $data->backpackurl = BADGE_BACKPACKURL;
                $data->email = $storedemail;
                $bp = new OpenBadgesBackpackHandler($data);

                // Make sure we have all the required information before trying to save the connection.
                $backpackuser = $bp->curl_request('user');
                if (isset($backpackuser->status) && $backpackuser->status === 'okay' && isset($backpackuser->userId)) {
                    $backpackuid = $backpackuser->userId;
                } else {
                    return [
                        new moodle_url($redirect),
                        get_string('backpackconnectionunexpectedresult', 'badges'),
                        \core\output\notification::NOTIFY_ERROR
                    ];
                }

                $backpack = self::create($data->email, $data->backpackurl, $backpackuid, 0, '');

                // Remove the verification vars and redirect to the mypackpack page.
                unset_user_preference('badges_email_verify_secret');
                unset_user_preference('badges_email_verify_address');
                return [
                    new moodle_url($redirect),
                    get_string('backpackemailverifysuccess', 'badges'),
                    \core\output\notification::NOTIFY_SUCCESS
                ];
            } else {
                // Stored secret doesn't match the supplied secret. Take user back to the mybackpack page and present a warning message.
                return [
                    new moodle_url($redirect),
                    get_string('backpackemailverifytokenmismatch', 'badges'),
                    \core\output\notification::NOTIFY_ERROR
                ];
            }
        } else {
            // Stored secret is null. Either the email address has already been verified, or there is no record of a verification attempt
            // for the current user. Either way, just redirect to the mybackpack page.
            return [new moodle_url($redirect), '', \core\output\notification::NOTIFY_INFO];
        }
    }
}
