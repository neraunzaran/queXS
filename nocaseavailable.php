<?
/**
 * Display error message when no cases available
 *
 *
 *	This file is part of queXS
 *	
 *	queXS is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation; either version 2 of the License, or
 *	(at your option) any later version.
 *	
 *	queXS is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *	
 *	You should have received a copy of the GNU General Public License
 *	along with queXS; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 *
 * @author Adam Zammit <adam.zammit@deakin.edu.au>
 * @copyright Deakin University 2007,2008
 * @package queXS
 * @subpackage user
 * @link http://www.deakin.edu.au/dcarf/ queXS was writen for DCARF - Deakin Computer Assisted Research Facility
 * @license http://opensource.org/licenses/gpl-2.0.php The GNU General Public License (GPL) Version 2
 * 
 * @todo Use calls to the database to determine the real reason why no case is available, not just give a list
 *
 */

/**
 * Configuration file
 */
include ("config.inc.php");

/**
 * Database file
 */
include ("db.inc.php");

/**
 * XHTML functions
 */
include ("functions/functions.xhtml.php");

/**
 * Operator functions
 */
include ("functions/functions.operator.php");

/**
 * Limesurvey functions
 */
include ("functions/functions.limesurvey.php");

xhtml_head(T_("No case available"),true,array("css/table.css"));

$operator_id = get_operator_id();

?>
<h1><? echo T_("There is no case currently available"); ?></h1>
<h2><? echo T_("Reasons:"); ?></h2>

<?

/**
 * check for reasons why no case is displayed
 */


//you have not been assigned to a questionnaire

$sql = "SELECT oq.questionnaire_id, q.description
	FROM operator_questionnaire as oq, questionnaire as q
	WHERE oq.operator_id = '$operator_id'
	AND q.questionnaire_id = oq.questionnaire_id";

$rs = $db->GetAll($sql);

?>
<p><? echo T_("Assigned questionnaires:"); ?></p>
<?
if (!empty($rs))
	xhtml_table($rs,array("questionnaire_id","description"),array(T_("ID"),T_("Description")));
else
{
	?> <p class='error'><? echo T_("ERROR: No questionnaires assigned to you"); ?></p> <?
}


//shift restrictions and no shift
$sql = "SELECT q.description, CONVERT_TZ(sh.start, 'UTC', o.Time_zone_name) as st, CONVERT_TZ(sh.end, 'UTC', o.Time_zone_name) as en
	FROM operator_questionnaire AS oq
	JOIN (questionnaire AS q, operator as o) ON ( oq.questionnaire_id = q.questionnaire_id and o.operator_id = oq.operator_id)
	LEFT JOIN shift AS sh ON (
		sh.questionnaire_id = oq.questionnaire_id
		AND (CONVERT_TZ( NOW( ) , 'System', 'UTC' ) >= sh.start )
		AND (CONVERT_TZ( NOW( ) , 'System', 'UTC' ) <= sh.end ))
	WHERE oq.operator_id = '$operator_id'
	AND !(q.restrict_work_shifts = 1 AND sh.shift_id IS NULL)";

$rs = $db->GetAll($sql);

?>
<p><? echo T_("Current shifts available:"); ?></p>
<?
if (!empty($rs))
	xhtml_table($rs,array("description","st","en"),array(T_("Questionnaire"),T_("Shift start"),T_("Shift end")));
else
{
	?> <p class='error'><? echo T_("ERROR: No shifts at this time"); ?></p> <?
}

//call restrictions and outside times
$sql = "SELECT count(*) as c
	FROM operator_questionnaire as oq
	JOIN (questionnaire_sample as qs, sample_import as si, sample as s) on (
		qs.questionnaire_id = oq.questionnaire_id
		and si.sample_import_id = qs.sample_import_id
		and s.import_id = si.sample_import_id)
	LEFT JOIN call_restrict as cr on (
		cr.day_of_week = DAYOFWEEK(CONVERT_TZ(NOW(), 'System' , s.Time_zone_name))
		and TIME(CONVERT_TZ(NOW(), 'System' , s.Time_zone_name)) >= cr.start
		and TIME(CONVERT_TZ(NOW(), 'System' , s.Time_zone_name)) <= cr.end)
	WHERE operator_id = '$operator_id'
		AND !(si.call_restrict = 1 AND cr.day_of_week IS NULL)";

$rs = $db->GetRow($sql);

?>
<p><? echo T_("Call restrictions:"); ?></p>
<?
if ($rs['c'] == 0)
{
	?> <p class='error'><? echo T_("ERROR: There are no cases available that fall within call restrictions"); ?></p> <?
}
else
{
	print "<p>" . T_("There are ") . $rs['c'] . T_(" unassigned case(s) available within the specified call restrictions") . "</p>";
}



?>
<p><? echo T_("Limesurvey links:"); ?></p>
<?

//no link to limesurvey
$sql = "SELECT q.lime_sid, q.description
	FROM questionnaire as q, operator_questionnaire as oq
	WHERE oq.operator_id = '$operator_id'
	AND q.questionnaire_id = oq.questionnaire_id";

$rs = $db->GetAll($sql);

if (!empty($rs))
{
	foreach($rs as $r)
	{
		$sql = "SELECT count(*)
			FROM " . LIME_PREFIX ."tokens_{$r['lime_sid']}";
		$rs2 = $ldb->GetRow($sql);

		if (empty($rs2))
			print "<p class='error'>" . T_("ERROR: No tokens table defined for LimeSurvey questionnaire") . " {$r['lime_sid']} " . T_("from questionnaire:") . " {$r['description']}</p>";
		else
			print "<p>{$r['description']}: " . T_("Tokens table exists for Limesurvey questionnaire:") . " {$r['lime_sid']}</p>";

	}
}
else
	print "<p class='error'>" . T_("ERROR: Cannot find questionnaires with LimeSurvey ID's") . "</p>";



//quota's full
$sql = "SELECT questionnaire_sample_quota_id,q.questionnaire_id,sample_import_id,lime_sgqa,value,comparison,completions,quota_reached,q.lime_sid
	FROM questionnaire_sample_quota as qsq, questionnaire as q, operator_questionnaire as oq
	WHERE oq.operator_id = '$operator_id'
	AND qsq.questionnaire_id = oq.questionnaire_id
	AND q.questionnaire_id = oq.questionnaire_id";
	
$rs = $db->GetAll($sql);

if (isset($rs) && !empty($rs))
{
	foreach($rs as $r)
	{
		if ($r['quota_reached'] == 1)
		{
			print "<p class='error'>" . T_("ERROR: Quota reached for this question") . " - " . $r['lime_sgqa'];
		}
	}
}

//quota row's full
$sql = "SELECT questionnaire_sample_quota_row_id,q.questionnaire_id,sample_import_id,lime_sgqa,value,comparison,completions,quota_reached,q.lime_sid
	FROM questionnaire_sample_quota_row as qsq, questionnaire as q, operator_questionnaire as oq
	WHERE oq.operator_id = '$operator_id'
	AND qsq.questionnaire_id = oq.questionnaire_id
	AND q.questionnaire_id = oq.questionnaire_id";
	
$rs = $db->GetAll($sql);

if (isset($rs) && !empty($rs))
{
	foreach($rs as $r)
	{
		if ($r['quota_reached'] == 1)
		{
			print "<p class='error'>" . T_("POSSIBLE ERROR: Row quota reached for this question") . " - " . $r['lime_sgqa'];
		}
	}
}



//no tokens table associated with questionnaire in limesurvey



//no sample associated with questionnaire




xhtml_foot();


?>
