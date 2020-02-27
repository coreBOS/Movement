/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
function WareHouseProductsOpenCapture(fromlink,fldname,MODULE,ID) {
	var WindowSettings = "width=680,height=602,resizable=0,scrollbars=0,top=150,left=200";
	var baseURL = "index.php?module=Products&action=Popup&html=Popup_picker&form=vtlibPopupView&forfield="+fldname+"&srcmodule="+MODULE;
	if(MODULE == 'Movement')
			window.open(baseURL+"&forrecord="+ID+"&srcwhid="+ document.EditView.srcwhid.value+"&cbcustompopupinfo=srcwhid","vtlibui10",WindowSettings);
}