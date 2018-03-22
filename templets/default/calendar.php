<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
 *
 * 캘린더 기본템플릿 - 캘린더
 * 
 * @file /modules/calendar/templets/default/calendar.php
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2018. 3. 18.
 */
if (defined('__IM__') == false) exit;
?>

<?php echo $context; ?>

<ul class="category">
	<?php foreach ($categories as $category) { ?>
	<li><i style="background:<?php echo $category->color; ?>"></i><?php echo $category->title; ?></i></li>
	<?php } ?>
</ul>

<div class="help">
	<p><i></i>일정을 클릭하여 상세일정내역을 확인할 수 있습니다.</p>
	<?php if ($permission->add == true) { ?>
	<p><i></i>캘린더의 원하는 날짜 또는 원하는 시각의 셀을 마우스 클릭 또는 마우스 드래그하여 일정을 추가할 수 있습니다.</p>
	<?php } ?>
	<?php if ($permission->add == true || $permission->modify == true) { ?>
	<p><i></i><?php echo $permission->modify == false ? '자신이 등록한 일정을' : '등록되어 있는 일정을'; ?> 마우스로 드래그 하여 원하는 날짜 또는 시간으로 일정을 변경할 수 있습니다.</p>
	<?php } ?>
</div>