<?php
/**
 * Copyright (c) 2008, Till Klampaeckel
 * 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *  * Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *  * Redistributions in binary form must reproduce the above copyright notice, this
 *    list of conditions and the following disclaimer in the documentation and/or
 *    other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * PHP Version 5
 *
 * @author   Till Klampaeckel <till@php.net>
 * @license  http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @link     http://www.lagged.de/wordpress/404.phps
 */

/**
 * This is used to redirect from on old MovableType URLs to Wordpress ones.
 * And that's all.
 */

/**
 * SQL data
 * @var string
 */
$db_user = 'root';
$db_pass = '';
$db_name = 'wordpress';
$db_host = 'localhost';

/**
 * Wordpress MU's table for posts.
 * @var string $posts_table
 */
$posts_table = 'wp_posts';

/**
 * To redirect when nothing is found.
 * @var string
 */
$url_not_found = '/advanced-search/';

/*****************************************************************
 * No editing below necessary. :-) Unless of course it's needed! *
 ****************************************************************/

$_uri = (string) @$_SERVER['REQUEST_URI'];
if (empty($_uri)) {
    header('Location:' . $url_not_found, true, 404);
    exit;
}

/**
 * @var string $_lookup This is the post_name in wordpress.
 */
$_lookup = basename($_uri);
$_lookup = str_replace('.html', '', $_lookup);

/**
 * @var string $_date Extract date (year/month from the old url.
 */
$_date = str_replace('.html', '', $_uri);
$_date = str_replace('/' . $_lookup, '', $_date);
$_date = str_replace('/archives/', '', $_date);

list($year, $month) = explode('/', $_date);
if (empty($year) || empty($month)) {
    header('Location:' . $url_not_found, true, 404);
    exit;
}

if (empty($_lookup)) {
    header('Location:' . $url_not_found, true, 404);
    exit;
}

$db = @mysql_connect($db_host, $db_user, $db_pass);
if (!$db) {
    trigger_error(mysql_error(), E_USER_WARNING);
    exit;
}
$status = @mysql_select_db($db_name);
if (!$status) {
    trigger_error(mysql_error(), E_USER_WARNING);
    shut_down($db);
    exit;
}

$query  = "SELECT post_title, guid FROM `%s`";
$query .= " WHERE post_name = '%s'";
$query .= " AND post_status = 'publish'";
$query .= " AND post_date >= '%s-%s-01 00:00:00'";
$query .= " AND post_date < '%s-%02d-01 00:00:00'";

$month_end = intval($month);
$month_end++;
if ($month_end > 12) {
    $month_end = 1;
    $year_end  = intval($year);
    $year_end++;
} else {
    $year_end = $year;
}

$query  = sprintf($query,
    mysql_real_escape_string($posts_table),
    mysql_real_escape_string($_lookup),
    $year, $month, $year_end, $month_end);

//var_dump($query); exit;

$rawdb = mysql_query($query, $db);

if (!$rawdb) {
    trigger_error(mysql_error(), E_USER_WARNING);
    shut_down($db);
    exit;
}

//var_dump(mysql_num_rows($rawdb)); exit;

if (($row_count = mysql_num_rows($rawdb)) == 0) {
    header($url_not_found, true, 404);
    shut_down($db);
    exit;
}

if ($row_count == 1) {
    $row = mysql_fetch_object($rawdb);
    header('Location: ' . $row->guid, true, 301);
    shut_down($db, $rawdb);
    exit;
}

header('HTTP/1.1 300 Multiple Choices');
echo '<h1>Sorry, multiple matches!</h1>';
echo '<ul>';
while ($row = mysql_fetch_object($rawdb)) {
    echo "<li><a href=\"{$row->guid}\">{$row->post_title}</a></li>";
}
echo '</ul>';

shut_down($db, $rawdb);

/**
 * shut_down is executed as cheap GC excuse before all calls to exit.
 *
 * @access public
 * @param  mixed $db    Null, or a db resource.
 * @param  mixed $rawdb Null, or a result.
 *
 * @return void
 */
function shut_down($db = null, $rawdb = null) {
    if ($rawdb !== null) {
        @mysql_free_result($rawdb);
    }
    if ($db !== null) {
        @mysql_close($db);
    }
}
?>
