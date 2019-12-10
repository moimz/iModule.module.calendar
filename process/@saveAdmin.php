<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 캘린더 관리자를 저장한다.
 *
 * @file /modules/calendar/process/@saveAdmin.php
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

$midx = Param('midx');
$cid = Param('cid');

$this->db()->replace($this->table->admin,array('cid'=>$cid,'midx'=>$midx))->execute();
$results->success = true;
?>