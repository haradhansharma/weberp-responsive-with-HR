<?php

include ('includes/session.php');

$Title = _('Supplier Purchasing Data');

include ('includes/header.php');

if (isset($_GET['SupplierID'])) {
    $SupplierID = trim(mb_strtoupper($_GET['SupplierID']));
} elseif (isset($_POST['SupplierID'])) {
    $SupplierID = trim(mb_strtoupper($_POST['SupplierID']));
}

if (isset($_GET['StockID'])) {
    $StockID = trim(mb_strtoupper($_GET['StockID']));
} elseif (isset($_POST['StockID'])) {
    $StockID = trim(mb_strtoupper($_POST['StockID']));
}

if (isset($_GET['Edit'])) {
    $Edit = true;
} elseif (isset($_POST['Edit'])) {
    $Edit = true;
} else {
	$Edit = false;
}

if (isset($_GET['EffectiveFrom'])) {
	$EffectiveFrom = $_GET['EffectiveFrom'];
} elseif ($Edit == true AND isset($_POST['EffectiveFrom'])) {
	$EffectiveFrom = FormatDateForSQL($_POST['EffectiveFrom']);
}


if (isset($_POST['StockUOM'])) {
	$StockUOM=$_POST['StockUOM'];
}


/*Deleting a supplier purchasing discount */
if (isset($_GET['DeleteDiscountID'])){
	$Result = DB_query("DELETE FROM supplierdiscounts WHERE id='" . intval($_GET['DeleteDiscountID']) . "'");
	echo prnMsg(_('Deleted the supplier discount record'),'success');
}


$NoPurchasingData=0;


if (isset($_POST['SupplierDescription'])) {
    $_POST['SupplierDescription'] = trim($_POST['SupplierDescription']);
}

