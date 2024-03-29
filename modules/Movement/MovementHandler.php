<?php
/*************************************************************************************************
* Copyright 2012 JPL TSolucio, S.L.  --  This file is a part of coreBOS Multiwarehouse Extension.
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

class MovementHandler extends VTEventHandler {

	public function handleEvent($eventName, $entityData) {

		if ($eventName == 'vtiger.entity.beforesave') {
			$moduleName = $entityData->getModuleName();
			if ($moduleName == 'Movement') {
				$mvId = $entityData->getId();
				if (!empty($mvId) && $_REQUEST['action'] != 'Import') {  // Editing, we undo the previous values of movement
					$entityData->focus->column_fields['unitsmvto']=-1*$entityData->focus->column_fields['unitsmvto'];
					$entityData->focus->executeMovement();
				}
			}
		}

		if ($eventName == 'vtiger.entity.aftersave' || $eventName == 'vtiger.entity.afterrestore') {
			$moduleName = $entityData->getModuleName();
			if ($moduleName == 'Movement') {
				// We always do this, if creating it is correct, if editing we undid the previous changes
				$entityData->focus->executeMovement();
			}
		}
	}
}
?>
