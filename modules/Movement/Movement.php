<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once('data/CRMEntity.php');
require_once('data/Tracker.php');

class Movement extends CRMEntity {
	var $db, $log; // Used in class functions of CRMEntity

	var $table_name = 'vtiger_movement';
	var $table_index= 'movementid';
	var $column_fields = Array();

	/** Indicator if this is a custom module or standard module */
	var $IsCustomModule = true;
	var $HasDirectImageField = false;
	/**
	 * Mandatory table for supporting custom fields.
	 */
	var $customFieldTable = Array('vtiger_movementcf', 'movementid');
	// Uncomment the line below to support custom field columns on related lists
	// var $related_tables = Array('vtiger_movementcf'=>array('movementid','vtiger_movement', 'movementid'));

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	var $tab_name = Array('vtiger_crmentity', 'vtiger_movement', 'vtiger_movementcf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_movement' => 'movementid',
		'vtiger_movementcf' => 'movementid');

	/**
	 * Mandatory for Listing (Related listview)
	 */
	var $list_fields = Array (
		/* Format: Field Label => Array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Moveno'=> Array('movement' => 'moveno'),
		'SrcWarehouse'=> Array('movement' => 'srcwhid'),
		'DstWarehouse'=> Array('movement' => 'dstwhid'),
		'Producto'=> Array('movement' => 'pdoid'),
		'Units'=> Array('movement' => 'unitsmvto'),
		'Assigned To' => Array('crmentity' =>'smownerid')
	);
	var $list_fields_name = Array(
		/* Format: Field Label => fieldname */
		'Moveno'=> 'moveno',
		'SrcWarehouse'=> 'srcwhid',
		'DstWarehouse'=> 'dstwhid',
		'Producto'=> 'pdoid',
		'Units'=> 'unitsmvto',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	var $list_link_field = 'moveno';

	// For Popup listview and UI type support
	var $search_fields = Array(
		/* Format: Field Label => Array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Moveno'=> Array('movement' => 'moveno'),
		'SrcWarehouse'=> Array('movement' => 'srcwhid'),
		'DstWarehouse'=> Array('movement' => 'dstwhid'),
		'Producto'=> Array('movement' => 'pdoid'),
		'Units'=> Array('movement' => 'unitsmvto'),
	);
	var $search_fields_name = Array(
		/* Format: Field Label => fieldname */
		'Moveno'=> 'moveno',
		'SrcWarehouse'=> 'srcwhid',
		'DstWarehouse'=> 'dstwhid',
		'Producto'=> 'pdoid',
		'Units'=> 'unitsmvto',
	);

	// For Popup window record selection
	var $popup_fields = Array('moveno');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	var $sortby_fields = Array();

	// For Alphabetical search
	var $def_basicsearch_col = 'moveno';

	// Column value to use on detail view record text display
	var $def_detailview_recname = 'moveno';

	// Required Information for enabling Import feature
	var $required_fields = Array();

	// Callback function list during Importing
	var $special_functions = Array('set_import_assigned_user');

	var $default_order_by = 'moveno';
	var $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	var $mandatory_fields = Array('createdtime', 'modifiedtime');
	
	function __construct() {
		global $log, $currentModule;
		$this->column_fields = getColumnFields($currentModule);
		$this->db = PearDatabase::getInstance();
		$this->log = $log;
		$sql = 'SELECT 1 FROM vtiger_field WHERE uitype=69 and tabid = ?';
		$tabid = getTabid($currentModule);
		$result = $this->db->pquery($sql, array($tabid));
		if ($result and $this->db->num_rows($result)==1) {
			$this->HasDirectImageField = true;
		}
	}

	function getSortOrder() {
		global $currentModule;
		$sortorder = $this->default_sort_order;
		if($_REQUEST['sorder']) $sortorder = $this->db->sql_escape_string($_REQUEST['sorder']);
		else if($_SESSION[$currentModule.'_Sort_Order']) 
			$sortorder = $_SESSION[$currentModule.'_Sort_Order'];
		return $sortorder;
	}

	function add_related_to($module, $fieldname) {
		if ($fieldname=='pdoid') {
			
			global $adb, $imported_ids, $current_user,$log;
		
			$related_to = $this->column_fields[$fieldname];
	
			if(empty($related_to)){
				return false;
			}
			
			//check if the field has module information; if not get the first module
			if(!strpos($related_to, "::::")){
				$module = getFirstModule($module, $fieldname);
				$value = $related_to;
			}else{
				//check the module of the field
				$arr = array();
				$arr = explode("::::", $related_to);
				$module = $arr[0];
				$value = $arr[1];
			}
			
			$focus1 = CRMEntity::getInstance($module);
			
			$entityNameArr = getEntityField($module);
			$entityName = 'product_no';
			$query = "SELECT vtiger_crmentity.deleted, $focus1->table_name.* 
						FROM $focus1->table_name
						INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid=$focus1->table_name.$focus1->table_index
							where $entityName=? and vtiger_crmentity.deleted=0";
			$result = $adb->pquery($query, array($value));
			
			if(!isset($this->checkFlagArr[$module])){
				$this->checkFlagArr[$module] = (isPermitted($module,'EditView','') == 'yes');
			}
			
			if($adb->num_rows($result)>0){
				//record found
				$focus1->id = $adb->query_result($result, 0, $focus1->table_index);
			}elseif($this->checkFlagArr[$module]){
				//record not found; create it
			$focus1->column_fields[$focus1->list_link_field] = $value;
			$focus1->column_fields['assigned_user_id'] = $current_user->id;
			$focus1->column_fields['modified_user_id'] = $current_user->id;
				$focus1->save($module);
				
			$last_import = new UsersLastImport();
			$last_import->assigned_user_id = $current_user->id;
			$last_import->bean_type = $module;
			$last_import->bean_id = $focus1->id;
			$last_import->save();
			}else{
				//record not found and cannot create
				$this->column_fields[$fieldname] = "";
				return false;
			}
			if(!empty($focus1->id)){
				$this->column_fields[$fieldname] = $focus1->id;
				return true;
			}else{
				$this->column_fields[$fieldname] = "";
				return false;
			}
			
		} else {
			return parent::add_related_to($module, $fieldname);
		}
	}

	function getOrderBy() {
		global $currentModule;
		
		$use_default_order_by = '';		
		if(PerformancePrefs::getBoolean('LISTVIEW_DEFAULT_SORTING', true)) {
			$use_default_order_by = $this->default_order_by;
		}
		
		$orderby = $use_default_order_by;
		if($_REQUEST['order_by']) $orderby = $this->db->sql_escape_string($_REQUEST['order_by']);
		else if($_SESSION[$currentModule.'_Order_By'])
			$orderby = $_SESSION[$currentModule.'_Order_By'];
		return $orderby;
	}

	function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id,$module);
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
	 * Return query to use based on given modulename, fieldname
	 * Useful to handle specific case handling for Popup
	 */
	function getQueryByModuleField($module, $fieldname, $srcrecord, $query='') {
		// $srcrecord could be empty
	}

