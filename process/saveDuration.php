<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 일정의 시작 및 종료시각을 저장한다.
 *
 * @file /modules/calendar/process/saveDuration.php
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

$start_time = preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',Request('start')) == true ? strtotime(Request('start')) : null;
$end_time = preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',Request('end')) == true ? strtotime(Request('end')) : null;

if ($start_time == null || $end_time == null) {
	$results->success = false;
	$results->message = '날짜 또는 시간이 잘못 입력되었습니다.';
	return;
}

if ($event->recurrence) {
	
} else {
	$update = array();
	$update['start_time'] = $start_time;
	$update['end_time'] = $end_time;
	
	$this->db()->update($this->table->event,$update)->where('uid',$uid)->where('rid',$rid)->execute();
	$results->success = true;
}

$results->success = true;
?>