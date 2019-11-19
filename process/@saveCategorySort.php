<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 카테고리 순서를 저장한다.
 * 
 * @file /modules/calendar/process/@saveCategorySort.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 19.
 */
if (defined('__IM__') == false) exit;

$updated = json_decode(Param('updated'));
for ($i=0, $loop=count($updated);$i<$loop;$i++) {
	$this->db()->update($this->table->category,array('sort'=>$updated[$i]->sort))->where('idx',$updated[$i]->idx)->execute();
}

$results->success = true;
?>