if ((isset($_POST['AddRecord']) OR isset($_POST['UpdateRecord'])) AND isset($SupplierID)) { /*Validate Inputs */
	$InputError = 0; /*Start assuming the best */

	if ($StockID == '' OR !isset($StockID)) {
		$InputError = 1;
		echo prnMsg(_('There is no stock item set up enter the stock ID or select a stock item using the search page'), 'error');
	}
	if (!is_numeric(filter_number_format($_POST['Price']))) {
		$InputError = 1;
		unset($_POST['Price']);
		echo prnMsg(_('The price entered was not numeric and a number is expected. No changes have been made to the system'), 'error');
	} elseif ($_POST['Price'] == 0) {
		echo prnMsg(_('The price entered is zero') . '   ' . _('Is this intentional?'), 'warn');
	}
	if (!is_numeric(filter_number_format($_POST['LeadTime']))) {
		$InputError = 1;
		unset($_POST['LeadTime']);
		echo prnMsg(_('The lead time entered was not numeric a number of days is expected no changes have been made to the system'), 'error');
	}
	if (!is_numeric(filter_number_format($_POST['MinOrderQty']))) {
		$InputError = 1;
		unset($_POST['MinOrderQty']);
		echo prnMsg(_('The minimum order quantity was not numeric and a number is expected no changes have been made to the system'), 'error');
	}
	if (!is_numeric(filter_number_format($_POST['ConversionFactor']))) {
		$InputError = 1;
		unset($_POST['ConversionFactor']);
		echo prnMsg(_('The conversion factor entered was not numeric') . ' (' . _('a number is expected') . '). ' . _('The conversion factor is the number which the price must be divided by to get the unit price in our unit of measure') . '. <br />' . _('E.g.') . ' ' . _('The supplier sells an item by the tonne and we hold stock by the kg') . '. ' . _('The suppliers price must be divided by 1000 to get to our cost per kg') . '. ' . _('The conversion factor to enter is 1000') . '. <br /><br />' . _('No changes will be made to the system'), 'error');
	}
    if (!Is_Date($_POST['EffectiveFrom'])){
		$InputError =1;
		unset($_POST['EffectiveFrom']);
		echo prnMsg (_('The date this purchase price is to take effect from must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'],'error');
	}
    if ($InputError == 0 AND isset($_POST['AddRecord'])) {
        $sql = "INSERT INTO purchdata (supplierno,
										stockid,
										price,
										effectivefrom,
										suppliersuom,
										conversionfactor,
										supplierdescription,
										suppliers_partno,
										leadtime,
										minorderqty,
										preferred)
						VALUES ('" . $SupplierID . "',
							'" . $StockID . "',
							'" . filter_number_format($_POST['Price']) . "',
							'" . FormatDateForSQL($_POST['EffectiveFrom']) . "',
							'" . $_POST['SuppliersUOM'] . "',
							'" . filter_number_format($_POST['ConversionFactor']) . "',
							'" . $_POST['SupplierDescription'] . "',
							'" . $_POST['SupplierCode'] . "',
							'" . filter_number_format($_POST['LeadTime']) . "',			                '" . filter_number_format($_POST['MinOrderQty']) . "',
							'" . $_POST['Preferred'] . "')";
        $ErrMsg = _('The supplier purchasing details could not be added to the database because');
        $DbgMsg = _('The SQL that failed was');
        $AddResult = DB_query($sql, $ErrMsg, $DbgMsg);
        echo prnMsg(_('This supplier purchasing data has been added to the system'), 'success');
    }
    if ($InputError == 0 AND isset($_POST['UpdateRecord'])) {
        $sql = "UPDATE purchdata SET price='" . filter_number_format($_POST['Price']) . "',
										effectivefrom='" . FormatDateForSQL($_POST['EffectiveFrom']) . "',
										suppliersuom='" . $_POST['SuppliersUOM'] . "',
										conversionfactor='" . filter_number_format($_POST['ConversionFactor']) . "',
										supplierdescription='" . $_POST['SupplierDescription'] . "',
										suppliers_partno='" . $_POST['SupplierCode'] . "',
										leadtime='" . filter_number_format($_POST['LeadTime']) . "',
										minorderqty='" . filter_number_format($_POST['MinOrderQty']) . "',
										preferred='" . $_POST['Preferred'] . "'
							WHERE purchdata.stockid='" . $StockID . "'
							AND purchdata.supplierno='" . $SupplierID . "'
							AND purchdata.effectivefrom='" . $_POST['WasEffectiveFrom'] . "'";
        $ErrMsg = _('The supplier purchasing details could not be updated because');
        $DbgMsg = _('The SQL that failed was');
        $UpdResult = DB_query($sql, $ErrMsg, $DbgMsg);
        echo prnMsg(_('Supplier purchasing data has been updated'), 'success');

		/*Now need to validate supplier purchasing discount records  and update/insert as necessary */
		$ErrMsg = _('The supplier purchasing discount details could not be updated because');
		$DiscountInputError = false;
		for ($i=0;$i<$_POST['NumberOfDiscounts'];$i++) {
			if (mb_strlen($_POST['DiscountNarrative' . $i])==0 OR $_POST['DiscountNarrative' . $i]==''){
				echo prnMsg(_('Supplier discount narrative cannot be empty. No changes will be made to this record'),'error');
				$DiscountInputError = true;
			} elseif (filter_number_format($_POST['DiscountPercent' . $i])>100 OR  filter_number_format($_POST['DiscountPercent' . $i]) < 0) {
				echo prnMsg(_('Supplier discount percent must be greater than zero but less than 100 percent. No changes will be made to this record'),'error');
				$DiscountInputError = true;
			}  elseif (filter_number_format($_POST['DiscountPercent' . $i])<>0 AND  filter_number_format($_POST['DiscountAmount' . $i]) <> 0) {
				echo prnMsg(_('Both the supplier discount percent and discount amount are non-zero. Only one or the other can be used. No changes will be made to this record'),'error');
				$DiscountInputError = true;
			} elseif (Date1GreaterThanDate2($_POST['DiscountEffectiveFrom' . $i], $_POST['DiscountEffectiveTo' .$i])) {
				echo prnMsg(_('The effective to date is prior to the effective from date. No changes will be made to this record'),'error');
				$DiscountInputError = true;
			}
			if ($DiscountInputError == false) {
				$sql = "UPDATE supplierdiscounts SET discountnarrative ='" . $_POST['DiscountNarrative' . $i] . "',
													discountamount ='" . filter_number_format($_POST['DiscountAmount' . $i]) . "',
													discountpercent = '" . filter_number_format($_POST['DiscountPercent' . $i])/100 . "',
													effectivefrom = '" . FormatDateForSQL($_POST['DiscountEffectiveFrom' . $i]) . "',
													effectiveto = '" . FormatDateForSQL($_POST['DiscountEffectiveTo' . $i]) . "'
						WHERE id = " . intval($_POST['DiscountID' . $i]);
				$UpdResult = DB_query($sql, $ErrMsg, $DbgMsg);
			}
		} /*end loop through all supplier discounts */

		/*Now check to see if a new Supplier Discount has been entered */
		if (mb_strlen($_POST['DiscountNarrative'])==0 OR $_POST['DiscountNarrative']==''){
			/* A new discount entry has not been entered */
		} elseif (filter_number_format($_POST['DiscountPercent'])>100 OR  filter_number_format($_POST['DiscountPercent']) < 0) {
			echo prnMsg(_('Supplier discount percent must be greater than zero but less than 100 percent. This discount record cannot be added.'),'error');
		}  elseif (filter_number_format($_POST['DiscountPercent'])<>0 AND  filter_number_format($_POST['DiscountAmount']) <> 0) {
			echo prnMsg(_('Both the supplier discount percent and discount amount are non-zero. Only one or the other can be used. This discount record cannot be added.'),'error');
		} elseif (Date1GreaterThanDate2($_POST['DiscountEffectiveFrom'], $_POST['DiscountEffectiveTo'])) {
			echo prnMsg(_('The effective to date is prior to the effective from date. This discount record cannot be added.'),'error');
		} elseif(filter_number_format($_POST['DiscountPercent'])==0 AND  filter_number_format($_POST['DiscountAmount']) ==0) {
			echo prnMsg(_('Some supplier discount narrative was entered but both the discount amount and the discount percent are zero. One of these must be none zero to create a valid supplier discount record. The supplier discount record was not added.'),'error');
		} else {
			/*It looks like a valid new discount entry has been entered - need to insert it into DB */
			$sql = "INSERT INTO supplierdiscounts ( supplierno,
													stockid,
													discountnarrative,
													discountamount,
													discountpercent,
													effectivefrom,
													effectiveto )
						VALUES ('" . $SupplierID . "',
								'" . $StockID . "',
								'" . $_POST['DiscountNarrative'] . "',
								'" . floatval($_POST['DiscountAmount']) . "',
								'" . floatval($_POST['DiscountPercent'])/100 . "',
								'" . FormatDateForSQL($_POST['DiscountEffectiveFrom']) . "',
								'" . FormatDateForSQL($_POST['DiscountEffectiveTo']) . "')";
			$ErrMsg = _('Could not insert a new supplier discount entry because');
			$DbgMsg = _('The SQL used to insert the supplier discount entry that failed was');
			$InsertResult = DB_query($sql, $ErrMsg, $DbgMsg);
			echo prnMsg(_('A new supplier purchasing discount record was entered successfully'),'success');
		}

    }

    if ($InputError == 0 AND isset($_POST['AddRecord'])) {
	/*  insert took place and need to clear the form  */
        unset($SupplierID);
        unset($_POST['Price']);
        unset($CurrCode);
        unset($_POST['SuppliersUOM']);
        unset($_POST['EffectiveFrom']);
        unset($_POST['ConversionFactor']);
        unset($_POST['SupplierDescription']);
        unset($_POST['LeadTime']);
        unset($_POST['Preferred']);
        unset($_POST['SupplierCode']);
        unset($_POST['MinOrderQty']);
        unset($SuppName);
        for ($i=0;$i<$_POST['NumberOfDiscounts'];$i++) {
			unset($_POST['DiscountNarrative' . $i]);
			unset($_POST['DiscountAmount' . $i]);
			unset($_POST['DiscountPercent' . $i]);
			unset($_POST['DiscountEffectiveFrom' . $i]);
			unset($_POST['DiscountEffectiveTo' . $i]);
		}
		unset($_POST['NumberOfDiscounts']);

    }
}

