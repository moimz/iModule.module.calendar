<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 카테고리를 삭제한다.
 *
 * @file /modules/calendar/process/@deleteCategory.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 19.
 */
if (defined('__IM__') == false) exit;

$idxes = Request('idx') ? explode(',',Request('idx')) : array();
foreach ($idxes as $idx) {
	$this->db()->delete($this->table->category)->where('idx',$idx)->execute();
	$this->db()->delete($this->table->event)->where('category',$idx)->execute();
}

$results->success = true;