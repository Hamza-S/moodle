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
 * Poll the server to keep the session alive.
 *
 * @module     core/network
 * @package    core
 * @copyright  2019 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/config', 'core/notification', 'core/str'],
        function($, Ajax, Config, Notification, Str) {

    var started = false;
    var keepAliveFrequency = 0;
    var checkFrequency = (Config.sessiontimeout / 10) * 1000;
    var warningLimit = Math.max(checkFrequency * 2, 900000); // 15 minutes or 1/5 of sessiontimeout.

    var touchSession = function() {
        var request = {
            methodname: 'core_session_touch',
            args: { }
        };

        return Ajax.call([request], true, true)[0].then(function() {
            setTimeout(touchSession, keepAliveFrequency);
            return true;
        }).fail(Notification.exception);
    };

    var checkSession = function() {
        var request = {
            methodname: 'core_session_time_remaining',
            args: { }
        };

        return Ajax.call([request], true, true)[0].then(function(timeremaining) {
            if (timeremaining * 1000 < warningLimit) {

                Str.get_strings([
                    {key: 'inactive', component: 'moodle'},
                    {key: 'sessiontimeoutsoon', component: 'error'},
                ]).then(function(strings) {
                    Notification.confirm(strings[0], strings[1],
                    'Extend session', // Delete.
                    'Cancel', // Cancel.
                    function() {
                        console.log('Extend it.');
                        return true;
                    });
                    return true;
                }).fail(Notification.exception);
                setTimeout(checkSession, warningLimit);
            } else {
                setTimeout(checkSession, checkFrequency);
            }
            return true;
        }).fail(Notification.exception);
    };

    var start = function() {
        if (keepAliveFrequency > 0) {
            setTimeout(touchSession, keepAliveFrequency);
        } else {
            setTimeout(checkSession, checkFrequency);
        }
    };

    var init = function() {
        // We only allow one concurrent instance of this checker.
        if (started) {
            return;
        }
        started = true;

        start();
    };

    var keepalive = function(freq) {
        // We only allow one concurrent instance of this checker.
        if (started) {
            return;
        }
        started = true;

        keepAliveFrequency = freq * 1000;
        start();
    };

    return {
        keepalive: keepalive,
        init: init
    };
});
