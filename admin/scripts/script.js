/**
 * 이 파일은 iModule 캘린더모듈의 일부입니다. (https://www.imodules.io)
 *
 * 캘린더모듈 관리자 UI를 제어한다.
 * 
 * @file /modules/calendar/admin/scripts/script.js
 * @author Arzz (arzz@arzz.com)
 * @license MIT License
 * @version 3.0.0
 * @modified 2019. 11. 15.
 */
var Calendar = {
	
	category:{
		list:function(cid) {
			new Ext.Window({
				id:"ModuleCalendarCategoryWindow",
				title:"카테고리 관리",
				modal:true,
				width:750,
				height:500,
				border:false,
				autoScroll:true,
				layout:"fit",
				items:[
					new Ext.grid.Panel({
						id:"ModuleCalendarCategoryList",
						border:true,
						tbar:[
							new Ext.Button({
								iconCls:"mi mi-plus",
								text:"카테고리추가",
								handler:function() {
									Calendar.category.add(cid);
								}
							}),
							new Ext.Button({
								iconCls:"mi mi-trash",
								text:"선택 카테고리 삭제",
								handler:function() {
									Calendar.category.delete();
								}
							})
						],
						store:new Ext.data.JsonStore({
							proxy:{
								type:"ajax",
								simpleSortMode:true,
								url:ENV.getProcessUrl("calendar","@getCategories"),
								extraParams:{cid:cid},
								reader:{type:"json"}
							},
							remoteSort:true,
							sorters:[{property:"sort",direction:"ASC"}],
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
							text:"카테고리명",
							dataIndex:"title",
							flex:1,
							renderer:function(value,p,record) {
								return '<div style="display:inline-block; vertical-align:middle; margin-right:5px; width:16px; height:16px; border:1px solid #ccc; background:'+record.data.color+';"></div>'+value;
							}
						},{
							text:"카테고리속성",
							dataIndex:"detail",
							width:250
						}],
						bbar:[
							new Ext.Button({
								iconCls:"fa fa-caret-up",
								handler:function() {
									Admin.gridSort(Ext.getCmp("ModuleCalendarCategoryList"),"sort","up");
								}
							}),
							new Ext.Button({
								iconCls:"fa fa-caret-down",
								handler:function() {
									Admin.gridSort(Ext.getCmp("ModuleCalendarCategoryList"),"sort","down");
								}
							}),
							"->",
							{xtype:"tbtext",text:"더블클릭 : 카테고리수정 / 마우스우클릭 : 상세메뉴"}
						],
						selModel:new Ext.selection.CheckboxModel(),
						listeners:{
							itemdblclick:function(grid,record,td,index) {
								Calendar.category.add(cid,record.data.idx);
							},
							itemcontextmenu:function(grid,record,item,index,e) {
								var menu = new Ext.menu.Menu();
								
								menu.addTitle(record.data.title);
								
								menu.add({
									iconCls:"xi xi-form",
									text:"카테고리 수정",
									handler:function() {
										Calendar.category.add(cid,record.data.idx);
									}
								});
								
								menu.add({
									iconCls:"mi mi-trash",
									text:"카테고리 삭제",
									handler:function() {
										Calendar.category.delete();
									}
								});
								
								e.stopEvent();
								menu.showAt(e.getXY());
							}
						}
					})
				]
			}).show();
		},
		add:function(cid,idx) {
			new Ext.Window({
				id:"ModuleCalendarAddCategoryWindow",
				title:(idx == null ? "카테고리추가" : "카테고리수정"),
				modal:true,
				width:600,
				border:false,
				autoScroll:true,
				items:[
					new Ext.form.Panel({
						id:"ModuleCalendarAddCategoryForm",
						border:false,
						bodyPadding:"10 10 0 10",
						fieldDefaults:{labelAlign:"right",labelWidth:100,anchor:"100%",allowBlank:false},
						items:[
							new Ext.form.Hidden({
								name:"cid",
								value:cid
							}),
							new Ext.form.Hidden({
								name:"idx",
								value:idx ? idx : ""
							}),
							new Ext.form.FieldContainer({
								layout:"hbox",
								items:[
									new Ext.form.TextField({
										name:"title",
										flex:1,
										emptyText:"카테고리명",
										style:{marginRight:"5px"}
									}),
									new Ext.ux.ColorField({
										name:"color",
										emptyText:"구분색상",
										maxLength:7,
										width:110,
										preview:false,
										style:{marginRight:"5px"},
										validator:function(value) {
											return value.search(/^#[0-9a-fA-F]{6}/) === 0;
										},
										listeners:{
											change:function(form,value) {
												if (form.isValid() == true) {
													var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(value);
													$("#ModuleCalendarAddCategoryColor-inputEl").css("backgroundColor","rgba("+parseInt(result[1],16)+","+parseInt(result[2],16)+","+parseInt(result[3],16)+",0.2)");
													$("#ModuleCalendarAddCategoryColor-inputEl").css("color",value);
												}
											}
										}
									}),
									new Ext.form.DisplayField({
										id:"ModuleCalendarAddCategoryColor",
										fieldStyle:{border:"1px solid #ccc",textAlign:"center"},
										width:30,
										value:"T"
									})
								],
								afterBodyEl:'<div class="x-form-help">일정을 구분할 고유한 색상값(예 : #000000)과 대상명을 입력하여 주십시오.</div>'
							}),
							new Ext.form.FieldSet({
								title:"외부 캘린더 연동 (일정을 직접입력하지 않고 외부 공유캘린더의 일정을 연동합니다.)",
								checkboxName:"use_ical",
								checkboxToggle:true,
								collapsed:true,
								items:[
									new Ext.form.TextField({
										name:"ical",
										margin:"0 0 0 0",
										emptyText:"iCal 주소를 입력하여 주십시오.",
										afterBodyEl:'<div class="x-form-help">http(s):// 를 포함한 전체 iCal 경로를 입력하여 주십시오.</div>'
									})
								],
								listeners:{
									expand:function(form,value) {
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_write").disable();
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_edit").disable();
									},
									collapse:function() {
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_write").enable();
										Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().findField("permission_edit").enable();
									}
								}
							}),
							new Ext.form.FieldSet({
								title:"카테고리 권한설정",
								items:[
									Admin.permissionField("일정보기","permission_view","true"),
									Admin.permissionField("일정작성","permission_write","true"),
									Admin.permissionField("일정수정","permission_edit","{$member.type} == 'ADMINISTRATOR'")
								]
							})
						]
					})
				],
				buttons:[
					new Ext.Button({
						text:"확인",
						handler:function() {
							Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().submit({
								url:ENV.getProcessUrl("calendar","@saveCategory"),
								submitEmptyText:false,
								waitTitle:Admin.getText("action/wait"),
								waitMsg:Admin.getText("action/saving"),
								success:function(form,action) {
									Ext.Msg.show({title:Admin.getText("alert/info"),msg:Admin.getText("action/saved"),buttons:Ext.Msg.OK,icon:Ext.Msg.INFO,fn:function(button) {
										Ext.getCmp("ModuleCalendarAddCategoryWindow").close();
										Ext.getCmp("ModuleCalendarCategoryList").getStore().reload();
										Ext.getCmp("ModuleCalendarList").getStore().reload();
									}});
								},
								failure:function(form,action) {
									if (action.result) {
										if (action.result.message) {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:action.result.message,buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										} else {
											Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("DATA_SAVE_FAILED"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
										}
									} else {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getErrorText("INVALID_FORM_DATA"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									}
								}
							});
						}
					}),
					new Ext.Button({
						text:"취소",
						handler:function() {
							Ext.getCmp("ModuleCalendarAddCategoryWindow").close();
						}
					})
				],
				listeners:{
					show:function() {
						if (idx) {
							Ext.getCmp("ModuleCalendarAddCategoryForm").getForm().load({
								url:ENV.getProcessUrl("calendar","@getCategory"),
								params:{idx:idx},
								waitTitle:Admin.getText("action/wait"),
								waitMsg:Admin.getText("action/loading"),
								success:function(form,action) {
									
								},
								failure:function(form,action) {
									if (action.result && action.result.message) {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:action.result.message,buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									} else {
										Ext.Msg.show({title:Admin.getText("alert/error"),msg:Admin.getText("error/load"),buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
									}
									Ext.getCmp("ModuleCalendarAddCategoryWindow").close();
								}
							});
						}
					}
				}
			}).show();
		},
		delete:function() {
			var selected = Ext.getCmp("ModuleCalendarCategoryList").getSelectionModel().getSelection();
			if (selected.length == 0) {
				Ext.Msg.show({title:Admin.getText("alert/error"),msg:"삭제할 카테고리를 선택하여 주십시오.",buttons:Ext.Msg.OK,icon:Ext.Msg.ERROR});
				return;
			}
			
			Ext.Msg.show({title:Admin.getText("alert/info"),msg:"선택하신 카테고리를 삭제하시겠습니까?<br>삭제되는 카테고리의 게시물이 기본 카테고리로 이동됩니다.",buttons:Ext.Msg.OKCANCEL,icon:Ext.Msg.QUESTION,fn:function(button) {
				if (button == "ok") {
					
				}
			}});
		}
	}
};