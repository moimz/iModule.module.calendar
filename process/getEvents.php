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
	
	if ($category->ical) {
		$iCal = $this->getICal($category->ical);
		foreach ($iCal->getEvents($start,$end) as $data) {
			$event = new stdClass();
			$event->id = $data->uid;
			$event->title = $data->summary;
			$event->start = date('c',strtotime($data->dtstart));
			$event->end = date('c',strtotime($data->dtend));
			$event->allDay = strlen($data->dtstart) == 8 && strlen($data->dtend);
			$event->color = $category->color;
			$event->is_recurrence = isset($data->recurrence_id) == true;
			$event->editable = false;
			$event->origin = $data;
			$events[] = $event;
		}
	}
}
/*
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
*/
$results->success = true;
$results->events = $events;
?>