	/**
	 * Get list view query (send more WHERE clause condition if required)
	 */
	function getListQuery($module, $usewhere='') {
		$query = "SELECT vtiger_crmentity.*, $this->table_name.*";
		
		// Keep track of tables joined to avoid duplicates
		$joinedTables = array();

		// Select Custom Field Table Columns if present
		if(!empty($this->customFieldTable)) $query .= ", " . $this->customFieldTable[0] . ".* ";

		$query .= " FROM $this->table_name";

		$query .= "	INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = $this->table_name.$this->table_index";

		$joinedTables[] = $this->table_name;
		$joinedTables[] = 'vtiger_crmentity';
		
		// Consider custom table join as well.
		if(!empty($this->customFieldTable)) {
			$query .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index";
			$joinedTables[] = $this->customFieldTable[0]; 
		}
		$query .= " LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid";
		$query .= " LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";

		$joinedTables[] = 'vtiger_users';
		$joinedTables[] = 'vtiger_groups';
		
		$linkedModulesQuery = $this->db->pquery("SELECT distinct fieldname, columnname, relmodule FROM vtiger_field" .
				" INNER JOIN vtiger_fieldmodulerel ON vtiger_fieldmodulerel.fieldid = vtiger_field.fieldid" .
				" WHERE uitype='10' AND vtiger_fieldmodulerel.module=?", array($module));
		$linkedFieldsCount = $this->db->num_rows($linkedModulesQuery);
		
		for($i=0; $i<$linkedFieldsCount; $i++) {
			$related_module = $this->db->query_result($linkedModulesQuery, $i, 'relmodule');
			$fieldname = $this->db->query_result($linkedModulesQuery, $i, 'fieldname');
			$columnname = $this->db->query_result($linkedModulesQuery, $i, 'columnname');
			
			$other =  CRMEntity::getInstance($related_module);
			vtlib_setup_modulevars($related_module, $other);
			
			if(!in_array($other->table_name, $joinedTables)) {
				$query .= " LEFT JOIN $other->table_name ON $other->table_name.$other->table_index = $this->table_name.$columnname";
				$joinedTables[] = $other->table_name;
			}
		}

		global $current_user;
		$query .= $this->getNonAdminAccessControlQuery($module,$current_user);
		$query .= "	WHERE vtiger_crmentity.deleted = 0 ".$usewhere;
		return $query;
	}

