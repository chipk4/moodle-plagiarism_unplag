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
 * version.php
 *
 * @package   plagiarism_unplag
 * @author     Dan Marsden <Dan@danmarsden.com>
 * @author Mikhail Grinenko <m.grinenko@p1k.co.uk>
 * @copyright 2014 Dan Marsden <Dan@danmarsden.com>
 * @copyright   UKU Group, LTD, https://www.unplag.com 
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$plugin->version = 2015090902;
$plugin->requires = 2015051101.00;
$plugin->cron     = 60; // Only run every 1 minute.
$plugin->component = 'plagiarism_unplag';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2';
