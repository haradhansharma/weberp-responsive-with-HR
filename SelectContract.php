<?php

include('includes/session.php');
$Title = _('Select Contract');
$ViewTopic= 'Contracts';
$BookMark = 'SelectContract';
include('includes/header.php');

echo '<div class="block-header"><a href="" class="header-title-link"><h1>', // Icon title.
	_('List of Contracts'), '</h1></a></div>';// Page title.

echo '<div class="row gutter30">
<div class="col-xs-12">';
echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

//echo '<br /><div class="centre">';

if (isset($_GET['ContractRef'])){
	$_POST['ContractRef']=$_GET['ContractRef'];
}
if (isset($_GET['SelectedCustomer'])){
	$_POST['SelectedCustomer']=$_GET['SelectedCustomer'];
}


if (isset($_POST['ContractRef']) AND $_POST['ContractRef']!='') {
	$_POST['ContractRef'] = trim($_POST['ContractRef']);
	echo _('Contract Reference') . ' - ' . $_POST['ContractRef'];
} else {
	if (isset($_POST['SelectedCustomer'])) {
		echo _('For customer') . ': ' . $_POST['SelectedCustomer'] . ' ' . _('and') . ' ';
		echo '<input type="hidden" name="SelectedCustomer" value="' . $_POST['SelectedCustomer'] . '" />';
	}
}

if (!isset($_POST['ContractRef']) or $_POST['ContractRef']==''){
echo '<div class="row">
				<div class="col-xs-3">
        <div class="form-group"> <label class="col-md-8 control-label">';
	echo _('Contract Reference') . '</label> <input type="text" name="ContractRef" class="form-control" maxlength="20" size="20" /></div></div>';
	echo '<div class="col-xs-3">
        <div class="form-group"> <label class="col-md-8 control-label">Status</label><select name="Status" class="form-control">';

	if (isset($_GET['Status'])){
		$_POST['Status']=$_GET['Status'];
	}
	if (!isset($_POST['Status'])){
		$_POST['Status']=4;
	}

	$statuses[] = _('Not Yet Quoted');
	$statuses[] = _('Quoted - No Order Placed');
	$statuses[] = _('Order Placed');
	$statuses[] = _('Completed');
	$statuses[] = _('All Contracts');

	$status_count = count($statuses);

	for ( $i = 0; $i < $status_count; $i++ ) {
		if ( $i == $_POST['Status'] ) {
			echo '<option selected="selected" value="' . $i . '">' . $statuses[$i] . '</option>';
		} else {
			echo '<option value="' . $i . '">' . $statuses[$i] . '</option>';
		}
	}

	echo '</select> </div></div>';
}
echo '<div class="col-xs-3">
        <div class="form-group"> <br />
<input type="submit" name="SearchContracts" class="btn btn-success" value="' . _('Search') . '" /></div></div>';
echo '<div class="col-xs-3">
        <div class="form-group"> <br />
<a href="' . $RootPath . '/Contracts.php" class="btn btn-info">' . _('New Contract') . '</a></div></div></div><br />';


//figure out the SQL required from the inputs available

if (isset($_POST['ContractRef']) AND $_POST['ContractRef'] !='') {
		$SQL = "SELECT contractref,
					   contractdescription,
					   categoryid,
					   contracts.debtorno,
					   debtorsmaster.name AS customername,
					   branchcode,
					   status,
					   orderno,
					   wo,
					   customerref,
					   requireddate
				FROM contracts INNER JOIN debtorsmaster
				ON contracts.debtorno = debtorsmaster.debtorno
				INNER JOIN locationusers ON locationusers.loccode=contracts.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
				WHERE contractref " . LIKE . " '%" .  $_POST['ContractRef'] ."%'";

} else { //contractref not selected
	if (isset($_POST['SelectedCustomer'])) {

		$SQL = "SELECT contractref,
					   contractdescription,
					   categoryid,
					   contracts.debtorno,
					   debtorsmaster.name AS customername,
					   branchcode,
					   status,
					   orderno,
					   wo,
					   customerref,
					   requireddate
				FROM contracts INNER JOIN debtorsmaster
				ON contracts.debtorno = debtorsmaster.debtorno
				INNER JOIN locationusers ON locationusers.loccode=contracts.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
				WHERE debtorno='". $_POST['SelectedCustomer'] ."'";
		if ($_POST['Status']!=4){
			$SQL .= " AND status='" . $_POST['Status'] . "'";
		}
	} else { //no customer selected
		$SQL = "SELECT contractref,
					   contractdescription,
					   categoryid,
					   contracts.debtorno,
					   debtorsmaster.name AS customername,
					   branchcode,
					   status,
					   orderno,
					   wo,
					   customerref,
					   requireddate
				FROM contracts INNER JOIN debtorsmaster
				ON contracts.debtorno = debtorsmaster.debtorno
				INNER JOIN locationusers ON locationusers.loccode=contracts.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1";
		if ($_POST['Status']!=4){
			$SQL .= " AND status='" . $_POST['Status'] . "'";
		}
	}
} //end not contract ref selected