	/**
	 * Apply security restriction (sharing privilege) query part for List view.
	 */
	function getListViewSecurityParameter($module) {
		global $current_user;
		require('user_privileges/user_privileges_'.$current_user->id.'.php');
		require('user_privileges/sharing_privileges_'.$current_user->id.'.php');

		$sec_query = '';
		$tabid = getTabid($module);

		if($is_admin==false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1 
			&& $defaultOrgSharingPermission[$tabid] == 3) {

				$sec_query .= " AND (vtiger_crmentity.smownerid in($current_user->id) OR vtiger_crmentity.smownerid IN 
					(
						SELECT vtiger_user2role.userid FROM vtiger_user2role 
						INNER JOIN vtiger_users ON vtiger_users.id=vtiger_user2role.userid 
						INNER JOIN vtiger_role ON vtiger_role.roleid=vtiger_user2role.roleid 
						WHERE vtiger_role.parentrole LIKE '".$current_user_parent_role_seq."::%'
					) 
					OR vtiger_crmentity.smownerid IN 
					(
						SELECT shareduserid FROM vtiger_tmp_read_user_sharing_per 
						WHERE userid=".$current_user->id." AND tabid=".$tabid."
					) 
					OR (";
		
					// Build the query based on the group association of current user.
					if(sizeof($current_user_groups) > 0) {
						$sec_query .= " vtiger_groups.groupid IN (". implode(",", $current_user_groups) .") OR ";
					}
					$sec_query .= " vtiger_groups.groupid IN 
						(
							SELECT vtiger_tmp_read_group_sharing_per.sharedgroupid 
							FROM vtiger_tmp_read_group_sharing_per
							WHERE userid=".$current_user->id." and tabid=".$tabid."
						)";
				$sec_query .= ")
				)";
		}
		return $sec_query;
	}

	/**
	 * Create query to export the records.
	 */
	function create_export_query($where)
	{
		global $current_user;
		$thismodule = $_REQUEST['module'];
		
		include("include/utils/ExportUtils.php");

		//To get the Permitted fields query and the permitted fields list
		$sql = getPermittedFieldsQuery($thismodule, "detail_view");
		
		$fields_list = getFieldsListFromQuery($sql);

		$query = "SELECT $fields_list, vtiger_users.user_name AS user_name 
					FROM vtiger_crmentity INNER JOIN $this->table_name ON vtiger_crmentity.crmid=$this->table_name.$this->table_index";

		if(!empty($this->customFieldTable)) {
			$query .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index"; 
		}

		$query .= " LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";
		$query .= " LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid = vtiger_users.id and vtiger_users.status='Active'";
		
		$linkedModulesQuery = $this->db->pquery("SELECT distinct fieldname, columnname, relmodule FROM vtiger_field" .
				" INNER JOIN vtiger_fieldmodulerel ON vtiger_fieldmodulerel.fieldid = vtiger_field.fieldid" .
				" WHERE uitype='10' AND vtiger_fieldmodulerel.module=?", array($thismodule));
		$linkedFieldsCount = $this->db->num_rows($linkedModulesQuery);

		$rel_mods[$this->table_name] = 1;
		for($i=0; $i<$linkedFieldsCount; $i++) {
			$related_module = $this->db->query_result($linkedModulesQuery, $i, 'relmodule');
			$fieldname = $this->db->query_result($linkedModulesQuery, $i, 'fieldname');
			$columnname = $this->db->query_result($linkedModulesQuery, $i, 'columnname');
			
			$other = CRMEntity::getInstance($related_module);
			vtlib_setup_modulevars($related_module, $other);
			if($columnname == "srcwhid")
				$query .= " LEFT JOIN $other->table_name as ".$other->table_name."SRC ON ".$other->table_name."SRC.$other->table_index = $this->table_name.$columnname";
			else
				$query .= " LEFT JOIN $other->table_name ON $other->table_name.$other->table_index = $this->table_name.$columnname";
		}

		$query .= $this->getNonAdminAccessControlQuery($thismodule,$current_user);
		$where_auto = " vtiger_crmentity.deleted=0";

		if($where != '') $query .= " WHERE ($where) AND $where_auto";
		else $query .= " WHERE $where_auto";

		return $query;
	}

	/**
	 * Initialize this instance for importing.
	 */
	function initImport($module) {
		$this->db = PearDatabase::getInstance();
		$this->initImportableFields($module);
	}

