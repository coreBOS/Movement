<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';

class Movement extends CRMEntity {
	public $table_name = 'vtiger_movement';
	public $table_index= 'movementid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	public $moduleIcon = array('library' => 'standard', 'containerClass' => 'slds-icon_container slds-icon-standard-account', 'class' => 'slds-icon', 'icon'=>'account');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_movementcf', 'movementid');
	// Uncomment the line below to support custom field columns on related lists
	// public $related_tables = array('vtiger_movementcf'=>array('movementid','vtiger_movement', 'movementid'));

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_movement', 'vtiger_movementcf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_movement' => 'movementid',
		'vtiger_movementcf' => 'movementid');

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Moveno'=> array('movement' => 'moveno'),
		'SrcWarehouse'=> array('movement' => 'srcwhid'),
		'DstWarehouse'=> array('movement' => 'dstwhid'),
		'Producto'=> array('movement' => 'pdoid'),
		'Units'=> array('movement' => 'unitsmvto'),
		'Assigned To' => array('crmentity' =>'smownerid')
	);
	public $list_fields_name = array(
		/* Format: Field Label => fieldname */
		'Moveno'=> 'moveno',
		'SrcWarehouse'=> 'srcwhid',
		'DstWarehouse'=> 'dstwhid',
		'Producto'=> 'pdoid',
		'Units'=> 'unitsmvto',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'moveno';

	// For Popup listview and UI type support
	public $search_fields = array(
		/* Format: Field Label => array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Moveno'=> array('movement' => 'moveno'),
		'SrcWarehouse'=> array('movement' => 'srcwhid'),
		'DstWarehouse'=> array('movement' => 'dstwhid'),
		'Producto'=> array('movement' => 'pdoid'),
		'Units'=> array('movement' => 'unitsmvto'),
	);
	public $search_fields_name = array(
		/* Format: Field Label => fieldname */
		'Moveno'=> 'moveno',
		'SrcWarehouse'=> 'srcwhid',
		'DstWarehouse'=> 'dstwhid',
		'Producto'=> 'pdoid',
		'Units'=> 'unitsmvto',
	);

	// For Popup window record selection
	public $popup_fields = array('moveno');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'moveno';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'moveno';

	// Required Information for enabling Import feature
	public $required_fields = array('moveno'=>1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'moveno';
	public $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'moveno');

	public function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
	}

	function trash($module, $id) {
		$ms=new Movement();
		$ms->retrieve_entity_info($id, $module);
		$ms->column_fields['unitsmvto']=-1*$ms->column_fields['unitsmvto'];
		$ms->executeMovement();
		parent::trash($module, $id);
	}

	function executeMovement() {
		global $adb, $log, $current_user;
		include_once 'modules/Stock/Stock.php';
		$uds=$this->column_fields['unitsmvto'];
		$pdo=$this->column_fields['pdoid'];
		$src=$this->column_fields['srcwhid'];
		$dst=$this->column_fields['dstwhid'];
		$orgcf=$this->column_fields;
		$sqlstock = 'select count(*) 
			from vtiger_stock
			inner join vtiger_crmentity on crmid = stockid
			where deleted=0 and whid='.$this->column_fields['srcwhid'].' and pdoid='.$this->column_fields['pdoid'];
		$cntrs=$adb->query($sqlstock);
		$cnt=$adb->query_result($cntrs,0,0);
		if ($cnt==0) { // no stock for this product in that warehouse > we create one
			$stk=new Stock();
			unset($stk->id);
			$stk->mode='';
			$stk->column_fields['assigned_user_id']=$current_user->id;
			$stk->save('Stock');
			$adb->query("update vtiger_stock set whid=$src,pdoid=$pdo,stocknum=0 where stockid=".$stk->id);
		}
		$sqlstock = 'select count(*)
			from vtiger_stock
			inner join vtiger_crmentity on crmid = stockid
			where deleted=0 and whid='.$this->column_fields['dstwhid'].' and pdoid='.$this->column_fields['pdoid'];
		$cntrs=$adb->query($sqlstock);
		$cnt=$adb->query_result($cntrs,0,0);
		if ($cnt==0) { // no stock for this product in that warehouse > we create one
			$stk=new Stock();
			unset($stk->id);
			$stk->mode='';
			$stk->column_fields['assigned_user_id']=$current_user->id;
			$stk->save('Stock');
			$adb->query("update vtiger_stock set whid=$dst,pdoid=$pdo,stocknum=0 where stockid=".$stk->id);
		}
		$adb->query("update vtiger_stock set stocknum=stocknum+$uds where whid=$dst and pdoid=$pdo");
		$adb->query("update vtiger_stock set stocknum=stocknum-$uds where whid=$src and pdoid=$pdo");
		$adb->query("update vtiger_products set qtyinstock=
			(select sum(stocknum)
			from vtiger_stock
			inner join vtiger_crmentity on crmid = stockid
			where deleted=0 and pdoid=$pdo
			  and whid not in (select warehouseid from vtiger_warehouse where warehno='Sale' or warehno='Purchase')
			) where productid=$pdo");
		$this->column_fields=$orgcf;
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param string Module name
	 * @param string Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		if ($event_type == 'module.postinstall') {
			// Handle post installation actions
			// Related lists
			$this->setModuleSeqNumber('configure', $modulename, 'MV-', '0000001');
			$module = Vtiger_Module::getInstance($modulename);
			$mod = Vtiger_Module::getInstance('Products');
			$mod->setRelatedList($module, 'Movement', Array('ADD'),'get_dependents_list');
			$mod = Vtiger_Module::getInstance('Warehouse');
			$mod->setRelatedList($module, 'Movement', Array('ADD'),'get_dependents_list');
			// Workflow functions for inc/dec stock
			global $adb;
			$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
			$adb->query("insert into com_vtiger_workflowtasks_entitymethod
             (workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
             values
             ($wfid,'PurchaseOrder','mwIncrementStock','modules/Movement/InventoryIncDec.php','mwIncrementStock')");
			$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
			$adb->query("insert into com_vtiger_workflowtasks_entitymethod
             (workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
             values
             ($wfid,'Invoice','mwDecrementStock','modules/Movement/InventoryIncDec.php','mwDecrementStock')");
			$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
			$adb->query("insert into com_vtiger_workflowtasks_entitymethod
             (workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
             values
             ($wfid,'SalesOrder','mwDecrementStock','modules/Movement/InventoryIncDec.php','mwDecrementStock')");
		$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
		$adb->query("insert into com_vtiger_workflowtasks_entitymethod
			     (workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
			     values
			     ($wfid,'PurchaseOrder','mwReturnStock','modules/Movement/InventoryIncDec.php','mwReturnStock')");
		
		$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
		$adb->query("insert into com_vtiger_workflowtasks_entitymethod
			     (workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
			     values
			     ($wfid,'Invoice','mwReturnStock','modules/Movement/InventoryIncDec.php','mwReturnStock')");
		
		$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
		$adb->query("insert into com_vtiger_workflowtasks_entitymethod
			     (workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
			     values
			     ($wfid,'SalesOrder','mwReturnStock','modules/Movement/InventoryIncDec.php','mwReturnStock')");			
		} elseif ($event_type == 'module.disabled') {
			// Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// Handle actions after this module is updated.
		}
	}
}
?>