if (isset($_GET['Delete'])) {
    $sql = "DELETE FROM purchdata
	   				WHERE purchdata.supplierno='" . $SupplierID . "'
	   				AND purchdata.stockid='" . $StockID . "'
	   				AND purchdata.effectivefrom='" . $EffectiveFrom . "'";
    $ErrMsg = _('The supplier purchasing details could not be deleted because');
    $DelResult = DB_query($sql, $ErrMsg);
    echo prnMsg(_('This purchasing data record has been successfully deleted'), 'success');
    unset($SupplierID);
}


if ($Edit == false) {

	$ItemResult = DB_query("SELECT description FROM stockmaster WHERE stockid='" . $StockID . "'");
	$DescriptionRow = DB_fetch_array($ItemResult);
	echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . ' ' . $Title . ' ' . _('For Stock ID:') . '  ' . $StockID . ' - ' . $DescriptionRow['description'] . '</h1></a></div><br />';
	
echo '<p align="left"><a href="' . $RootPath . '/SelectProduct.php" class="btn btn-default">' . _('Back to Items') . '</a></p>';	
	

    $sql = "SELECT purchdata.supplierno,
				suppliers.suppname,
				purchdata.price,
				suppliers.currcode,
				purchdata.effectivefrom,
				purchdata.suppliersuom,
				purchdata.supplierdescription,
				purchdata.leadtime,
				purchdata.suppliers_partno,
				purchdata.minorderqty,
				purchdata.preferred,
				purchdata.conversionfactor,
				currencies.decimalplaces AS currdecimalplaces
			FROM purchdata INNER JOIN suppliers
				ON purchdata.supplierno=suppliers.supplierid
			INNER JOIN currencies
				ON suppliers.currcode=currencies.currabrev
			WHERE purchdata.stockid = '" . $StockID . "'
			ORDER BY purchdata.effectivefrom DESC";
    $ErrMsg = _('The supplier purchasing details for the selected part could not be retrieved because');
    $PurchDataResult = DB_query($sql, $ErrMsg);
    if (DB_num_rows($PurchDataResult) == 0 and $StockID != '') {
		echo prnMsg(_('There is no purchasing data set up for the part selected'), 'info');
		$NoPurchasingData=1;
    } else if ($StockID != '') {

        echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
			<thead>
				<tr>
							<th class="ascending">' . _('Supplier') . '</th>
							<th class="ascending">' . _('Price') . '</th>
							<th>' . _('Supplier Unit') . '</th>
							<th>' . _('Conversion Factor') . '</th>
							<th class="ascending">' . _('Cost Per Our Unit') .  '</th>
							<th class="ascending">' . _('Currency') . '</th>
							<th class="ascending">' . _('Effective From') . '</th>
							<th class="ascending">' . _('Min Order Qty') . '</th>
							<th class="ascending">' . _('Lead Time') . '</th>
							<th>' . _('Preferred') . '</th>
							<th colspan="3">' . _('Actions') . '</th>
				</tr>
			</thead>
			<tbody>';

		$CountPreferreds = 0;

		while ($myrow = DB_fetch_array($PurchDataResult)) {
			if ($myrow['preferred'] == 1) {
				$DisplayPreferred = _('Yes');
				$CountPreferreds++;
			} else {
				$DisplayPreferred = _('No');
			}
			$UPriceDecimalPlaces = max($myrow['currdecimalplaces'],$_SESSION['StandardCostDecimalPlaces']);
			printf('<tr class="striped_row">
					<td>%s</td>
					<td class="number">%s</td>
					<td>%s</td>
					<td class="number">%s</td>
					<td class="number">%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s ' . _('days') . '</td>
					<td>%s</td>
					<td><a href="%s?StockID=%s&amp;SupplierID=%s&amp;Edit=1&amp;EffectiveFrom=%s" class="btn btn-info">' . _('Edit') . '</a></td>
					<td><a href="%s?StockID=%s&amp;SupplierID=%s&amp;Copy=1&amp;EffectiveFrom=%s" class="btn  btn-info">' . _('Copy') . '</a></td>
					<td><a href="%s?StockID=%s&amp;SupplierID=%s&amp;Delete=1&amp;EffectiveFrom=%s" class="btn btn-danger" onclick=\'return confirm("' . _('Are you sure you wish to delete this suppliers price?') . '");\'>' . _('Delete') . '</a></td>
					</tr>',
					$myrow['suppname'],
					locale_number_format($myrow['price'],$UPriceDecimalPlaces),
					$myrow['suppliersuom'],
					locale_number_format($myrow['conversionfactor'],'Variable'),
					locale_number_format($myrow['price']/$myrow['conversionfactor'],$UPriceDecimalPlaces),
					$myrow['currcode'],
					ConvertSQLDate($myrow['effectivefrom']),
					locale_number_format($myrow['minorderqty'],'Variable'),
					locale_number_format($myrow['leadtime'],'Variable'),
					$DisplayPreferred,
					htmlspecialchars($_SERVER['PHP_SELF']),
					$StockID,
					$myrow['supplierno'],
					$myrow['effectivefrom'],
					htmlspecialchars($_SERVER['PHP_SELF']),
					$StockID,
					$myrow['supplierno'],
					$myrow['effectivefrom'],
					htmlspecialchars($_SERVER['PHP_SELF']),
					$StockID,
					$myrow['supplierno'],
					$myrow['effectivefrom']);
        } //end of while loop
        echo '</tbody></table></div></div></div><br/>';
        if ($CountPreferreds > 1) {
            echo prnMsg(_('There are now') . ' ' . $CountPreferreds . ' ' . _('preferred suppliers set up for') . ' ' . $StockID . ' ' . _('you should edit the supplier purchasing data to make only one supplier the preferred supplier'), 'warn');
        } elseif ($CountPreferreds == 0) {
            echo prnMsg(_('There are NO preferred suppliers set up for') . ' ' . $StockID . ' ' . _('you should make one supplier only the preferred supplier'), 'warn');
        }
    } // end of there are purchsing data rows to show
   
} /* Only show the existing purchasing data records if one is not being edited */