	/**
	 * Create list query to be shown at the last step of the import.
	 * Called From: modules/Import/UserLastImport.php
	 */
	function create_import_query($module) {
		global $current_user;
		$query = "SELECT vtiger_crmentity.crmid, case when (vtiger_users.user_name not like '') then vtiger_users.user_name else vtiger_groups.groupname end as user_name, $this->table_name.* FROM $this->table_name
			INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = $this->table_name.$this->table_index
			LEFT JOIN vtiger_users_last_import ON vtiger_users_last_import.bean_id=vtiger_crmentity.crmid
			LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid
			LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid
			WHERE vtiger_users_last_import.assigned_user_id='$current_user->id'
			AND vtiger_users_last_import.bean_type='$module'
			AND vtiger_users_last_import.deleted=0";
		return $query;
	}

	/**
	 * Delete the last imported records.
	 */
	function undo_import($module, $user_id) {
		global $adb;
		$count = 0;
		$query1 = "select bean_id from vtiger_users_last_import where assigned_user_id=? AND bean_type='$module' AND deleted=0";
		$result1 = $adb->pquery($query1, array($user_id)) or die("Error getting last import for undo: ".mysql_error()); 
		while ( $row1 = $adb->fetchByAssoc($result1))
		{
			$query2 = "update vtiger_crmentity set deleted=1 where crmid=?";
			$result2 = $adb->pquery($query2, array($row1['bean_id'])) or die("Error undoing last import: ".mysql_error()); 
			$count++;			
		}
		return $count;
	}
	
	/**
	 * Transform the value while exporting
	 */
	function transform_export_value($key, $value) {
		return parent::transform_export_value($key, $value);
	}

	/**
	 * Function which will set the assigned user id for import record.
	 */
	function set_import_assigned_user()
	{
		global $current_user, $adb;
		$record_user = $this->column_fields["assigned_user_id"];
		
		if($record_user != $current_user->id){
			$sqlresult = $adb->pquery("select id from vtiger_users where id = ? union select groupid as id from vtiger_groups where groupid = ?", array($record_user, $record_user));
			if($this->db->num_rows($sqlresult)!= 1) {
				$this->column_fields["assigned_user_id"] = $current_user->id;
			} else {			
				$row = $adb->fetchByAssoc($sqlresult, -1, false);
				if (isset($row['id']) && $row['id'] != -1) {
					$this->column_fields["assigned_user_id"] = $row['id'];
				} else {
					$this->column_fields["assigned_user_id"] = $current_user->id;
				}
			}
		}
	}
	
