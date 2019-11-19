<?php
/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 캘린더모듈 관리자패널을 구성한다.
 * 
 * @file /modules/calendar/admin/index.php
 * @author Arzz (arzz@arzz.com)
 * @license GPLv3
 * @version 3.0.0
 * @modified 2019. 11. 15.
 */
if (defined('__IM__') == false) exit;
?>
<script>
Ext.onReady(function () { Ext.getCmp("iModuleAdminPanel").add(
	new Ext.TabPanel({
		id:"ModuleCalendar",
		border:false,
		tabPosition:"bottom",
		items:[
			new Ext.grid.Panel({
				id:"ModuleCalendarList",
				iconCls:"mi mi-calendar",
				title:Calendar.getText("admin/list/title"),
				border:false,
				tbar:[
					new Ext.Button({
						text:Calendar.getText("admin/list/add_calendar"),
						iconCls:"mi mi-plus",
						handler:function() {
							Calendar.list.add();
						}
					}),
					new Ext.Button({
						text:Calendar.getText("admin/list/delete_calendar"),
						iconCls:"mi mi-trash",
						handler:function() {
							Calendar.list.delete();
						}
					})
				],
				store:new Ext.data.JsonStore({
					proxy:{
						type:"ajax",
						simpleSortMode:true,
						url:ENV.getProcessUrl("calendar","@getCalendars"),
						reader:{type:"json"}
					},
					remoteSort:true,
					sorters:[{property:"cid",direction:"ASC"}],
					autoLoad:true,
					pageSize:50,
					fields:["cid","title"],
					listeners:{
						load:function(store,records,success,e) {
							if (success == false) {
								if (e.getError()) {
									Ext.Msg.show({title:Admin.getText("alert/error"),msg:e.getError(),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
								} else {
									Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getText("error/load"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
								}
							}
						}
					}
				}),
				columns:[{
					text:Calendar.getText("admin/list/columns/cid"),
					width:120,
					sortable:true,
					dataIndex:"cid"
				},{
					text:Calendar.getText("admin/list/columns/title"),
					minWidth:200,
					flex:1,
					sortable:true,
					dataIndex:"title"
				},{
					text:Calendar.getText("admin/list/columns/category"),
					width:80,
					align:"right",
					dataIndex:"category",
					renderer:function(value,p) {
						if (value == 0) {
							p.style = "text-align:center;";
							return "-";
						}
						return Ext.util.Format.number(value,"0,000");
					}
				},{
					text:Calendar.getText("admin/list/columns/event"),
					width:80,
					align:"right",
					dataIndex:"event",
					sortable:true,
					renderer:function(value,p) {
						if (value == 0) {
							p.style = "text-align:center;";
							return "-";
						}
						return Ext.util.Format.number(value,"0,000");
					}
				},{
					text:Calendar.getText("admin/list/columns/latest_update"),
					width:130,
					align:"center",
					dataIndex:"latest_update",
					sortable:true,
					renderer:function(value) {
						return value > 0 ? moment(value * 1000).format("YYYY-MM-DD HH:mm") : "-";
					}
				}],
				selModel:new Ext.selection.CheckboxModel(),
				bbar:new Ext.PagingToolbar({
					store:null,
					displayInfo:false,
					items:[
						"->",
						{xtype:"tbtext",text:"항목 더블클릭 : 캘린더보기 / 항목 우클릭 : 상세메뉴"}
					],
					listeners:{
						beforerender:function(tool) {
							tool.bindStore(Ext.getCmp("ModuleCalendarList").getStore());
						}
					}
				}),
				listeners:{
					itemdblclick:function(grid,record) {
						Calendar.list.view(record.data.cid,record.data.title);
					},
					itemcontextmenu:function(grid,record,item,index,e) {
						var menu = new Ext.menu.Menu();
						
						menu.addTitle(record.data.title);
						
						menu.add({
							iconCls:"mi mi-calendar",
							text:"캘린더 수정",
							handler:function() {
								Calendar.list.add(record.data.cid);
							}
						});
						
						menu.add({
							iconCls:"xi xi-sitemap",
							text:"카테고리 관리",
							handler:function() {
								Calendar.category.list(record.data.cid);
							}
						});
						
						menu.add({
							iconCls:"mi mi-trash",
							text:"캘린더 삭제",
							handler:function() {
								Calendar.list.delete();
							}
						});
						
						e.stopEvent();
						menu.showAt(e.getXY());
					}
				}
			})
		]
	})
); });
</script>