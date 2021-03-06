<?php


include('includes/session.php');

$Title = _('Stock Usage');

if (isset($_GET['StockID'])){
	$StockID = trim(mb_strtoupper($_GET['StockID']));
} elseif (isset($_POST['StockID'])){
	$StockID = trim(mb_strtoupper($_POST['StockID']));
} else {
	$StockID = '';
}

if (isset($_POST['ShowGraphUsage'])) {
	echo '<meta http-equiv="Refresh" content="0; url=' . $RootPath . '/StockUsageGraph.php?StockLocation=' . $_POST['StockLocation']  . '&amp;StockID=' . $StockID . '">';
	echo prnMsg(_('You should automatically be forwarded to the usage graph') .
			'. ' . _('If this does not happen') .' (' . _('if the browser does not support META Refresh') . ') ' .
			'<a href="' . $RootPath . '/StockUsageGraph.php?StockLocation=' . $_POST['StockLocation'] .'&amp;StockID=' . $StockID . '">' . _('click here') . '</a> ' . _('to continue'),'info');
	exit;
}

include('includes/header.php');

echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . ' ' . $Title . '
	</h1></a></div>';

$result = DB_query("SELECT description,
						units,
						mbflag,
						decimalplaces
					FROM stockmaster
					WHERE stockid='".$StockID."'");
$myrow = DB_fetch_row($result);

$DecimalPlaces = $myrow[3];
echo '<div class="row gutter30">
<div class="col-xs-12">';
echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<div class="row">';

$Its_A_KitSet_Assembly_Or_Dummy =False;
if ($myrow[2]=='K'
	OR $myrow[2]=='A'
	OR $myrow[2]=='D') {

	$Its_A_KitSet_Assembly_Or_Dummy =True;
	echo '<h2>' . $StockID . ' - ' . $myrow[0] . '</h2>';

	echo prnMsg( _('The selected item is a dummy or assembly or kit-set item and cannot have a stock holding') . '. ' . _('Please select a different item'),'warn');

	$StockID = '';
} else {
	echo '<h2>' . _('Item') . ' : ' . $StockID . ' - ' . $myrow[0] . '   (' . _('in units of') . ' : ' . $myrow[1] . ')</h2>';
}

echo '
<div class="col-xs-3">
<div class="form-group"> <label class="col-md-8 control-label">' . _('Stock Code') . '</label><input type="text" pattern="(?!^\s+$)[^%]{1,20}" class="form-control" title="'._('The input should not be blank or percentage mark').'" required="required" name="StockID" size="21" maxlength="20" value="' . $StockID . '" /></div></div>';

echo '
<div class="col-xs-3">
<div class="form-group"> <label class="col-md-8 control-label">'. _('Stock Location') . '</label><select name="StockLocation" class="form-control">';

$sql = "SELECT locations.loccode, locationname FROM locations
			INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1";
$resultStkLocs = DB_query($sql);
while ($myrow=DB_fetch_array($resultStkLocs)){
	if (isset($_POST['StockLocation'])){
		if ($myrow['loccode'] == $_POST['StockLocation']){
		     echo '<option selected="selected" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
		} else {
		     echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
		}
	} elseif ($myrow['loccode']==$_SESSION['UserStockLocation']){
		 echo '<option selected="selected" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
		 $_POST['StockLocation']=$myrow['loccode'];
	} else {
		 echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
	}
}
if (isset($_POST['StockLocation'])){
	if ('All'== $_POST['StockLocation']){
	     echo '<option selected="selected" value="All">' . _('All Locations') . '</option>';
	} else {
	     echo '<option value="All">' . _('All Locations') . '</option>';
	}
}
echo '</select></div></div>';

echo '
<div class="col-xs-3">
<div class="form-group"> <br /><input type="submit" class="btn btn-success" name="ShowUsage" value="' . _('Show') . '" /></div></div>';
echo '<div class="col-xs-3">
<div class="form-group"><br / ><input type="submit" class="btn btn-info" name="ShowGraphUsage" value="' . _('Graph') . '" /></div>
		</div>
		</div>
		<br />
		
		';


/*HideMovt ==1 if the movement was only created for the purpose of a transaction but is not a physical movement eg. A price credit will create a movement record for the purposes of display on a credit note
but there is no physical stock movement - it makes sense honest ??? */

$CurrentPeriod = GetPeriod(Date($_SESSION['DefaultDateFormat']));

if (isset($_POST['ShowUsage'])){
	if($_POST['StockLocation']=='All'){
		$sql = "SELECT periods.periodno,
				periods.lastdate_in_period,
				canview,
				SUM(CASE WHEN (stockmoves.type=10 OR stockmoves.type=11 OR stockmoves.type=17 OR stockmoves.type=28 OR stockmoves.type=38)
							AND stockmoves.hidemovt=0
							AND stockmoves.stockid = '" . $StockID . "'
						THEN -stockmoves.qty ELSE 0 END) AS qtyused
				FROM periods LEFT JOIN stockmoves
					ON periods.periodno=stockmoves.prd
				INNER JOIN locationusers ON locationusers.loccode=stockmoves.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
				WHERE periods.periodno <='" . $CurrentPeriod . "'
				GROUP BY periods.periodno,
					periods.lastdate_in_period
				ORDER BY periodno DESC LIMIT " . $_SESSION['NumberOfPeriodsOfStockUsage'];
	} else {
		$sql = "SELECT periods.periodno,
				periods.lastdate_in_period,
				SUM(CASE WHEN (stockmoves.type=10 OR stockmoves.type=11 OR stockmoves.type=17 OR stockmoves.type=28 OR stockmoves.type=38)
								AND stockmoves.hidemovt=0
								AND stockmoves.stockid = '" . $StockID . "'
								AND stockmoves.loccode='" . $_POST['StockLocation'] . "'
							THEN -stockmoves.qty ELSE 0 END) AS qtyused
				FROM periods LEFT JOIN stockmoves
					ON periods.periodno=stockmoves.prd
				WHERE periods.periodno <='" . $CurrentPeriod . "'
				GROUP BY periods.periodno,
					periods.lastdate_in_period
				ORDER BY periodno DESC LIMIT " . $_SESSION['NumberOfPeriodsOfStockUsage'];

	}
	$MovtsResult = DB_query($sql);
	if (DB_error_no() !=0) {
		echo _('The stock usage for the selected criteria could not be retrieved because') . ' - ' . DB_error_msg();
		if ($debug==1){
		echo '<br />' . _('The SQL that failed was') . $sql;
		}
		exit;
	}

	echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
		<thead>
			<tr>
						<th class="ascending">' . _('Month') . '</th>
						<th class="ascending">' . _('Usage') . '</th>
			</tr>
		</thead>
		<tbody>';

	$TotalUsage = 0;
	$PeriodsCounter =0;

	while ($myrow=DB_fetch_array($MovtsResult)) {

		$DisplayDate = MonthAndYearFromSQLDate($myrow['lastdate_in_period']);

		$TotalUsage += $myrow['qtyused'];
		$PeriodsCounter++;
		printf('<tr class="striped_row">
				<td>%s</td>
				<td class="number">%s</td>
				</tr>',
				$DisplayDate,
				locale_number_format($myrow['qtyused'],$DecimalPlaces));
	} //end of while loop

	echo '</tbody></table></div></div></div>';

	if ($TotalUsage>0 AND $PeriodsCounter>0){
		echo '<table class="selection"><tr>
				<th colspan="2">' . _('<strong>Average Usage per month:</strong>') . ' ' . locale_number_format($TotalUsage/$PeriodsCounter) . '</th>
			</tr></table>';
	}

} /* end if Show Usage is clicked */

echo '<div class="row">';
echo '<div class="col-xs-2">
    <a href="' . $RootPath . '/StockStatus.php?StockID=' . $StockID . '" class="btn btn-info">' . _('Stock Status')  . '</a></div>';
echo '<div class="col-xs-2">
	<a href="' . $RootPath . '/StockMovements.php?StockID=' . $StockID . '&amp;StockLocation=' . $_POST['StockLocation'] . '" class="btn btn-info">' . _('Stock Movements') . '</a></div>';
echo '<div class="col-xs-2">
	<a href="' . $RootPath . '/SelectSalesOrder.php?SelectedStockItem=' . $StockID . '&amp;StockLocation=' . $_POST['StockLocation'] . '" class="btn btn-info">' . _('Outstanding Sales Orders') . '</a></div>';
echo '<div class="col-xs-3">
	<a href="' . $RootPath . '/SelectCompletedOrder.php?SelectedStockItem=' . $StockID . '" class="btn btn-info">' . _('Completed Sales Orders') . '</a></div>';
echo '<div class="col-xs-3">
	<a href="' . $RootPath . '/PO_SelectOSPurchOrder.php?SelectedStockItem=' . $StockID . '" class="btn btn-info">' . _('Outstanding Purchase Orders') . '</a></div>';

echo '</div>
      
      </form></div></div><br />';
include('includes/footer.php');

?>
