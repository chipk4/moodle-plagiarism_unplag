<?php
// This file is part of the Checklist plugin for Moodle - http://moodle.org/
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
 * locallib.php - Stores all the functions for manipulating a plagiarism_unplag
 *
 * @package     plagiarism_unplag
 * @subpackage  plagiarism
 * @author      Vadim Titov <v.titov@p1k.co.uk>, Aleksandr Kostylev <a.kostylev@p1k.co.uk>
 * @copyright   UKU Group, LTD, https://www.unicheck.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core\event\base;
use plagiarism_unplag\classes\entities\providers\unplag_file_provider;
use plagiarism_unplag\classes\event\unplag_event_validator;
use plagiarism_unplag\classes\helpers\unplag_check_helper;
use plagiarism_unplag\classes\helpers\unplag_progress;
use plagiarism_unplag\classes\helpers\unplag_translate;
use plagiarism_unplag\classes\services\storage\unplag_file_state;
use plagiarism_unplag\classes\unplag_core;
use plagiarism_unplag\classes\unplag_settings;


global $CFG;

require_once($CFG->libdir . '/filelib.php');

require_once(dirname(__FILE__) . '/constants.php');
require_once(dirname(__FILE__) . '/autoloader.php');
/**
 * Class plagiarism_unplag
 *
 * @copyright   UKU Group, LTD, https://www.unicheck.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plagiarism_unplag {
    use unplag_translate;
    /**
     * @var array
     */
    private static $supportedplagiarismmods = [
        UNPLAG_MODNAME_ASSIGN, UNPLAG_MODNAME_WORKSHOP, UNPLAG_MODNAME_FORUM,
    ];
    /**
     * @var array
     */
    private static $supportedarchivemimetypes = [
        'application/zip',
    ];
    /**
     * @var array
     */
    private static $supportedfilearea = [
        UNPLAG_WORKSHOP_FILES_AREA,
        UNPLAG_DEFAULT_FILES_AREA,
        UNPLAG_FORUM_FILES_AREA,
        'submission_files',
        'submission_attachment',
        'attachment',
    ];
    /**
     * @var array
     */
    private static $supportedextension = [
        'pdf',
        'odt',
        'odp',
        'doc',
        'docx',
        'html',
        'txt',
        'rtf',
        'ppt',
        'pptx',
        'pages',
        'htm',
        'xls',
        'xlsx',
        'ods'
    ];

    /**
     * Handle all system events
     *
     * @param base $event
     */
    public static function event_handler(base $event) {
        if (unplag_event_validator::validate_event($event)) {
            $unplagevent = new \plagiarism_unplag\classes\entities\unplag_event();
            $unplagevent->process($event);
        }
    }

    /**
     * Verify supporting for modules like: assign, workshop, forum
     *
     * @param string $modname
     *
     * @return bool
     */
    public static function is_support_mod($modname) {
        return in_array($modname, self::$supportedplagiarismmods);
    }

    /**
     * Verify supporting for file areas
     *
     * @param string $filearea
     *
     * @return bool
     */
    public static function is_support_filearea($filearea) {
        return in_array($filearea, self::$supportedfilearea);
    }

    /**
     * Verify supporting for file extension
     *
     * @param string $ext
     *
     * @return bool
     */
    public static function is_supported_extension($ext) {
        return in_array(strtolower($ext), self::$supportedextension);
    }

    /**
     * Verify supporting for file mimetype
     *
     * @param stored_file $file
     *
     * @return bool
     */
    public static function is_archive(stored_file $file) {
        if ($mimetype = $file->get_mimetype()) {
            if (in_array($mimetype, self::$supportedarchivemimetypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert object to array
     *
     * @param object $obj
     *
     * @return array
     */
    public static function object_to_array($obj) {
        if (is_object($obj)) {
            $obj = (array)$obj;
        }
        if (is_array($obj)) {
            $new = [];
            foreach ($obj as $key => $val) {
                $new[$key] = self::object_to_array($val);
            }
        } else {
            $new = $obj;
        }

        return $new;
    }

    /**
     * Get list of files for current context
     *
     * @param int    $contextid
     * @param string $filearea
     * @param null   $itemid
     *
     * @return stored_file[]
     */
    public static function get_area_files($contextid, $filearea = UNPLAG_DEFAULT_FILES_AREA, $itemid = null) {

        $itemid = ($itemid !== null) ? $itemid : false;

        return get_file_storage()->get_area_files($contextid, UNPLAG_PLAGIN_NAME, $filearea, $itemid, null, false);
    }

    /**
     * Check whether the plugin is enabled
     *
     * @return null|false
     */
    public static function is_plugin_enabled() {
        return unplag_settings::get_settings('use');
    }

    /**
     * Get forum topic results
     *
     * @param context $context
     * @param array   $linkarray
     *
     * @return null|stored_file
     */
    public static function get_forum_topic_results($context, $linkarray) {
        $contenthash = unplag_core::content_hash($linkarray['content']);
        $file = unplag_core::get_file_by_hash($context->id, $contenthash);

        return $file;
    }

    /**
     * Error handler
     *
     * @param string $errorresponse
     *
     * @return string
     */
    public static function error_resp_handler($errorresponse) {
        $errors = json_decode($errorresponse, true);
        if (is_array($errors)) {
            $error = self::api_trans(current($errors));
        } else {
            $error = self::trans('unknownwarning');
        }

        return $error;
    }

    /**
     * Track current file status
     *
     * @param string $data
     *
     * @return string
     */
    public function track_progress($data) {
        $data = unplag_core::parse_json($data);
        $resp = [];
        $records = unplag_file_provider::find_by_ids($data->ids);
        if (!empty($records)) {
            $checkstatusforids = [];
            try {
                foreach ($records as $record) {
                    switch ($record->state) {
                        case unplag_file_state::UPLOADING:
                            unplag_progress::track_upload($record);
                            break;
                        case unplag_file_state::HAS_ERROR:
                            $resp[$record->id] = [
                                'file_id' => $record->id,
                                'state'   => $record->state,
                                'content' => unplag_progress::gen_row_content_score($data->cid, $record),
                            ];
                            break;
                        default:
                            $progressinfo = unplag_progress::get_check_progress_info($record, $data->cid, $checkstatusforids);
                            if ($progressinfo) {
                                $resp[$record->id] = $progressinfo;
                            }

                            if (!empty($checkstatusforids)) {
                                unplag_progress::get_real_check_progress($data->cid, $checkstatusforids, $resp);
                            }

                            break;
                    }
                }

            } catch (\Exception $ex) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                $resp['error'] = $ex->getMessage();
            }
        }

        return unplag_core::json_response($resp);
    }

    /**
     * Callback handler
     *
     * @param string $token
     *
     * @throws moodle_exception
     */
    public function unplag_callback($token) {
        global $DB;

        if (self::access_granted($token)) {
            $record = $DB->get_record(UNPLAG_FILES_TABLE, ['identifier' => $token]);
            $rawjson = file_get_contents('php://input');
            $respcheck = unplag_core::parse_json($rawjson);
            if ($record && isset($respcheck->check)) {
                $progress = 100 * $respcheck->check->progress;
                unplag_check_helper::check_complete($record, $respcheck->check, $progress);
            }
        } else {
            print_error('error');
        }
    }

    /**
     * Check access grunt
     *
     * @param string $token
     *
     * @return bool
     */
    private static function access_granted($token) {
        return ($token && strlen($token) === 40 && $_SERVER['REQUEST_METHOD'] == 'POST');
    }
}