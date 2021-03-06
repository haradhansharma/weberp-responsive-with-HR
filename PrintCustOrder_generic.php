<?php
include('includes/session.php');
include('includes/SQL_CommonFunctions.inc');

//Get Out if we have no order number to work with
If (!isset($_GET['TransNo']) OR $_GET['TransNo']==''){
	$Title = _('Select Order To Print');
	include('includes/header.php');
	echo prnMsg( _('Select an Order Number to Print before calling this page') , 'error');
	echo '<br />
			<div class="row">
<div class="col-xs-4"><a href="'. $RootPath . '/SelectSalesOrder.php" class="btn btn-info">' . _('Outstanding Sales Orders') . '</a></div>
<div class="col-xs-4"><a href="'. $RootPath . '/SelectCompletedOrder.php" class="btn btn-info">' . _('Completed Sales Orders') . '</a></div>
            </div>
			
			<br />';
	include('includes/footer.php');
	exit;
}

/*retrieve the order details from the database to print */
$ErrMsg = _('There was a problem retrieving the order header details for Order Number') . ' ' . $_GET['TransNo'] . ' ' . _('from the database');

$sql = "SELECT salesorders.debtorno,
    		salesorders.customerref,
			salesorders.comments,
			salesorders.orddate,
			salesorders.deliverto,
			salesorders.deladd1,
			salesorders.deladd2,
			salesorders.deladd3,
			salesorders.deladd4,
			salesorders.deladd5,
			salesorders.deladd6,
			salesorders.deliverblind,
			debtorsmaster.name,
			debtorsmaster.address1,
			debtorsmaster.address2,
			debtorsmaster.address3,
			debtorsmaster.address4,
			debtorsmaster.address5,
			debtorsmaster.address6,
			shippers.shippername,
			salesorders.printedpackingslip,
			salesorders.datepackingslipprinted,
			locations.locationname,
			salesorders.fromstkloc
		FROM salesorders
		INNER JOIN debtorsmaster
			ON salesorders.debtorno=debtorsmaster.debtorno
		INNER JOIN shippers
			ON salesorders.shipvia=shippers.shipper_id
		INNER JOIN locations
			ON salesorders.fromstkloc=locations.loccode
		INNER JOIN locationusers
			ON locationusers.loccode=locations.loccode
			AND locationusers.userid='" .  $_SESSION['UserID'] . "'
			AND locationusers.canview=1
		WHERE salesorders.orderno='" . $_GET['TransNo'] . "'";

if ($_SESSION['SalesmanLogin'] != '') {
	$sql .= " AND salesorders.salesperson='" . $_SESSION['SalesmanLogin'] . "'";
}

$result=DB_query($sql, $ErrMsg);

//If there are no rows, there's a problem.
if (DB_num_rows($result)==0){
	$Title = _('Print Packing Slip Error');
	include('includes/header.php');
	
	echo prnMsg( _('Unable to Locate Order Number') . ' : ' . $_GET['TransNo'] . ' ', 'error');
	echo '<br />
			<div class="row">
<div class="col-xs-6"><a href="'. $RootPath . '/SelectSalesOrder.php" class="btn btn-info">' . _('Outstanding Sales Orders') . '</a></div>
<div class="col-xs-6"><a href="'. $RootPath . '/SelectCompletedOrder.php" class="btn btn-info">' . _('Completed Sales Orders') . '</a></div>
			</div>
			
			<br />';
	include('includes/footer.php');
	exit();
} elseif (DB_num_rows($result)==1){ /*There is only one order header returned - thats good! */

        $myrow = DB_fetch_array($result);
        /* Place the deliver blind variable into a hold variable to used when
        producing the packlist */
        $DeliverBlind = $myrow['deliverblind'];
        if ($myrow['printedpackingslip']==1 AND ($_GET['Reprint']!='OK' OR !isset($_GET['Reprint']))){
                $Title = _('Print Packing Slip Error');
                include('includes/header.php');
                
                echo prnMsg( _('The packing slip for order number') . ' ' . $_GET['TransNo'] . ' ' .
                        _('has previously been printed') . '. ' . _('It was printed on'). ' ' . ConvertSQLDate($myrow['datepackingslipprinted']) .
                        '<br />' . _('This check is there to ensure that duplicate packing slips are not produced and dispatched more than once to the customer'), 'warn' );
              echo '<br /><div class="row" align="center">
               <a href="' . $RootPath. '/PrintCustOrder_generic.php?TransNo=' . urlencode($_GET['TransNo']) . '&Reprint=OK" class="btn btn-warning">' .  _('Do a Re-Print') . ' </a></div>';

                echo '<br /><h4 align="center">';
                echo  '<strong>'._('Or select another Order Number to Print').'</strong>';
                echo '</h4>
				<div class="row">
				<div class="col-xs-3"></div>
				<div class="col-xs-4"><a href="'. $RootPath . '/SelectSalesOrder.php" class="btn btn-info">' . _('Outstanding Sales Orders') . '</a></div>
                <div class="col-xs-4"><a href="'. $RootPath . '/SelectCompletedOrder.php" class="btn btn-info">' . _('Completed Sales Orders') . '</a></div>
                        </div>
                       
                        <br />';

                include('includes/footer.php');
                exit;
        }//packing slip has been printed.
}

