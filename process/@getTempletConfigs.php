<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 캘린더 템플릿의 환경설정폼을 가져온다.
 *
 * @file /modules/calendar/process/@getTempletConfigs.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 19.
 */
if (defined('__IM__') == false) exit;

$cid = Param('cid');
$name = Param('name');
$templet = Param('templet');

if ($name == 'templet') {
	$Templet = $this->getModule()->getTemplet($templet);
	$calendar = $this->getCalendar($cid);
	
	if ($calendar !== null && $calendar->templet == $templet) $Templet->setConfigs($calendar->templet_configs);
	$configs = $Templet->getConfigs();
}

$results->success = true;
$results->configs = $configs;
?>