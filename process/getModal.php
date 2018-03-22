<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
 * 
 * 모달창을 가져온다.
 *
 * @file /modules/calendar/process/getModal.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 18.
 */
if (defined('__IM__') == false) exit;

$modal = Request('modal');

if ($modal == 'add') {
	$cid = Request('cid');
	
	if ($this->checkPermission($cid,'add') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$start = strtotime(Request('start'));
	$end = strtotime(Request('end'));
	
	$results->success = true;
	$results->modalHtml = $this->getAddModal($cid,$start,$end);
}

if ($modal == 'view') {
	$idx = Request('idx');
	$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
	if ($schedule == null) {
		$results->success = false;
		$results->message = $this->getErrorText('NOT_FOUND');
		return;
	}
	
	if ($schedule->midx != $this->IM->getModule('member')->getLogged() && $this->checkPermission($schedule->cid,'view') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$results->success = true;
	$results->modalHtml = $this->getViewModal($idx);
}

if ($modal == 'modify') {
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
	
	$results->success = true;
	$results->modalHtml = $this->getModifyModal($idx);
}

if ($modal == 'delete') {
	$idx = Request('idx');
	$schedule = $this->db()->select($this->table->schedule)->where('idx',$idx)->getOne();
	if ($schedule == null) {
		$results->success = false;
		$results->message = $this->getErrorText('NOT_FOUND');
		return;
	}
	
	if ($schedule->midx != $this->IM->getModule('member')->getLogged() && $this->checkPermission($schedule->cid,'delete') == false) {
		$results->success = false;
		$results->message = $this->getErrorText('FORBIDDEN');
		return;
	}
	
	$results->success = true;
	$results->modalHtml = $this->getDeleteModal($idx);
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