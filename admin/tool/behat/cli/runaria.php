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
 * Wrapper to run screen reader acceptance testing system on a clean behat site.
 *
 * @package    tool_behat
 * @copyright  2018 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
define('NO_OUTPUT_BUFFERING', true);
define('NEWLINE', "\n");

require_once(__DIR__ .'/../../../../config.php');
require_once(__DIR__.'/../../../../lib/clilib.php');
require_once(__DIR__.'/../../../../lib/behat/lib.php');
require_once(__DIR__.'/../../../../lib/behat/classes/behat_command.php');
require_once(__DIR__.'/../../../../lib/behat/classes/behat_config_manager.php');
require_once(__DIR__.'/../../../../lib/testing/classes/tests_finder.php');

if (isset($_SERVER['REMOTE_ADDR'])) {
    die(); // No access from web!
}

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');
ini_set('log_errors', '1');


list($options, $unrecognised) = cli_get_params(
    array(
        'feature'  => '',
        'help'     => false,
        'verbose'  => false
    ),
    array(
        'f' => 'feature',
        'h' => 'help',
        'v' => 'verbose'
    )
);

// Checking run.php CLI script usage.
$help = "
Command line helper to run screen reader acceptance testing system tests against a clean behat site.

Usage:
  php runaria.php [--feature=\"value\"] [--help]

Options:
--feature          Only execute specified feature file (Absolute path of feature file).

-h, --help         Print out this help

Example from Moodle root directory:
\$ php admin/tool/behat/cli/runaria.php --feature=\"path to feature file\"
";

if (!empty($options['help'])) {
    echo $help;
    exit(0);
}

chdir($CFG->dirroot);

// Add feature option if specified.
if ($options['feature']) {
    // Check the single file argument has a valid name.
    $pathinfo = pathinfo($options['feature']);
    $featurefilename = realpath($options['feature']);
    $validextension = 'js';
    $validsuffix = '_test';

    // The file is expected to be in a tests/aria folder and end with "_test.js".
    if ((isset($pathinfo['extension']) && $pathinfo['extension'] != $validextension) ||
            !$featurefilename ||
            (substr($pathinfo['filename'], -strlen($validsuffix)) !== $validsuffix)) {
        echo 'The specified feature file "' . $options['feature'] . '" does not exist or is incorrectly named.';
        echo NEWLINE;
        exit(1);
    }

    // The file is good, we add it to our list.
    $features = [$featurefilename];
} else {
    // Walk the directory tree looking for feature files.
    $features = get_all_aria_tests();
}

// Build a single file containing all the util functions that can be used by the test.
$utilsfile = build_combined_aria_utils_file();

// Now run the feature files.
$command = get_scrats_command($utilsfile);

if ($options['verbose']) {
    $command = $command . ' -v ';
}

// Run the test suite once for each test file.
foreach ($features as $featurefile) {
    // Reset the testing site before each feature.
    reset_acceptance_testing_site();
    $status = 0;
    $instancecommand = $command . ' --feature=' . $featurefile;

    passthru("$instancecommand", $status);
    if ($status != 0) {
        exit($status);
    }
}

// All finished.
exit(0);

/**
 * Return the command to run the scrats process including the site url.
 *
 * @param string $utilsfile A single javascript file to include before the test.
 * @return string The base command to run the test.
 */
function get_scrats_command($utilsfile) {
    global $CFG;
    $behatconfig = behat_config_manager::get_behat_cli_config_filepath();

    $exec = 'scrats';

    if (!isset($CFG->behat_wwwroot) ||
            !file_exists($behatconfig)) {
        echo 'Behat has not been initialised for this site.';
        echo NEWLINE;
        exit(1);
    }

    $siterooturl = $CFG->behat_wwwroot;
    $utilsarg = escapeshellarg($utilsfile);
    return $exec . ' -p ' . $utilsarg . ' ' . $siterooturl;
}

/**
 * Reset testing site.
 *
 * This ensures the tests run starting from a clean site each time.
 */
function reset_acceptance_testing_site() {
    $behatopts = "--tags=@behat_reset";
    $cwd = getcwd();
    chdir(__DIR__);
    $runtestscommand = behat_command::get_behat_command(false, false, true);
    $runtestscommand .= ' --config ' . behat_config_manager::get_behat_cli_config_filepath();
    $runtestscommand .= ' ' . $behatopts;

    $resetoutput = '';
    $resetstatus = 0;
    exec("php $runtestscommand", $resetoutput, $resetstatus);
    if ($resetstatus != 0) {
        // Fail early if the site is not ready.
        echo implode("\n", $resetoutput);
        exit($resetstatus);
    }

    chdir($cwd);
}

/**
 * Return a list of all test files.
 *
 * @return array The list of tests.
 */
function get_all_aria_tests() {
    // Walk the directory tree looking for feature files.
    $features = [];

    // Files must be in a tests/aria folder for the component.
    $components = tests_finder::get_components_with_tests('aria');
    $features = array();
    $featurespaths = array();
    $validtestspath = DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'aria';

    if ($components) {
        foreach ($components as $componentname => $path) {
            $path .= $validtestspath;
            if (file_exists($path)) {
                // We only add files ending with the name _test.js to the list of valid files.
                $componenttests = glob("$path/*_test.js");
                foreach ($componenttests as $testfile) {
                    array_push($features, $testfile);
                }
            }
        }
    }

    return $features;
}

/**
 * Build a single temporary file by combining all components util files.
 *
 * @return string The filename of the new file.
 */
function build_combined_aria_utils_file() {
    // Collect all common utils to a single preloaded javascript file.
    $utils = tests_finder::get_components_with_tests('ariautil');
    $tmpdir = make_request_directory();
    // Temp file to write to.
    $tmpfile = $tmpdir . DIRECTORY_SEPARATOR . 'util.js';
    // Start with empty file.
    $tmpfilehandle = fopen($tmpfile, 'w');
    fwrite($tmpfilehandle, '');
    // Naming convention for test util files.
    $validtestutilspath = DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'aria' . DIRECTORY_SEPARATOR . 'util.js';

    if ($utils) {
        foreach ($utils as $componentname => $path) {
            $path .= $validtestutilspath;
            if (file_exists($path)) {
                fwrite($tmpfilehandle, file_get_contents ($path));
            }
        }
    }
    fclose($tmpfilehandle);

    return $tmpfile;
}
