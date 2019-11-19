<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 모달창을 가져온다.
 *
 * @file /modules/calendar/process/getModal.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 3. 19.
 */
if (defined('__IM__') == false) exit;

$modal = Param('modal');
if ($modal == 'write') {
	$cid = Param('cid');
	
	if ($this->checkPermission($cid,0,'write') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$start = strtotime(Request('start'));
	$end = strtotime(Request('end'));
	
	$results->success = true;
	$results->modalHtml = $this->getEventWriteModal($cid,$start,$end);
}

if ($modal == 'view') {
	$event = json_decode(Param('event'));
	if ($event == null) {
		$results->success = false;
		$results->error = $this->getErrorText('NOT_FOUND');
		return;
	}
	
	if ($this->checkPermission($event->cid,$event->category,'view') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$results->success = true;
	$results->modalHtml = $this->getEventViewModal($event);
}

if ($modal == 'share') {
	$cid = Param('cid');
	if ($this->checkPermission($cid,0,'view') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$results->success = true;
	$results->modalHtml = $this->getShareModal($cid);
}

if ($modal == 'edit') {
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
	
	$results->success = true;
	$results->modalHtml = $this->getEventEditModal($event);
}

if ($modal == 'delete') {
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
	
	$results->success = true;
	$results->modalHtml = $this->getEventDeleteModal($event);
}

if ($modal == 'duration') {
	$idx = Request('idx');
	$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
	if ($schedule == null) {
		$results->success = false;
		$results->message = $this->getErrorText('NOT_FOUND');
		return;
	}
	
	if ($schedule->midx != $this->IM->getModule('member')->getLogged() && $this->checkPermission($schedule->cid,'modify') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$start = preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',Request('start')) == true ? strtotime(Request('start')) : null;
	$end = preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/',Request('end')) == true ? strtotime(Request('end')) : null;
	
	$results->success = true;
	$results->modalHtml = $this->getDurationConfirmModal($idx,$start,$end);
}
?>