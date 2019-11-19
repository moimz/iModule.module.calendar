<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 캘린더를 삭제한다.
 *
 * @file /modules/calendar/process/@deleteCalendar.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 19.
 */
if (defined('__IM__') == false) exit;

$cids = Request('cid') ? explode(',',Request('cid')) : array();
foreach ($cids as $cid) {
	$this->db()->delete($this->table->category)->where('cid',$cid)->execute();
	$this->db()->delete($this->table->event)->where('cid',$cid)->execute();
	$this->db()->delete($this->table->calendar)->where('cid',$cid)->execute();
}

$results->success = true;
?>