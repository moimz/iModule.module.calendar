<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 관리자 정보를 가져온다.
 * 
 * @file /modules/calendar/process/@getAdmin.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 12. 11.
 */
if (defined('__IM__') == false) exit;

if ($this->isAdmin() !== true) {
	$results->success = false;
	$results->message = $this->getErrorText('FORBIDDEN');
	return;
}

$midx = Request('midx');
$admin = $this->db()->select($this->table->admin)->where('midx',$midx)->getOne();
if ($admin == null) {
	$results->success = false;
	$results->message = $this->getErrorText('NOT_FOUND');
	return;
}

$member = $this->IM->getModule('member')->getMember($midx);

$results->success = true;
$results->member = $member;
$results->cid = $admin->cid == '*' ? '*' : explode(',',$admin->cid);
?>