	/** 
	 * Function which will give the basic query to find duplicates
	 */
	function getDuplicatesQuery($module,$table_cols,$field_values,$ui_type_arr,$select_cols='') {
		$select_clause = "SELECT ". $this->table_name .".".$this->table_index ." AS recordid, vtiger_users_last_import.deleted,".$table_cols;

		// Select Custom Field Table Columns if present
		if(isset($this->customFieldTable)) $query .= ", " . $this->customFieldTable[0] . ".* ";

		$from_clause = " FROM $this->table_name";

		$from_clause .= "	INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = $this->table_name.$this->table_index";

		// Consider custom table join as well.
		if(isset($this->customFieldTable)) {
			$from_clause .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index"; 
		}
		$from_clause .= " LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid
						LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";
		
		$where_clause = "	WHERE vtiger_crmentity.deleted = 0";
		$where_clause .= $this->getListViewSecurityParameter($module);
					
		if (isset($select_cols) && trim($select_cols) != '') {
			$sub_query = "SELECT $select_cols FROM  $this->table_name AS t " .
				" INNER JOIN vtiger_crmentity AS crm ON crm.crmid = t.".$this->table_index;
			// Consider custom table join as well.
			if(isset($this->customFieldTable)) {
				$sub_query .= " LEFT JOIN ".$this->customFieldTable[0]." tcf ON tcf.".$this->customFieldTable[1]." = t.$this->table_index";
			}
			$sub_query .= " WHERE crm.deleted=0 GROUP BY $select_cols HAVING COUNT(*)>1";	
		} else {
			$sub_query = "SELECT $table_cols $from_clause $where_clause GROUP BY $table_cols HAVING COUNT(*)>1";
		}	
		
		
		$query = $select_clause . $from_clause .
					" LEFT JOIN vtiger_users_last_import ON vtiger_users_last_import.bean_id=" . $this->table_name .".".$this->table_index .
					" INNER JOIN (" . $sub_query . ") AS temp ON ".get_on_clause($field_values,$ui_type_arr,$module) .
					$where_clause .
					" ORDER BY $table_cols,". $this->table_name .".".$this->table_index ." ASC";
					
		return $query;		
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	function vtlib_handler($modulename, $event_type) {
		if($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
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
		} else if($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} else if($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} else if($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
		} else if($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} else if($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}
	
	/*
	 * Function to get the secondary query part of a report for which generateReportsSecQuery Doesnt exist in module 
	 * @param - $module primary module name
	 * @param - $secmodule secondary module name
	 * returns the query string formed on fetching the related data for report for secondary module
	 */
	function generateReportsSecQuery($module,$secmodule, $queryPlanner, $type = '', $where_condition = ''){
		global $adb;
		$secondary = CRMEntity::getInstance($secmodule);

		vtlib_setup_modulevars($secmodule, $secondary);
	 	
		$tablename = $secondary->table_name;
		$tableindex = $secondary->table_index;
		$modulecftable = $secondary->customFieldTable[0];
		$modulecfindex = $secondary->customFieldTable[1];
		
		if(isset($modulecftable)){
			$cfquery = "left join $modulecftable as $modulecftable on $modulecftable.$modulecfindex=$tablename.$tableindex";
		} else {
			$cfquery = '';
		}
				
		$query = $this->getRelationQuery($module,$secmodule,"$tablename","$tableindex");
		$query .=" 	left join vtiger_crmentity as vtiger_crmentity$secmodule on vtiger_crmentity$secmodule.crmid = $tablename.$tableindex AND vtiger_crmentity$secmodule.deleted=0   
					$cfquery   
					left join vtiger_groups as vtiger_groups".$secmodule." on vtiger_groups".$secmodule.".groupid = vtiger_crmentity$secmodule.smownerid
					left join vtiger_users as vtiger_users".$secmodule." on vtiger_users".$secmodule.".id = vtiger_crmentity$secmodule.smownerid"; 
	
		$fields_query = $adb->pquery("SELECT vtiger_field.fieldname,vtiger_field.tablename,vtiger_field.fieldid from vtiger_field INNER JOIN vtiger_tab on vtiger_tab.name = ? WHERE vtiger_tab.tabid=vtiger_field.tabid AND vtiger_field.uitype IN (10) and vtiger_field.presence in (0,2)",array($secmodule));
		
		if($adb->num_rows($fields_query)>0){
			for($i=0;$i<$adb->num_rows($fields_query);$i++){
				$field_name = $adb->query_result($fields_query,$i,'fieldname');
				$field_id = $adb->query_result($fields_query,$i,'fieldid');
				$tab_name = $adb->query_result($fields_query,$i,'tablename');
				$ui10_modules_query = $adb->pquery("SELECT relmodule FROM vtiger_fieldmodulerel WHERE fieldid=?",array($field_id));
		
			if($adb->num_rows($ui10_modules_query)>0){
					$query.= " left join vtiger_crmentity as vtiger_crmentityRel$secmodule$i on vtiger_crmentityRel$secmodule$i.crmid = $tab_name.$field_name and vtiger_crmentityRel$secmodule$i.deleted=0";
					for($j=0;$j<$adb->num_rows($ui10_modules_query);$j++){
						$rel_mod = $adb->query_result($ui10_modules_query,$j,'relmodule');
						$rel_obj = CRMEntity::getInstance($rel_mod);
						vtlib_setup_modulevars($rel_mod, $rel_obj);
						
						$rel_tab_name = $rel_obj->table_name;
						$rel_tab_index = $rel_obj->table_index;
					if($field_name == 'srcwhid')
						$query.= " left join $rel_tab_name as ".$rel_tab_name."RelSource$secmodule on ".$rel_tab_name."RelSource$secmodule.$rel_tab_index = vtiger_crmentityRel$secmodule$i.crmid";
					elseif($field_name == 'dstwhid')
						$query.= " left join $rel_tab_name as ".$rel_tab_name."RelDestination$secmodule on ".$rel_tab_name."RelDestination$secmodule.$rel_tab_index = vtiger_crmentityRel$secmodule$i.crmid";
					else
						$query.= " left join $rel_tab_name as ".$rel_tab_name."Rel$secmodule on ".$rel_tab_name."Rel$secmodule.$rel_tab_index = vtiger_crmentityRel$secmodule$i.crmid";
					}
				}
			}
		}

		return $query;
	}
	
	/** 
	 * Handle saving related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	// function save_related_module($module, $crmid, $with_module, $with_crmid) { }
	
	/**
	 * Handle deleting related module information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//function delete_related_module($module, $crmid, $with_module, $with_crmid) { }

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }
}
?>
