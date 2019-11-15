<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 카테고리목록을 가져온다.
 *
 * @file /modules/calendar/process/@getCategories.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 15.
 */
if (defined('__IM__') == false) exit;

$cid = Param('cid');
$lists = $this->db()->select($this->table->category)->where('cid',$cid)->orderBy('sort','asc')->get();

for ($i=0, $loop=count($lists);$i<$loop;$i++) {

}

$results->success = true;
$results->lists = $lists;
$results->total = count($lists);
?>