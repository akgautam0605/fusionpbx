<?php
/*
FusionPBX
Version: MPL 1.1

The contents of this file are subject to the Mozilla Public License Version
1.1 (the "License"); you may not use this file except in compliance with
the License. You may obtain a copy of the License at
http://www.mozilla.org/MPL/

Software distributed under the License is distributed on an "AS IS" basis,
WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
for the specific language governing rights and limitations under the
License.

The Original Code is FusionPBX

The Initial Developer of the Original Code is
Mark J Crane <markjcrane@fusionpbx.com>
Portions created by the Initial Developer are Copyright (C) 2008-2018
the Initial Developer. All Rights Reserved.

Contributor(s):
Mark J Crane <markjcrane@fusionpbx.com>
 */
//includes
require_once "root.php";
require_once "resources/require.php";
require_once "resources/check_auth.php";

//redirect admin to app instead
if (file_exists($_SERVER["PROJECT_ROOT"] . "/app/domains/app_config.php") && !permission_exists('domain_all')) {
    header("Location: " . PROJECT_PATH . "/app/domains/domains.php");
}

//check permission
if (permission_exists('voicemail_greeting_view')) {
    //access granted
} else {
    echo "access denied";
    exit;
}

//add multi-lingual support
$language = new text;
$text = $language->get();

//change the domain
if (is_uuid($_GET["domain_uuid"]) && $_GET["domain_change"] == "true") {
    if (permission_exists('domain_select')) {
        //get the domain_uuid
        $sql = "select * from v_domains ";
        // $sql .= "order by domain_name asc ";
        $database = new database;
        $result = $database->select($sql, null, 'all');
        if (is_array($result) && sizeof($result) != 0) {
            foreach ($result as $row) {
                if (count($result) == 0) {
                    $_SESSION["domain_uuid"] = $row["domain_uuid"];
                    $_SESSION["domain_name"] = $row['domain_name'];
                } else {
                    if ($row['domain_name'] == $domain_array[0] || $row['domain_name'] == 'www.' . $domain_array[0]) {
                        $_SESSION["domain_uuid"] = $row["domain_uuid"];
                        $_SESSION["domain_name"] = $row['domain_name'];
                    }
                }
            }
        }
        unset($sql, $result);

        //update the domain session variables
        $domain_uuid = $_GET["domain_uuid"];
        $_SESSION['domain_uuid'] = $domain_uuid;
        $_SESSION["domain_name"] = $_SESSION['domains'][$domain_uuid]['domain_name'];
        $_SESSION['domain']['template']['name'] = $_SESSION['domains'][$domain_uuid]['template_name'];

        //clear the extension array so that it is regenerated for the selected domain
        unset($_SESSION['extension_array']);

        //set the setting arrays
        $domain = new domains();
        $domain->db = $db;
        $domain->set();

        //redirect the user
        if ($_SESSION["login"]["destination"] != '') {
            // to default, or domain specific, login destination
            header("Location: " . PROJECT_PATH . $_SESSION["login"]["destination"]["url"]);
        } else {
            header("Location: " . PROJECT_PATH . "/core/dashboard.php");
        }
        exit;
    }
}

//redirect the user
if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/app/domains/domains.php")) {
    $href = '/app/domains/domains.php';
}

//includes
require_once "resources/header.php";
$document['title'] = "Refill Report";
require_once "resources/paging.php";

//get the http values and set them as variables
$search = $_GET["search"];
$order_by = $_GET["order_by"] != '' ? $_GET["order_by"] : 'domain_name';
$order = $_GET["order"];

$sql = "select astpp_customer from v_domains where domain_uuid = '" . $domain_uuid . "'";

$database = new database;
$accountid = $database->select($sql, null, 'column');
unset($sql);

$sql = "SELECT id from accounts where  number = '" . $accountid . "'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_array($result);
$id = $row['id'];

$sql = "SELECT * from invoice_details where product_category = 3 AND charge_type = 'REFILL' AND accountid = " . $id;
$sql .= " AND (";
$sql .= " 	created_date like '%" . strtolower($search) . "%'  ";
$sql .= " 	or credit  like '%" . strtolower($search) . "%' ";
$sql .= ") ";
// echo $sql;

$result = mysqli_query($conn, $sql);
$num_rows = mysqli_num_rows($result);

//prepare to page the results
$rows_per_page = ($_SESSION['domain']['paging']['numeric'] != '') ? $_SESSION['domain']['paging']['numeric'] : 50;
$param = "";
$page = $_GET['page'];
if (strlen($page) == 0) {$page = 0;
    $_GET['page'] = 0;}
list($paging_controls, $rows_per_page, $var3) = paging($num_rows, $param, $rows_per_page);
$offset = $rows_per_page * $page;

//get the domains

$c = 0;
$row_style["0"] = "row_style0";
$row_style["1"] = "row_style1";

//show the header and the search

echo "<table class='list'>\n";
echo "<tr class='list-header'>\n";
echo "		<td width='50%' align='left' valign='top' nowrap='nowrap'><b>Refill report (" . $num_rows . ")</b></td>\n";
echo "		<td width='50%' align='right' valign='top'>\n";
echo "			<form method='get' action=''>\n";
echo "			<input type='text' class='txt' style='width: 150px' name='search' value='" . escape($search) . "'>";
echo "			<input type='submit' class='btn' name='submit' value='" . $text['button-search'] . "'>";
echo "			</form>\n";
echo "		</td>\n";
echo "	</tr>\n";
echo "	<tr>\n";
echo "		<td align='left' valign='top' colspan='2'>\n";
echo "			List of Refill report<br /><br />\n";
echo "		</td>\n";
echo "	</tr>\n";
echo "</table>\n";

echo "<div class='card'>\n";
echo "<table class='list'>\n";
echo "<tr>\n";
echo "<th>Date</th>";
echo "<th> Amount ($)</th>";
echo "<th>Payment method</th>";
echo "<th>Description</th>";

echo "</tr>\n";

while ($row = mysqli_fetch_array($result)) {

    echo "<tr " . $tr_link . ">\n";
    echo "	<td valign='top' class='" . $row_style[$c] . "' " . (($indent != 0) ? "style='padding-left: " . ($indent * 20) . "px;'" : null) . ">";

    echo $row['created_date'];
    echo "	</td>\n";

    echo "	<td valign='top' class='" . $row_style[$c] . "'>" . $row['credit'] . "</td>\n";

    $sql1 = "SELECT payment_method from payment_transaction where  id = '" . $row['id'] . "'";
    $result1 = mysqli_query($conn, $sql1);
    $acc = mysqli_fetch_array($result1);
    echo "	<td valign='top' class='" . $row_style[$c] . "'>" . $acc['payment_method'] . "</td>\n";

    echo "	<td valign='top' class='" . $row_style[$c] . "'>" . $row['description'] . "</td>\n";
    echo "</tr>\n";
    $c = ($c == 0) ? 1 : 0;
}

    echo "</table>\n";
    echo "</div>\n";
    echo "<br />\n";
    echo "<div align='center'>".$paging_controls."</div>\n";

//include the footer
require_once "resources/footer.php";