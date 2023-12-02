<?php
/*************************************************************************************************
* Copyright 2012 JPL TSolucio, S.L.  --  This file is a part of vtiger CRM Multiwarehouse Extension.
* You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
* Vizsage Public License (the "License"). You may not use this file except in compliance with the
* License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
* and share improvements. However, for proper details please read the full License, available at
* http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
* the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
* applicable law or agreed to in writing, any software distributed under the License is distributed
* on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and limitations under the
* License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
*************************************************************************************************/

function mwUpdateStock($entity_id, $src, $dst) {
	global $log, $adb, $updateInventoryProductRel_update_product_array, $current_user;
	include_once 'modules/Movement/Movement.php';
	$mv = new Movement();
	$mv->column_fields['assigned_user_id'] = $current_user->id;
	$update_product_array = $updateInventoryProductRel_update_product_array;
	$log->debug('> mwUpdateStock '.$entity_id);

	if (!empty($update_product_array)) {
		foreach ($update_product_array as $seq) {
			foreach ($seq as $seq => $product_info) {
				foreach ($product_info as $pdo => $uds) {
					if (getSalesEntityType($pdo)=='Services') {
						continue;
					}
					$mvid=$adb->getone("select movementid from vtiger_movement
						inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_movement.movementid
						where deleted = 0 and srcwhid=$src and dstwhid=$dst and pdoid=$pdo and refid=$entity_id and seqno=$seq");
					if (!empty($mvid)) {
						$mv->trash('Movement', $mvid);
					}
				}
			}
		}
	}
	$adb->pquery('UPDATE vtiger_inventoryproductrel SET incrementondel=1 WHERE id=?', array($entity_id));

	$product_info = $adb->pquery('SELECT productid,sequence_no, quantity from vtiger_inventoryproductrel WHERE id=?', array($entity_id));
	$numrows = $adb->num_rows($product_info);
	for ($index = 0; $index <$numrows; $index++) {
		$mv->column_fields['pdoid']=$adb->query_result($product_info, $index, 'productid');
		if (getSalesEntityType($mv->column_fields['pdoid'])=='Services') {
			continue;
		}
		$sequence_no = $adb->query_result($product_info, $index, 'sequence_no');
		unset($mv->id);
		$mv->mode='';
		$mv->column_fields['srcwhid']=$src;
		$mv->column_fields['dstwhid']=$dst;
		$mv->column_fields['unitsmvto']=$adb->query_result($product_info, $index, 'quantity');
		$mv->column_fields['refid']=$entity_id;
		$mv->column_fields['seqno']=$sequence_no;
		$mv->save('Movement');
		$adb->query("update vtiger_movement set refid=$entity_id, seqno=$sequence_no where movementid=".$mv->id);
		mwUpdateStockSubProducts($mv->column_fields['pdoid'], $mv, $mv->column_fields['unitsmvto'], $entity_id, $sequence_no);
	}
	$log->debug('< mwUpdateStock');
}

function mwUpdateStockSubProducts($pdoid,$focus_mv,$qty_parent,$entity_id,$sequence_no) {
	global $adb;
	$res_sub_product = $adb->pquery("SELECT qty,vtiger_products.productid, vtiger_products.productname, vtiger_products.product_no
		FROM vtiger_products
		INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_products.productid
		INNER JOIN vtiger_productcf ON vtiger_productcf.productid = vtiger_products.productid
		LEFT JOIN vtiger_seproductsrel ON vtiger_seproductsrel.crmid = vtiger_products.productid AND vtiger_seproductsrel.setype='Products'
		WHERE vtiger_crmentity.deleted = 0 AND vtiger_seproductsrel.productid = ?",array($pdoid));
	if ($adb->num_rows($res_sub_product)>0) {
		for($j=0; $j<$adb->num_rows($res_sub_product); $j++) {
			$pdo = $adb->query_result($res_sub_product,$j,"productid");

			//Check if movement exist
			$mvid=$adb->getone("select movementid from vtiger_movement
				inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_movement.movementid
				where deleted = 0 and srcwhid=$src and dstwhid=$dst and pdoid=$pdo and refid=$entity_id and seqno=$sequence_no");
			if (!empty($mvid)) {
				$focus_mv->trash('Movement', $mvid);
			}

			$focus_mv->column_fields['pdoid'] = $pdo;
			$focus_mv->column_fields['unitsmvto'] = $adb->query_result($res_sub_product,$j,"qty") * $qty_parent;
			unset($focus_mv->id);
			$focus_mv->mode='';
			$focus_mv->save('Movement');
			$adb->query("update vtiger_movement set refid=$entity_id, seqno=$sequence_no where movementid=".$focus_mv->id);
			mwUpdateStockSubProducts($focus_mv->column_fields['pdoid'],$focus_mv,$focus_mv->column_fields['unitsmvto'],$entity_id,$sequence_no);
		}
	}
}

function mwIncrementStock($entity) {
	global $adb;
	$entity_id = vtws_getIdComponents($entity->getId());
	$entity_id = $entity_id[1];
	$src=$adb->getone("select warehouseid from vtiger_warehouse where warehno='Purchase'");
	$dst=$adb->getone("select whid from vtiger_purchaseorder where purchaseorderid=$entity_id");
	mwUpdateStock($entity_id, $src, $dst);
}

function mwDecrementStock($entity) {
	global $adb;
	$entity_id = vtws_getIdComponents($entity->getId());
	$entity_id = $entity_id[1];
	if ($entity->moduleName=='Invoice') {
		$table='invoice';
	} else {
		$table='salesorder';
	}
	$dst=$adb->getone("select warehouseid from vtiger_warehouse where warehno='Sale'");
	$src=$adb->getone("select whid from vtiger_$table where ".$table."id=$entity_id");
	mwUpdateStock($entity_id, $src, $dst);
}

function mwReturnPOStock($entity_id) {
	global $adb;
	$dst=$adb->getone("select warehouseid from vtiger_warehouse where warehno='Purchase'");
	$src=$adb->getone("select whid from vtiger_purchaseorder where purchaseorderid=$entity_id");
	mwUpdateStock($entity_id, $src, $dst);
}

function mwReturnSOIStock($entity_id, $table) {
	global $adb;
	$src=$adb->getone("select warehouseid from vtiger_warehouse where warehno='Sale'");
	$dst=$adb->getone("select whid from vtiger_$table where ".$table."id=$entity_id");
	mwUpdateStock($entity_id, $src, $dst);
}

function mwSrcToDstStock($entity) {
	global $adb;
	$entity_id = vtws_getIdComponents($entity->getId());
	$entity_id = $entity_id[1];
	$src=$adb->getone("select whid from vtiger_massivemovements where massivemovementsid=$entity_id");
	$dst=$adb->getone("select dstwhid from vtiger_massivemovements where massivemovementsid=$entity_id");
	mwUpdateStock($entity_id,$src,$dst);
}

function mwReturnMMvStock($entity_id) {
	global $adb;
	$dst=$adb->getone("select srcwhid from vtiger_massivemovements where massivemovementsid=$entity_id");
	$src=$adb->getone("select dstwhid from vtiger_massivemovements where massivemovementsid=$entity_id");
	mwUpdateStock($entity_id,$src,$dst);
}
function mwReturnStock($entity) {
	$entity_id = vtws_getIdComponents($entity->getId());
	$entity_id = $entity_id[1];
	switch ($entity->moduleName) {
		case 'Invoice':
			mwReturnSOIStock($entity_id, 'invoice');
			break;
		case 'SalesOrder':
			mwReturnSOIStock($entity_id, 'salesorder');
			break;
		case 'PurchaseOrder':
			mwReturnPOStock($entity_id);
			break;
		case 'MassiveMovemnts':
			mwReturnMMvStock($entity_id);
			break;
	}
}
?>
