<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 일정을 삭제한다.
 *
 * @file /modules/calendar/process/deleteEvent.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 3. 20.
 */
if (defined('__IM__') == false) exit;

$uid = Param('uid');
$rid = Param('rid');
$event = $this->getEvent($uid,$rid);
if ($event == null) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

if ($event->midx != $this->IM->getModule('member')->getLogged() && $this->checkPermission($event->cid,$event->category,'edit') == false) {
	$results->success = false;
	$results->message = $this->getErrorText('FORBIDDEN');
	return;
}

if ($event->recurrence) {
	
} else {
	$this->db()->delete($this->table->event)->where('uid',$uid)->where('rid',$rid)->execute();
}

$this->updateCategory($event->category);
$this->updateCalendar($event->cid);
$results->success = true;
?>