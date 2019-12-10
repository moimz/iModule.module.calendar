<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 일정을 저장한다.
 *
 * @file /modules/calendar/process/saveEvent.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 12. 10.
 */
if (defined('__IM__') == false) exit;

$errors = array();
$uid = Request('uid');
$rid = Request('rid');
$cid = Param('cid');
$category = Param('category');

if ($this->db()->select($this->table->category)->where('cid',$cid)->where('idx',$category)->has() == false) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

if ($uid) {
	$event = $this->getEvent($uid,$rid);
	if ($event == null) {
		$results->success = false;
		$results->message = $this->getErrorText('NOT_FOUND');
		return;
	}
	
	if ($this->checkPermission($event->cid,$event->category,'edit') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
} else {
	if ($this->checkPermission($cid,$category,'write') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
}

$summary = Param('summary') ? Request('summary') : $errors['summary'] = $this->getErrorText('REQUIRED');
$is_allday = Request('is_allday') == 'TRUE';
$start_date = Request('start_date') && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',Request('start_date')) == true ? Request('start_date') : $errors['start_date'] = $this->getErrorText('REQUIRED');
$end_date = Request('end_date') && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',Request('end_date')) == true ? Request('end_date') : $errors['end_date'] = $this->getErrorText('REQUIRED');

if ($is_allday == true) {
	$start_time = strtotime($start_date);
	$end_time = strtotime($end_date.' 24:00:00');
} else {
	$start_time = Request('start_time') && preg_match('/^[0-9]{2}:[0-9]{2}$/',Request('start_time')) == true ? Request('start_time') : $errors['start_time'] = $this->getErrorText('REQUIRED');
	$end_time = Request('end_time') && preg_match('/^[0-9]{2}:[0-9]{2}$/',Request('end_time')) == true ? Request('end_time') : $errors['end_time'] = $this->getErrorText('REQUIRED');
	
	if (count($errors) == 0) {
		$start_time = strtotime($start_date.' '.$start_time.':00');
		$end_time = strtotime($end_date.' '.$end_time.':00');
	}
}

$location = Request('location') ? Request('location') : '';
$description = Request('description') ? Request('description') : '';
$url = Request('url') ? Request('url') : '';

$repeat = Request('repeat');
if ($repeat == 'NONE') {
	$recurrence = '';
} else {
	$recurrence = '';
}

if (count($errors) == 0) {
	$insert = array();
	$insert['cid'] = $cid;
	$insert['category'] = $category;
	$insert['midx'] = $this->IM->getModule('member')->getLogged();
	$insert['summary'] = $summary;
	$insert['start_time'] = $start_time;
	$insert['end_time'] = $end_time;
	$insert['is_allday'] = $is_allday == true ? 'TRUE' : 'FALSE';
	$insert['recurrence'] = $recurrence;
	$insert['description'] = $description;
	$insert['location'] = $location;
	$insert['url'] = $url;
	$insert['latest_update'] = time();
	
	if ($uid) {
		$this->db()->update($this->table->event,$insert)->where('uid',$uid)->where('rid',$rid)->execute();
		$results->success = true;
	} else {
		$this->db()->setLockMethod('WRITE')->lock($this->table->event);
		$uid = UUID::v4();
		while (true) {
			if ($this->db()->select($this->table->event)->where('uid',$uid)->where('rid','')->has() == false) break;
			$uid = UUID::v4();
		}
		$this->db()->unlock();
		
		$insert['uid'] = $uid;
		$insert['rid'] = '';
		$insert['reg_date'] = time();
		$insert['sequence'] = 0;
		
		$this->db()->insert($this->table->event,$insert)->execute();
		$results->success = true;
	}
	
	$this->updateCategory($category);
	$this->updateCalendar($cid);
} else {
	$results->success = false;
	$results->errors = $errors;
}
?>