if (isset($SupplierID) AND $SupplierID != '' AND !isset($_POST['SearchSupplier'])) {
	/*NOT EDITING AN EXISTING BUT SUPPLIER selected OR ENTERED*/

    $sql = "SELECT suppliers.suppname,
					suppliers.currcode,
					currencies.decimalplaces AS currdecimalplaces
			FROM suppliers
			INNER JOIN currencies
			ON suppliers.currcode=currencies.currabrev
			WHERE supplierid='".$SupplierID."'";
    $ErrMsg = _('The supplier details for the selected supplier could not be retrieved because');
    $DbgMsg = _('The SQL that failed was');
    $SuppSelResult = DB_query($sql, $ErrMsg, $DbgMsg);
    if (DB_num_rows($SuppSelResult) == 1) {
        $myrow = DB_fetch_array($SuppSelResult);
        $SuppName = $myrow['suppname'];
        $CurrCode = $myrow['currcode'];
        $CurrDecimalPlaces = $myrow['currdecimalplaces'];
    } else {
        echo prnMsg(_('The supplier code') . ' ' . $SupplierID . ' ' . _('is not an existing supplier in the system') . '. ' . _('You must enter an alternative supplier code or select a supplier using the search facility below'), 'error');
        unset($SupplierID);
    }
} else {
	if ($NoPurchasingData==0) {
		echo '<div class="block-header"><a href="" class="header-title-link"><h1> ' . ' ' . $Title . ' ' . _('For Stock Code') . ' - ' . $StockID . '</h1></a></div>';
	}
    if (!isset($_POST['SearchSupplier'])) {
        echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '" method="post">
				
					<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
					<input type="hidden" name="StockID" value="' . $StockID . '" />
					<div class="row">
<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Supplier') . ' ' . _('Name-part or full') . '</label>
					<input type="text" class="form-control" name="Keywords" size="20" maxlength="25" /></div></div>
					<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Supplier') . ' ' . _('Code-part or full') . '</label>
					<input type="text" class="form-control" name="SupplierCode" data-type="no-illegal-chars" size="20" maxlength="50" /></div>
				</div>
				<div class="col-xs-4">
<div class="form-group"> <br />
					<input type="submit" class="btn btn-success" name="SearchSupplier" value="' . _('Search') . '" />
				</div>
				</div>
				</div>
			</form>';
        include ('includes/footer.php');
        exit;
    }
}

