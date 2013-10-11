<?php
//Functions to pull result from XML
function GetXMLValue($XMLElement, $XML, $pattern) {
	$soapArray = null;
	$ToReturn = null;
	if (preg_match('#<'.$XMLElement.'>('.$pattern.')</'.$XMLElement.'>#iU', $XML, $soapArray)) {
		$ToReturn = $soapArray[1];
	} else {
		$ToReturn = $XMLElement . " Not Found";
	}
	
	return $ToReturn;
}

function GetCrossReference($XML) {
	$soapArray = null;
	$ToReturn = null;
	if (preg_match('#<TransactionOutputData CrossReference="(.+)">#iU', $XML, $soapArray)) {
		$ToReturn = $soapArray[1];
	} else {
		$ToReturn = "No Data Found";
	}
	
	return $ToReturn;
}

function stripGWInvalidChars($strToCheck) {
	$toReplace = array("<","&");
	$replaceWith = array("","&amp;");
	$cleanString = str_replace($toReplace, $replaceWith, $strToCheck);
	return $cleanString;
}
?>