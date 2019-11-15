<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 * 
 * 캘린더목록을 가져온다.
 *
 * @file /modules/calendar/process/@getCalendars.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 15.
 */
if (defined('__IM__') == false) exit;

$start = Request('start');
$limit = Request('limit');
$lists = $this->db()->select($this->table->calendar);
$total = $lists->copy()->count();
$sort = Request('sort') ? Request('sort') : 'title';
$dir = Request('dir') ? Request('dir') : 'asc';
if ($limit > 0) $lists->limit($start,$limit);
$lists = $lists->orderBy($sort,$dir)->get();

for ($i=0, $loop=count($lists);$i<$loop;$i++) {
	$lists[$i]->category = $this->db()->select($this->table->category)->where('cid',$lists[$i]->cid)->count();
}

$results->success = true;
$results->lists = $lists;
$results->total = $total;
?>