if ($Edit == true) {
	$ItemResult = DB_query("SELECT description FROM stockmaster WHERE stockid='" . $StockID . "'");
	$DescriptionRow = DB_fetch_array($ItemResult);
	echo '<div class="block-header"><a href="" class="header-title-link"><h1> ' . ' ' . $Title . ' ' . _('For Stock ID:') . '  ' . $StockID . ' - ' . $DescriptionRow['description'] . '</h1></a></div>';
}


if (isset($_POST['SearchSupplier'])) {
    if (isset($_POST['Keywords']) AND isset($_POST['SupplierCode'])) {
        echo prnMsg( _('Supplier Name keywords have been used in preference to the Supplier Code extract entered') . '.', 'info');
        echo '<br />';
    }
    if ($_POST['Keywords'] == '' AND $_POST['SupplierCode'] == '') {
        $_POST['Keywords'] = ' ';
    }
    if (mb_strlen($_POST['Keywords']) > 0) {
        //insert wildcard characters in spaces
		$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';

		$SQL = "SELECT suppliers.supplierid,
						suppliers.suppname,
						suppliers.currcode,
						suppliers.address1,
						suppliers.address2,
						suppliers.address3
				FROM suppliers
				WHERE suppliers.suppname " . LIKE  . " '".$SearchString."'";

    } elseif (mb_strlen($_POST['SupplierCode']) > 0) {
        $SQL = "SELECT suppliers.supplierid,
						suppliers.suppname,
						suppliers.currcode,
						suppliers.address1,
						suppliers.address2,
						suppliers.address3
				FROM suppliers
				WHERE suppliers.supplierid " . LIKE . " '%" . $_POST['SupplierCode'] . "%'";

    } //one of keywords or SupplierCode was more than a zero length string
    $ErrMsg = _('The suppliers matching the criteria entered could not be retrieved because');
    $DbgMsg = _('The SQL to retrieve supplier details that failed was');
    $SuppliersResult = DB_query($SQL, $ErrMsg, $DbgMsg);
} //end of if search

