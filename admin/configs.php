<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodule.kr)
 *
 * 캘린더모듈 설정패널
 * 
 * @file /modules/calendar/admin/configs.php
 * @author Arzz (arzz@arzz.com)
 * @license GPLv3
 * @version 3.0.0
 * @modified 2018. 3. 18.
 */
if (defined('__IM__') == false) exit;
?>
<script>
var config = new Ext.form.Panel({
	id:"ModuleConfigForm",
	border:false,
	bodyPadding:10,
	width:600,
	fieldDefaults:{labelAlign:"right",labelWidth:100,anchor:"100%",allowBlank:true},
	items:[
		new Ext.form.FieldSet({
			title:Calendar.getText("admin/configs/form/default_setting"),
			items:[
				Admin.templetField(Calendar.getText("admin/configs/form/templet"),"templet","calendar",false)
			]
		})
	]
});
</script>