/*retrieve the order details from the database to print */

/* Then there's an order to print and it has not been printed already (or its been flagged for reprinting)
LETS GO */

$PaperSize = 'A4_Landscape';
include('includes/PDFStarter.php');

$pdf->addInfo('Title', _('Customer Laser Packing Slip') );
$pdf->addInfo('Subject', _('Laser Packing slip for order') . ' ' . $_GET['TransNo']);
$FontSize=12;
$line_height=24;
$PageNumber = 1;
$Copy = 'Office';

$ListCount = 0;

for ($i=1;$i<=2;$i++){  /*Print it out twice one copy for customer and one for office */
	if ($i==2){
		$PageNumber = 1;
		$pdf->newPage();
	}
	/* Now ... Has the order got any line items still outstanding to be invoiced */
	$ErrMsg = _('There was a problem retrieving the order details for Order Number') . ' ' . $_GET['TransNo'] . ' ' . _('from the database');

	$sql = "SELECT salesorderdetails.stkcode,
					stockmaster.description,
					salesorderdetails.quantity,
					salesorderdetails.qtyinvoiced,
					salesorderdetails.unitprice,
					salesorderdetails.narrative,
					stockmaster.mbflag,
					stockmaster.decimalplaces,
					stockmaster.grossweight,
					stockmaster.volume,
					stockmaster.units,
					stockmaster.controlled,
					stockmaster.serialised,
					pickreqdetails.qtypicked,
					pickreqdetails.detailno,
					custitem.cust_part,
					custitem.cust_description,
					locstock.bin
				FROM salesorderdetails
				INNER JOIN stockmaster
					ON salesorderdetails.stkcode=stockmaster.stockid
				INNER JOIN locstock
					ON stockmaster.stockid = locstock.stockid
				LEFT OUTER JOIN pickreq
					ON pickreq.orderno=salesorderdetails.orderno
					AND pickreq.closed=0
				LEFT OUTER JOIN pickreqdetails
					ON pickreqdetails.prid=pickreq.prid
					AND pickreqdetails.orderlineno=salesorderdetails.orderlineno
				LEFT OUTER JOIN custitem
					ON custitem.debtorno='" . $myrow['debtorno'] . "'
					AND custitem.stockid=salesorderdetails.stkcode
				WHERE locstock.loccode = '" . $myrow['fromstkloc'] . "'
					AND salesorderdetails.orderno='" . $_GET['TransNo'] . "'";
	$result=DB_query($sql, $ErrMsg);

	if (DB_num_rows($result)>0){
		/*Yes there are line items to start the ball rolling with a page header */
		include('includes/PDFOrderPageHeader_generic.inc');

		while ($myrow2=DB_fetch_array($result)){

            $ListCount ++;
			$Volume += $myrow2['quantity'] * $myrow2['volume'];
			$Weight += $myrow2['quantity'] * $myrow2['grossweight'];

			$DisplayQty = locale_number_format($myrow2['quantity'],$myrow2['decimalplaces']);
			$DisplayPrevDel = locale_number_format($myrow2['qtyinvoiced'],$myrow2['decimalplaces']);

			if ($myrow2['qtypicked'] > 0) {
				$DisplayQtySupplied = locale_number_format($myrow2['qtypicked'], $myrow2['decimalplaces']);
			} else {
				$DisplayQtySupplied = locale_number_format($myrow2['quantity'] - $myrow2['qtyinvoiced'],$myrow2['decimalplaces']);
			}

			$LeftOvers = $pdf->addTextWrap($XPos,$YPos,127,$FontSize,$myrow2['stkcode'],'left');
			$LeftOvers = $pdf->addTextWrap(147,$YPos,255,$FontSize,$myrow2['description'],'left');
			$LeftOvers = $pdf->addTextWrap(400,$YPos,85,$FontSize,$DisplayQty,'left');
			$LeftOvers = $pdf->addTextWrap(487,$YPos,85,$FontSize,$myrow2['units'],'left');
			$LeftOvers = $pdf->addTextWrap(527,$YPos,70,$FontSize,$myrow2['bin'],'left');
			$LeftOvers = $pdf->addTextWrap(593,$YPos,85,$FontSize,$DisplayQtySupplied,'left');
			$LeftOvers = $pdf->addTextWrap(692,$YPos,85,$FontSize,$DisplayPrevDel,'left');

			if ($_SESSION['AllowOrderLineItemNarrative'] == 1) {
				// Prints salesorderdetails.narrative:
				$FontSize2 = $FontSize*0.8;// Font size to print salesorderdetails.narrative.
				$Width2 = $Page_Width-$Right_Margin-145;// Width to print salesorderdetails.narrative.
				$LeftOvers = trim($myrow2['narrative']);
				//**********
				$LeftOvers = str_replace('\n', ' ', $LeftOvers);// Replaces line feed character.
				$LeftOvers = str_replace('\r', '', $LeftOvers);// Delete carriage return character
				$LeftOvers = str_replace('\t', '', $LeftOvers);// Delete tabulator character
				//**********
				while (mb_strlen($LeftOvers) > 1) {
					$YPos -= $FontSize2;
					if ($YPos < ($Bottom_Margin)) {// Begins new page.
						include ('includes/PDFOrderPageHeader_generic.inc');
					}
					$LeftOvers = $pdf->addTextWrap(147, $YPos, $Width2, $FontSize2, $LeftOvers);
				}
			}

			if ($YPos-$line_height <= 50){
			/* We reached the end of the page so finish off the page and start a newy */
				$PageNumber++;
				include ('includes/PDFOrderPageHeader_generic.inc');
			} //end if need a new page headed up
			else {
				/*increment a line down for the next line item */
				$YPos -= ($line_height);
			}

			if ($myrow2['cust_part'] > '') {
				$LeftOvers = $pdf->addTextWrap($XPos, $YPos, 127, $FontSize, $myrow2['cust_part'], 'right');
				$LeftOvers = $pdf->addTextWrap(147, $YPos, 255, $FontSize, $myrow2['cust_description']);
				if ($YPos - $line_height <= 50) {
					/* We reached the end of the page so finish off the page and start a newy */
					$PageNumber++;
					include('includes/PDFOrderPageHeader_generic.php');
				} //end if need a new page headed up
				else {
					/*increment a line down for the next line item */
					$YPos -= ($line_height);
				}
			}

			if ($myrow2['mbflag']=='A'){
				/*Then its an assembly item - need to explode into it's components for packing list purposes */
				$sql = "SELECT bom.component,
								bom.quantity,
								stockmaster.description,
								stockmaster.decimalplaces
						FROM bom
						INNER JOIN stockmaster
							ON bom.component=stockmaster.stockid
						WHERE bom.parent='" . $myrow2['stkcode'] . "'
							AND bom.effectiveafter <= CURRENT_DATE
							AND bom.effectiveto > CURRENT_DATE";
				$ErrMsg = _('Could not retrieve the components of the ordered assembly item');
				$AssemblyResult = DB_query($sql,$ErrMsg);
				$LeftOvers = $pdf->addTextWrap($XPos,$YPos,150,$FontSize, _('Assembly Components:-'));
				$YPos -= ($line_height);
				/*Loop around all the components of the assembly and list the quantity supplied */
				while ($ComponentRow=DB_fetch_array($AssemblyResult)){
					$DisplayQtySupplied = locale_number_format($ComponentRow['quantity']*($myrow2['quantity'] - $myrow2['qtyinvoiced']),$ComponentRow['decimalplaces']);
					$LeftOvers = $pdf->addTextWrap($XPos,$YPos,127,$FontSize,$ComponentRow['component']);
					$LeftOvers = $pdf->addTextWrap(147,$YPos,255,$FontSize,$ComponentRow['description']);
					$LeftOvers = $pdf->addTextWrap(503,$YPos,85,$FontSize,$DisplayQtySupplied,'right');
					if ($YPos-$line_height <= 50){
						/* We reached the end of the page so finsih off the page and start a newy */
						$PageNumber++;
						include ('includes/PDFOrderPageHeader_generic.inc');
					} //end if need a new page headed up
					 else{
						/*increment a line down for the next line item */
						$YPos -= ($line_height);
					}
				} //loop around all the components of the assembly
			}

			if ($myrow2['controlled'] == '1') {
				$ControlLabel = _('Lot') . ':';
				if ($myrow2['serialised'] == 1) {
					$ControlLabel = _('Serial') . ':';
				}
				$sersql = "SELECT serialno,
									moveqty
							FROM pickserialdetails
							WHERE pickserialdetails.detailno='" . $myrow2['detailno'] . "'";
				$serresult = DB_query($sersql, $ErrMsg);
				while ($myser = DB_fetch_array($serresult)) {
					$LeftOvers = $pdf->addTextWrap($XPos, $YPos, 127, $FontSize, $ControlLabel, 'right');
					$LeftOvers = $pdf->addTextWrap(147, $YPos, 255, $FontSize, $myser['serialno'], 'left');
					$LeftOvers = $pdf->addTextWrap(147, $YPos, 255, $FontSize, $myser['moveqty'], 'right');
					if ($YPos - $line_height <= 50) {
						/* We reached the end of the page so finsih off the page and start a newy */
						$PageNumber++;
						include('includes/PDFOrderPageHeader_generic.php');
					} //end if need a new page headed up
					else {
						/*increment a line down for the next line item */
						$YPos -= ($line_height);
					}
				} //while loop on myser
			} //controlled
		} //end while there are line items to print out

	} /*end if there are order details to show on the order*/

	if ( $Copy != 'Customer' ) {
		$pdf->SetFont('arsenalb');
		$LeftOvers = $pdf->addTextWrap(375,20,150,$FontSize,'Accepted/Received By:','left');
		$pdf->line(500,20,650,20);
		$LeftOvers = $pdf->addTextWrap(675,20,50,$FontSize,'Date:','left');
		$pdf->line(710,20,785,20);
	}
$pdf->SetFont('arsenalb');
	$LeftOvers = $pdf->addText(17,30,$FontSize,'Volume: ');
	$pdf->SetFont('arsenal');
	$LeftOvers = $pdf->addTextWrap(60,20,100,$FontSize,round($Volume) . ' GA','left');
	$pdf->SetFont('arsenalb');
	$LeftOvers = $pdf->addText(147,30,$FontSize,'Weight: ');
	$pdf->SetFont('arsenal');
	$LeftOvers = $pdf->addTextWrap(197,20,200,$FontSize,round($Weight) . ' LB (approximate)','left');

	$Copy='Customer';
	$Volume = 0;
	$Weight = 0;

} /*end for loop to print the whole lot twice */

if ($ListCount == 0) {
	$Title = _('Print Packing Slip Error');
	include('includes/header.php');
	echo '<div class="row"><p class="text-danger">' .  _('There were no outstanding items on the order to deliver') . '. ' . _('A packing slip cannot be printed').
			'</p><br />
			<div class="col-xs-4"><a href="' . $RootPath . '/SelectSalesOrder.php" class="btn btn-info">' .  _('Print Another Packing Slip/Order').
			'</a></div>
			<div class="col-xs-4"><a href="' . $RootPath . '/menu_data.php?Application=Sales" class="btn btn-default">' . _('<i class="fa fa-hand-o-left fa-fw"></i> To Menu') . '</a></div></div><br />';
	include('includes/footer.php');
	exit;
} else {
    	$pdf->OutputD('nERP' . '_PackingSlip_' . date('Y-m-d') . '.pdf');
    	$pdf->__destruct();
	$sql = "UPDATE salesorders
				SET printedpackingslip=1,
					datepackingslipprinted=CURRENT_DATE
				WHERE salesorders.orderno='" . $_GET['TransNo'] . "'";
	$result = DB_query($sql);
}

?>