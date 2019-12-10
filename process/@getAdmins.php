<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 관리자목록을 가져온다.
 *
 * @file /modules/calendar/process/@getAdmins.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 12. 11.
 */
if (defined('__IM__') == false) exit;

$lists = $this->db()->select($this->table->admin)->get();
for ($i=0, $loop=count($lists);$i<$loop;$i++) {
	$member = $this->IM->getModule('member')->getMember($lists[$i]->midx);
	
	$lists[$i]->name = $member->name;
	$lists[$i]->email = $member->email;

	if ($lists[$i]->cid == '*') {
		$lists[$i]->cid = $this->getText('admin/admin/admin_all');
	} else {
		$cids = explode(',',$lists[$i]->cid);
		foreach ($cids as &$cid) {
			$board = $this->getBoard($cid);
			$cid = $board == null ? 'Unknown('.$cid.')' : $board->title.'('.$cid.')';
		}
		$lists[$i]->cid = implode(', ',$cids);
	}
}

$results->success = true;
$results->lists = $lists;
$results->total = count($lists);
?>