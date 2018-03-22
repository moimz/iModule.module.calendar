<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
 * 
 * 일정을 가져온다.
 *
 * @file /modules/calendar/process/getSchedules.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 20.
 */
if (defined('__IM__') == false) exit;

$cid = Request('cid');
$start = strtotime(Request('start'));
$end = strtotime(Request('end'));

if ($this->checkPermission($cid,'view') === false) {
	$results->success = false;
	return;
}

$events = $this->db()->select($this->table->schedule.' s','s.*, c.color')->join($this->table->category.' c','c.idx=s.category','LEFT')->where('s.cid',$cid)->where('s.start_time',$end,'<=')->where('s.end_time',$start,'>=')->get();
for ($i=0, $loop=count($events);$i<$loop;$i++) {
	$event = new stdClass();
	$event->id = $events[$i]->idx;
	$event->title = $events[$i]->title;
	$event->start = date('c',$events[$i]->start_time);
	$event->end = date('c',$events[$i]->end_time);
	$event->allDay = $events[$i]->is_allday == 'TRUE';
	$event->color = $events[$i]->color;
	$event->is_repeat = $events[$i]->repeat != 'NONE';
	$event->is_module = true;
	
	$events[$i] = $event;
}

$results->success = true;
$results->events = $events;
?>