if (isset($SuppliersResult)) {
	if (isset($StockID)) {
        $result = DB_query("SELECT stockmaster.description,
								stockmaster.units,
								stockmaster.mbflag
						FROM stockmaster
						WHERE stockmaster.stockid='".$StockID."'");
		$myrow = DB_fetch_row($result);
		$StockUOM = $myrow[1];
		if (DB_num_rows($result) == 1) {
			if ($myrow[2] == 'D' OR $myrow[2] == 'A' OR $myrow[2] == 'K') {
				echo prnMsg($StockID . ' - ' . $myrow[0] . '<p> ' . _('The item selected is a dummy part or an assembly or kit set part') . ' - ' . _('it is not purchased') . '. ' . _('Entry of purchasing information is therefore inappropriate'), 'warn');
				include ('includes/footer.php');
				exit;
			} else {
 //               echo '<br /><b>' . $StockID . ' - ' . $myrow[0] . ' </b>  (' . _('In Units of') . ' ' . $myrow[1] . ' )';
			}
		} else {
			echo prnMsg(_('Stock Item') . ' - ' . $StockID . ' ' . _('is not defined in the system'), 'warn');
		}
	} else {
		$StockID = '';
		$StockUOM = 'each';
	}
	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="post">
			<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
		<thead>
			<tr>
						<th class="ascending">' . _('Code') . '</th>
	                	<th class="ascending">' . _('Supplier Name') . '</th>
						<th class="ascending">' . _('Currency') . '</th>
						<th class="ascending">' . _('Address 1') . '</th>
						<th class="ascending">' . _('Address 2') . '</th>
						<th class="ascending">' . _('Address 3') . '</th>
			</tr>
		</thead>
		<tbody>';

    while ($myrow = DB_fetch_array($SuppliersResult)) {
		printf('<tr class="striped_row">
				<td><input type="submit" class="btn btn-info" name="SupplierID" value="%s" /></td>
				<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				</tr>',
				$myrow['supplierid'],
				$myrow['suppname'],
				$myrow['currcode'],
				$myrow['address1'],
				$myrow['address2'],
				$myrow['address3']);

        echo '<input type="hidden" name="StockID" value="' . $StockID . '" />';
        echo '<input type="hidden" name="StockUOM" value="' . $StockUOM . '" />';

    }
    //end of while loop
    echo '</tbody>
		</table></div></div></div>
			<br />

			</form>';
}
//end if results to show

/*Show the input form for new supplier purchasing details */
if (!isset($SuppliersResult)) {
	if ($Edit == true OR isset($_GET['Copy'])) {

		 $sql = "SELECT purchdata.supplierno,
						suppliers.suppname,
						purchdata.price,
						purchdata.effectivefrom,
						suppliers.currcode,
						purchdata.suppliersuom,
						purchdata.supplierdescription,
						purchdata.leadtime,
						purchdata.conversionfactor,
						purchdata.suppliers_partno,
						purchdata.minorderqty,
						purchdata.preferred,
						stockmaster.units,
						currencies.decimalplaces AS currdecimalplaces
				FROM purchdata INNER JOIN suppliers
					ON purchdata.supplierno=suppliers.supplierid
				INNER JOIN stockmaster
					ON purchdata.stockid=stockmaster.stockid
				INNER JOIN currencies
					ON suppliers.currcode = currencies.currabrev
				WHERE purchdata.supplierno='" . $SupplierID . "'
				AND purchdata.stockid='" . $StockID . "'
				AND purchdata.effectivefrom='" . $EffectiveFrom . "'";

		$ErrMsg = _('The supplier purchasing details for the selected supplier and item could not be retrieved because');
		$EditResult = DB_query($sql, $ErrMsg);
		$myrow = DB_fetch_array($EditResult);
		$SuppName = $myrow['suppname'];
		$UPriceDecimalPlaces = max($myrow['currdecimalplaces'],$_SESSION['StandardCostDecimalPlaces']);
		if ($Edit == true) {
			$_POST['Price'] = locale_number_format(round($myrow['price'],$UPriceDecimalPlaces),$UPriceDecimalPlaces);
			$_POST['EffectiveFrom'] = ConvertSQLDate($myrow['effectivefrom']);
		} else { // we are copying a blank record effective from today
			$_POST['Price'] = 0;
			$_POST['EffectiveFrom'] = Date($_SESSION['DefaultDateFormat']);
		}
		$CurrCode = $myrow['currcode'];
		$CurrDecimalPlaces = $myrow['currdecimalplaces'];
		$_POST['SuppliersUOM'] = $myrow['suppliersuom'];
		$_POST['SupplierDescription'] = $myrow['supplierdescription'];
		$_POST['LeadTime'] = locale_number_format($myrow['leadtime'],'Variable');

		$_POST['ConversionFactor'] = locale_number_format($myrow['conversionfactor'],'Variable');
		$_POST['Preferred'] = $myrow['preferred'];
		$_POST['MinOrderQty'] = locale_number_format($myrow['minorderqty'],'Variable');
		$_POST['SupplierCode'] = $myrow['suppliers_partno'];
		$StockUOM=$myrow['units'];
    }
    echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" method="post">
		<div class="row">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
    if (!isset($SupplierID)) {
        $SupplierID = '';
    }
	if ($Edit == true) {
        echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Supplier Name') . '</label>
				<input type="hidden" name="SupplierID" value="' . $SupplierID . '" />' . $SupplierID . ' - ' . $SuppName . '<input type="hidden" name="WasEffectiveFrom" value="' . $myrow['effectivefrom'] . '" /></div>
			</div>';
    } else {
        echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Supplier Name') . '</label>
				<input type="hidden" name="SupplierID" maxlength="10" size="11" value="' . $SupplierID . '" />';

		if ($SupplierID!='') {
			echo '' . $SuppName;
		}
		if (!isset($SuppName) OR $SuppName = '') {
			echo '(' . _('A search facility is available below if necessary') . ')';
		} else {
			echo '' . $SuppName;
		}
		echo '</div></div>';
	}
	echo '<input type="hidden" name="StockID" maxlength="10" size="11" value="' . $StockID . '" />';
	if (!isset($CurrCode)) {
		$CurrCode = '';
	}
	if (!isset($_POST['Price'])) {
		$_POST['Price'] = 0;
	}
	if (!isset($_POST['EffectiveFrom'])) {
		$_POST['EffectiveFrom'] = Date($_SESSION['DefaultDateFormat']);
	}
	if (!isset($_POST['SuppliersUOM'])) {
		$_POST['SuppliersUOM'] = '';
	}
	if (!isset($_POST['SupplierDescription'])) {
		$_POST['SupplierDescription'] = '';
	}
	if (!isset($_POST['SupplierCode'])) {
		$_POST['SupplierCode'] = '';
	}
	if (!isset($_POST['MinOrderQty'])) {
		$_POST['MinOrderQty'] = '1';
	}
	echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Currency') . '</label>
			<input type="hidden" name="CurrCode" . value="' . $CurrCode . '" />' . $CurrCode . '</div>
		</div></div>
		<div class="row">
			<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Price') . ' (' . _('in Supplier Currency') . ')</label>
			<input type="text" class="form-control" name="Price" maxlength="12" size="12" value="' . $_POST['Price'] . '" /></div>
		</div>
		<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Price Effective From') . ':</label>
			<input type="text" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="EffectiveFrom" maxlength="10" size="11" value="' . $_POST['EffectiveFrom'] . '" /></div>
		</div>
		<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Our Unit of Measure') . '</label>';

	if (isset($SupplierID)) {
		echo '<td>' . $StockUOM . '</div></div></div>';
	}
	echo '<div class="row">
			<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Suppliers Unit of Measure') . '</label>
			<input type="text" class="form-control" name="SuppliersUOM" size="20" maxlength="20" value ="' . $_POST['SuppliersUOM'] . '"/></div>
		</div>';

	if (!isset($_POST['ConversionFactor']) OR $_POST['ConversionFactor'] == '') {
		$_POST['ConversionFactor'] = 1;
	}

	echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Conversion Factor (to our UOM)') . '</label>
			<input type="text" class="form-control" name="ConversionFactor" maxlength="12" size="12" value="' . $_POST['ConversionFactor'] . '" /></div>
		</div>
		<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Supplier Stock Code') . '</label>
			<input type="text" name="SupplierCode" class="form-control"  maxlength="50" size="20" value="' . $_POST['SupplierCode'] . '" /></div>
		</div></div>
		
		<div class="row">
			<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('MinOrderQty') . '</label>
			<input type="text" class="form-control" name="MinOrderQty" maxlength="15" size="15" value="' . $_POST['MinOrderQty'] . '" /></div>
		</div>
		<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Supplier Stock Description') . '</label>
			<input type="text" class="form-control" name="SupplierDescription" maxlength="50" size="51" value="' . $_POST['SupplierDescription'] . '" /></div>
		</div>';

	if (!isset($_POST['LeadTime']) OR $_POST['LeadTime'] == "") {
		$_POST['LeadTime'] = 1;
	}
	echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Lead Time') . ' (' . _('in days from date of order') . ')</label>
			<input type="text" class="form-control" name="LeadTime" maxlength="4" size="5" value="' . $_POST['LeadTime'] . '" /></div>
		</div></div>
		
		<div class="row">
			<div class="col-xs-4">
<div class="form-group"> <label class="col-md-12 control-label">' . _('Preferred Supplier') . '</label>
			<select name="Preferred" class="form-control">';

	if ($_POST['Preferred'] == 1) {
		echo '<option selected="selected" value="1">' . _('Yes') . '</option>
				<option value="0">' . _('No')  . '</option>';
	} else {
		echo '<option value="1">' . _('Yes')  . '</option>
				<option selected="selected" value="0">' . _('No')  . '</option>';
	}
	echo '</select></div>
		</div>
		</div><br />
';

	if ($Edit == true) {
		/* A supplier purchase price is being edited - also show the discounts applicable to the supplier  for update/deletion*/

		/*List the discount records for this supplier */
		$sql = "SELECT id,
						discountnarrative,
						discountpercent,
						discountamount,
						effectivefrom,
						effectiveto
				FROM supplierdiscounts
				WHERE supplierno = '" . $SupplierID . "'
				AND stockid = '" . $StockID . "'";

		$ErrMsg = _('The supplier discounts could not be retrieved because');
		$DbgMsg = _('The SQL to retrieve supplier discounts for this item that failed was');
		$DiscountsResult = DB_query($sql, $ErrMsg, $DbgMsg);

		echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
			<thead>
				<tr>
					<th class="ascending">' . _('Discount Name') . '</th>
	               	<th class="ascending">' . _('Discount') . '<br />' . _('Value') . '</th>
					<th class="ascending">' . _('Discount') . '<br />' . _('Percent') . '</th>
					<th class="ascending">' . _('Effective From') . '</th>
					<th class="ascending">' . _('Effective To') . '</th>
				</tr>
			</thead>
			<tbody>';

	    $i = 0; //DiscountCounter
	    while ($myrow = DB_fetch_array($DiscountsResult)) {
			printf('<tr class="striped_row">
					<input type="hidden" name="DiscountID%s" value="%s" />
					<td><input type="text" name="DiscountNarrative%s" class="form-control" value="%s" maxlength="20" size="20" /></td>
					<td><input type="text" class="form-control" name="DiscountAmount%s" value="%s" maxlength="10" size="11" /></td>
					<td><input type="text" class="form-control" name="DiscountPercent%s" value="%s" maxlength="5" size="6" /></td>
					<td><input type="text" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="DiscountEffectiveFrom%s" maxlength="10" size="11" value="%s" /></td>
					<td><input type="text" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="DiscountEffectiveTo%s" maxlength="10" size="11" value="%s" /></td>
					<td><a href="%s?DeleteDiscountID=%s&amp;StockID=%s&amp;EffectiveFrom=%s&amp;SupplierID=%s&amp;Edit=1" class="btn btn-danger">' . _('Delete') . '</a></td>
					</tr>',
					$i,
					$myrow['id'],
					$i,
					$myrow['discountnarrative'],
					$i,
					locale_number_format($myrow['discountamount'],$CurrDecimalPlaces),
					$i,
					locale_number_format($myrow['discountpercent']*100,2),
					$i,
					ConvertSQLDate($myrow['effectivefrom']),
					$i,
					ConvertSQLDate($myrow['effectiveto']),
					htmlspecialchars($_SERVER['PHP_SELF']),
					$myrow['id'],
					$StockID,
					$EffectiveFrom,
					$SupplierID);

			$i++;
		}//end of while loop

		echo '</tbody><input type="hidden" name="NumberOfDiscounts" value="' . $i . '" />';

		$DefaultEndDate =  Date($_SESSION['DefaultDateFormat'], mktime(0,0,0,Date('m')+1,0,Date('y')));

	    echo '<tr>
				<td><input type="text" class="form-control" name="DiscountNarrative" value="" maxlength="20" size="20" /></td>
				<td><input type="text" class="form-control" name="DiscountAmount" value="0" maxlength="10" size="11" /></td>
				<td><input type="text" class="form-control" name="DiscountPercent" value="0" maxlength="5" size="6" /></td>
				<td><input type="text" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="DiscountEffectiveFrom" maxlength="10" size="11" value="' . Date($_SESSION['DefaultDateFormat']) . '" /></td>
				<td><input type="text" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="DiscountEffectiveTo" maxlength="10" size="11" value="' . $DefaultEndDate . '" /></td>
			</tr>
			</table></div></div></div>
			<br/>';

		echo '<div class="row">
<div class="col-xs-4"><input type="submit" name="UpdateRecord" class="btn btn-info" value="' . _('Update') . '" /></div></div>';
		echo '<input type="hidden" name="Edit" value="1" />';

		/*end if there is a supplier purchasing price being updated */
	} else {
		echo '<div class="row">
<div class="col-xs-4"><input type="submit" name="AddRecord" class="btn btn-success" value="' . _('Submit') . '" /></div></div>';
	}

	echo '
		<div class="row">';

	if (isset($StockLocation) AND isset($StockID) AND mb_strlen($StockID) != 0) {
		echo '<div class="col-xs-3"><a href="' . $RootPath . '/StockStatus.php?StockID=' . $StockID . '" class="btn btn-info">' . _('Show Stock Status') . '</a></div>';
		echo '<div class="col-xs-3"><a href="' . $RootPath . '/StockMovements.php?StockID=' . $StockID . '&StockLocation=' . $StockLocation . '" class="btn btn-info">' . _('Show Stock Movements') . '</a></div>';
		echo '<div class="col-xs-3"><a href="' . $RootPath . '/SelectSalesOrder.php?SelectedStockItem=' . $StockID . '&StockLocation=' . $StockLocation . '" class="btn btn-info">' . _('Search Outstanding Sales Orders') . '</a></div>';
		echo '<div class="col-xs-3"><a href="' . $RootPath . '/SelectCompletedOrder.php?SelectedStockItem=' . $StockID . '" class="btn btn-info">' . _('Search Completed Sales Orders') . '</a></div>';
	}
	echo '</div><br />
</form>';
}

include ('includes/footer.php');
?>