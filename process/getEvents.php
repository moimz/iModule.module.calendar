<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 일정을 가져온다.
 *
 * @file /modules/calendar/process/getEvents.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 3. 17.
 */
if (defined('__IM__') == false) exit;

$cid = Param('cid');
$start = strtotime(Param('start'));
$end = strtotime(Param('end'));

$events = array();
$categories = $this->db()->select($this->table->category)->where('cid',$cid)->get();
foreach ($categories as $category) {
	if ($this->checkPermission($cid,$category->idx,'view') == false) continue;
	
	$events = array_merge($events,$this->getEvents($cid,$category->idx,$start,$end));
}

$results->success = true;
$results->events = $events;
?>