$ErrMsg = _('No contracts were returned by the SQL because');
$ContractsResult = DB_query($SQL,$ErrMsg);

/*show a table of the contracts returned by the SQL */

echo '<div class="col-xs-12">
<div class="table-responsive">
			<table id="general-table" class="table table-bordered">';

$TableHeader = '<thead>
<tr>
					<th>' . _('Action') . '</th>
					<th>' . _('Order/Quote #') . '</th>
					<th>' . _('Issue To WO') . '</th>
					<th>' . _('Costing') . '</th>
					<th>' . _('Contract Ref') . '</th>
					<th>' . _('Description') . '</th>
					<th>' . _('Customer') . '</th>
					<th>' . _('Required Date') . '</th>
				</tr></thead>';

echo $TableHeader;

$j = 1;

while ($myrow=DB_fetch_array($ContractsResult)) {
	echo '<tr class="striped_row">';

	$ModifyPage = $RootPath . '/Contracts.php?ModifyContractRef=' . $myrow['contractref'];
	$OrderModifyPage = $RootPath . '/SelectOrderItems.php?ModifyOrderNumber=' . $myrow['orderno'];
	$IssueToWOPage = $RootPath . '/WorkOrderIssue.php?WO=' . $myrow['wo'] . '&amp;StockID=' . $myrow['contractref'];
	$CostingPage = $RootPath . '/ContractCosting.php?SelectedContract=' . $myrow['contractref'];
	$FormatedRequiredDate = ConvertSQLDate($myrow['requireddate']);

	if ($myrow['status']==0 OR $myrow['status']==1){ //still setting up the contract
		echo '<td><a href="' . $ModifyPage . '" class="btn btn-info">' . _('Modify') . '</a></td>';
	} else {
		echo '<td>' . _('<strong>N/A</strong>') . '</td>';
	}
	if ($myrow['status']==1 OR $myrow['status']==2){ // quoted or ordered
		echo '<td><a href="' . $OrderModifyPage . '" class="btn btn-info">' . $myrow['orderno'] . '</a></td>';
	} else {
		echo '<td>' . _('<strong>N/A</strong>') . '</td>';
	}
	if ($myrow['status']==2){ //the customer has accepted the quote but not completed contract yet
		echo '<td><a href="' . $IssueToWOPage . '" class="btn btn-info">' . $myrow['wo'] . '</a></td>';
	} else {
		echo '<td>' . _('<strong>N/A</strong>') . '</td>';
	}
	if ($myrow['status']==2 OR $myrow['status']==3){
			echo '<td><a href="' . $CostingPage . '" class="btn btn-info">' . _('View') . '</a></td>';
		} else {
			echo '<td>' . _('<strong>N/A</strong>') . '</td>';
	}
	echo '<td>' . $myrow['contractref'] . '</td>
		  <td>' . $myrow['contractdescription'] . '</td>
		  <td>' . $myrow['customername'] . '</td>
		  <td>' . $FormatedRequiredDate . '</td></tr>';

	$j++;
	if ($j == 12){
		$j=1;
		echo $TableHeader;
	}
//end of page full new headings if
}
//end of while loop

echo '</table>
      </div>
	  </div>
      </form>
      </div>
	  </div>';
include('includes/footer.php');
?>