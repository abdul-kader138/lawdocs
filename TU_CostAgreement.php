<?php

$path = '';
for ($i=0; $i < count(explode('\\', __DIR__))-4 ; $i++) { 
    $path .= '\\..';
}

// Get document data
// $data contains all of the document's data in the format $data[InputName] = InputValue
require_once __DIR__ . $path . '/controllers/OutputGenerationController.php';

use PhpOffice\PhpWord\PhpWord; 
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\ListItem;

// Create a new PHPWord object
$phpWord = new PhpWord();

$Client = $data[0];
$Lawyer = $data[1];
$WorkDescription01 = $data[2];
$WorkDescription02 = $data[3];
$WorkDescription03 = $data[4];
$WorkDescription04 = $data[5];
$WorkDescription05 = $data[6];
$WorkDescription06 = $data[7];
$WorkDescription07 = $data[8];
$WorkDescription08 = $data[9];
$WorkDescription09 = $data[10];
$WorkDescription10 = $data[11];
$EstimateCompletion = $data[12];
$EstimateCompletionGST = $data[13];
$EstimateFeesExpenses = $data[14];
$EstimateFeesExpensesGST = $data[15];
$EstimatedCompletion = $data[16];
//$TrustMoney = $data[17];
$TrustMoney = $data['TrustMoney'];


$clause08 = "While undertaking the work we will incur expenses on your behalf to others.  These may include:";
$clause09 = "Where we have been unable to provide an estimate of an expense we will do so as soon as it is reasonably practicable.  The expenses that we have estimated may also change and we will advise you of these as soon as is reasonably practicable.";
$clause10 = "The following estimate is based on the information available to us to date. It is an estimate and not a quotation and is subject to change.";
$clause11 = "Where the estimate is a range it is because it is not possible at this time to provide an accurate estimate of the total fees and expenses.  This estimate will change as more information becomes available.";
$clause12 = "The major factors affecting the range of the estimate of fees and expenses are:";
$clause12_1 = "Determining the length of the proceedings;";
$clause12_2 = "The extent of the evidence";
$clause12_3 = "Whether the quantum of damages is contested";
$clause12_4 = "The number of adjournments that are granted";
$clause12_5 = "Whether any interlocutory applications are necessary";
$clause12_6 = "Can the proceedings be moved to a closer court";
$clause13 = "All rates, fees, expenses etc in this agreement are GST exclusive unless stated otherwise.  Where GST is payable it will be added to your tax invoice and charged to you.";
$clause14 = "We can charge you interest at the maximum rate allowed under the Uniform Law.  This is presently the Cash Rate Target set by the Reserve Bank of Australia plus 2 per cent. Interest will be charged on any amount which remains unpaid after the expiry of 30 days from when we give you the tax invoice.";
$clause15 = "We estimate that the work will be completed by 2 September 2024.";
$clause16 = "You have a right to receive a tax invoice from us.  We will send you a tax invoice of our fees and expenses including GST either after completion of the work, or monthly, six monthly or at such other times as agreed with you.";
$clause17 = "If we provide to you a lump sum bill then you are entitled to request from us, within 30 days of receiving our bill, an itemised bill. You have the right to request an itemised account from us within 30 days after a lump sum bill or partially itemised account is payable.";
$clause18 = "It is your right to";
$clause18_1 = "Negotiate this costs agreement with us";
$clause18_2 = "Negotiate the method of billing (task or time based)";
$clause18_3 = "Seek the assistance of the designated local regulatory authority (NSW Commissioner) in the event of a dispute about legal costs;";
$clause18_4 = "Be notified as soon as is reasonably practical of any substantial change to any matter affecting costs;";
$clause18_5 = "Accept or reject any offer we make for an intestate costs law to apply to your matter and";
$clause18_6 = "Notify us that you require an interstate costs law to apply to your matter.";
$clause19 = "The Uniform Law does not permit us to take action to recover our legal costs from you until 30 days after a tax invoice (which complies with the Uniform Law) has been given to you.";
$clause20 = "If you do not agree with our tax invoice you have a right to apply to the Manager, Costs Assessment at the Supreme Court of NSW to have";
$clause20_1 = "our tax Invoice of costs (or any part of it) and if there was no tax invoice, the amount we charged you for the work (or part of it ) assessed for its fairness and reasonableness by a Costs Assessor under the Uniform Law";
$clause20_2 = "any application for assessment must be made within 12 months after either the  tax invoice was provided or we request payment from you or after the costs were paid";
$clause21 = "If you do not agree with our tax invoice you can also apply to the NSW Commissioner to resolve the dispute.";
$clause22 = "You are entitled to request, at reasonable intervals written progress reports on the work.  We are entitled to charge you for these reports.";
$clause23 = "You are entitled to reasonably request a written report on the legal costs incurred to date or since the date of our last tax invoice..";
$clause24 = "We will inform you as soon as reasonably practicable of any substantial change to anything contained in this document.";
$clause25 = "On acceptance of this offer we require you to pay to us the sum of:";
$clause26 = "Any amount that you pay to us will be held in our trust account.  You authorise us to apply these moneys and any other moneys that we might hold on your behalf in our trust account (eg proceeds of a sale or settlement) to:";
$clause26_1 = "pay any tax invoice we have sent to you (whether for the work the subject of this agreement or any other work you have requested we do);";
$clause26_2 = "pay any expense that we incur on your behalf while undertaking the work.";
$clause27 = "You authorise us to receive on your behalf any judgment or settlement moneys and to deal with them in accordance with the preceding paragraph.";
$clause28 = "If the work involves court proceedings the court may order the other party to pay your costs of the proceedings.  This sum will not necessarily be enough to reimburse you for the legal fees and expenses we have rendered to you.  If you do obtain such an order you will incur additional costs enforcing it.  It may not be possible to recover the costs if the other party is insolvent.  You will however still be liable to pay our costs of attempting to enforce the order.";
$clause29 = "It is also possible that the court will make an order that you pay the other party's costs (if, for instance, you lose the case).  If such an order is made you will be required to pay these costs in addition to our fees and expenses. You have the right to have these costs assessed but you will be liable to pay our costs of acting for you in any costs assessment and you might also have to pay the other parties costs of the cost assessment.";
$clause30 = "We may engage, on your behalf, the services of another legal practitioner to act as our agent.  We will advise you of the terms of that legal practitioner's engagement and disclose their fees and expenses once they have disclosed them to us.  You may be required to enter into a costs agreement directly with that legal practitioner.";
$clause31 = "On completion of the work we will retain for no more than seven years any documents which are in our possession (except those documents deposited with us in safe custody) which you are entitled to. You authorise us to destroy these documents after seven years have passed since the date of our final tax invoice to you.  You also authorise us to retain these documents by copying them into an electronic format which can be viewed and printed.  Where we have done this we are entitled to destroy the original documents which were copied.";
$clause32 = "Where you owe us any money we are entitled to retain any documents that we hold on your behalf until all moneys are paid.";
$clause33 = "Where the person referred to in this agreement as 'you', 'your' is a company then the person who signs this agreement on behalf of the company:";
$clause33_1 = "warrants that they are authorised to do so by the company;";
$clause33_2 = "agrees that they:";
$clause33_2_1 = "are personally liable for any moneys that the company owes us under this agreement (including any moneys we incur in recovering those moneys from the company or the person signing on behalf of the company);";
$clause33_2_2 = "will indemnify us and keep us indemnified for any loss that we incur as a result of entering this agreement with the company;";
$clause33_2_3 = "where, for whatever reason the company is held not to owe us some or all of the moneys payable under this agreement then the person signing this agreement will still be personally liable for those moneys.";
$clause34 = "This agreement is entered into on the basis that we have discussed with you where relevant to the work whether you are eligible for Legal Aid and you understand that:";
$clause34_1 = "Legal Aid is not available in respect of the work; or";
$clause34_2 = "you are not eligible for legal aid; or";
$clause34_3 = "you have declined to make an application for Legal Aid.";
$clause35 = "If you subsequently obtain Legal Aid we can terminate this agreement.";
$clause36 = "We can suspend doing any work for you where you:";
$clause36_1 = "fail to pay any moneys that we have invoiced/requested, including money on account of fees and expenses;";
$clause36_2 = "fail to provide us with adequate instructions;";
$clause36_3 = "indicate that you have lost confidence in us;";
$clause36_4 = "indicate you cannot or will not pay our fees and expenses as required under this agreement;";
$clause36_5 = "you refuse to accept our advice;";
$clause36_6 = "if there are any ethical grounds which we consider require us to cease acting for you e.g. conflict of interest;";
$clause36_7 = "in our sole discretion we consider it no longer appropriate to act for you or";
$clause36_8 = "for just cause;";
$clause37 = "Where we have suspended to do work for you we will write to you advising the reason for that suspension.  We will not be liable for any loss you suffer as a consequence of our suspending our services.";
$clause38 = "We can terminate this agreement for the same reasons that we can suspend this agreement.  Where we are going to terminate this agreement we will give you fourteen (14) days' notice that we will terminate this agreement and the agreement will terminate on the fourteenth day after the date of our notice.  We will advise you in the notice of our reasons for termination of this agreement.";
$clause39 = "You can terminate this agreement at any time by notice in writing to us to that effect or we can at our option accept your verbal notification of termination.";
$clause40 = "Termination of this agreement does not affect your liability for our fees and expenses (whether or not tax invoiced at the time) to the close of business on the day on which we give or receive notification of termination.";

/// consider doing the caluse 31 and 32 as links
$clause41 = "The obligations in clauses 31 and 32 are not affected by termination of this agreement and continue.";
$clause42 = "The Law of New South Wales applies to legal costs regarding this matter.  If this matter has a substantial connection with the law of any other State or Territory you may wish to have the matter dealt with by the law of that State or Territory.  If you do so, as an incorporated legal practice separate disclosure requirements are imposed on us and we will disclose our costs in accordance with those requirements.  You may, however, contract with us that the costs assessment scheme in NSW is applicable in the event of any dispute arising as to costs.";
$clause43 = "Where you send us an email or give us your email address, we will assume that you agree to us sending you an email.  You acknowledge that email communications are not secure and you accept the security risk of communicating by email.  It is your responsibility to scan any email or other document that you send to us for computer viruses and to similarly scan any email or document we send to you. ";
$clause44 = "Unless you receive a receipt from us you cannot rely on us actually having received your email.  If you have not heard from us in response to your email or if your communication is urgent, you should advise us by telephone call that you have sent an email.";
$clause45 = "Where any clause in this agreement is found to be invalid or void or otherwise unenforceable then this agreement is to be read as if that clause had never formed part of the agreement and each other clause remains binding on the parties.";












$section = $phpWord->addSection();

$hangingparagraphStyle = array(
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph
    'indentation' => array(
        'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3),
        'hanging' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3),
    ),
    'lineHeight' => 1
);

$leftparagraphStyle = array(
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph
    'indentation' => array(
        'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0),
    ),
    'lineHeight' => 1
);

$centerparagraphStyle = array(
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph   
    'lineHeight' => 1,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER // Center the text horizontally
);

$rightparagraphStyle = array(
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph   
    'lineHeight' => 1,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT // Right the text horizontally
);

$leftinsetparagraphStyle = array(
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph
    'indentation' => array(
        'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
    ),
    'lineHeight' => 1
);


// Define the list style
$listStyle = array(
    'lineHeight' => 1, // Single line spacing
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph
    'listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED // Defines a filled bullet list
);

// Define the paragraph style for the list items
$paragraphStyle = array(
    'lineHeight' => 1, // Single line spacing
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph
    'indentation' => array(
        'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), 
        'hanging' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5) 
    )
);


// Add numbered list style
$multilevelNumberingStyleName = 'multilevel';
$phpWord->addNumberingStyle(
    $multilevelNumberingStyleName,
    [
       'type' => 'multilevel',
       'levels' => [
             [
                'format' => 'decimal',
                'text' => '%1.', 
                'font' => 'Times New Roman',
                'sz' => 22, // 11 points (22 half-points)
                'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
                'hanging' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
                'tabPos' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5),
                'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11),
                'lineHeight' => 1
             ],
             [
                'format' => 'decimal', 
                'text' => '%1.%2', 
                'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3), 
                'hanging' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), 
                'tabPos' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3), 
                'font' => 'Times New Roman',
                'sz' => 22, // 11 points
                'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), 
                'lineHeight' => 1
             ],
             [
                'format' => 'lowerLetter', 
                'text' => '(%3)', 
                'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(4.5), 
                'hanging' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), 
                'tabPos' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(4.5), 
                'font' => 'Times New Roman',
                'sz' => 22, // 11 points
                'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), 
                'lineHeight' => 1
             ],
             [
                'format' => 'lowerRoman', 
                'text' => '(%4)', 
                'left' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(6), 
                'hanging' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), 
                'tabPos' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(6), 
                'font' => 'Times New Roman',
                'sz' => 22, // 11 points
                'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), 
                'lineHeight' => 1
             ],
          ],
    ]
 );



$section->addText(
    'COSTS AGREEMENT',
    array('name' => 'Times New Roman', 'size' => 11, 'bold' => true), // Font style array
    array(
        'alignment' => 'center', // Paragraph alignment
        'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pointToTwip(11), // 11 points of spacing after the paragraph
        'lineHeight' => 1
    )
);

// Add a TextRun to the section
$textRun001 = $section->addTextRun($hangingparagraphStyle);

// Add text elements with different styles
$textRun001->addText('BETWEEN', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun001->addText("\t"); // Add a tab character (may not align without tab stops defined)
$textRun001->addText('MCPHEE KELSHAW PTY LTD', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);
$textRun001->addText(' trading under registered business name McPhee Kelshaw (McPhee Kelshaw)', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

// client
$textRun002 = $section->addTextRun($hangingparagraphStyle);
$textRun002->addText('AND', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun002->addText("\t"); // Add a tab character (may not align without tab stops defined)
$textRun002->addText($data['Client'], ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);
$textRun002->addText(' ("you", "your")', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

// date
$currentDate = date ('j F Y');

$textRun003 = $section->addTextRun($hangingparagraphStyle);
$textRun003->addText('DATED', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun003->addText("\t"); // Add a tab character (may not align without tab stops defined)
$textRun003->addText($currentDate, ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

// offer
$textRun004 = $section->addTextRun($leftparagraphStyle);
$textRun004->addText('This document is an offer to provide legal ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun004->addText('services to  you and constitutes our costs agreement and disclosure ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun004->addText('under the Legal Professional Uniform Law (NSW) ("the Uniform Law").', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

$textRun005 = $section->addTextRun($leftparagraphStyle);
$textRun005->addText('You are entitled to obtain independent legal advice before entering into this agreement.', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

$textRun006 = $section->addTextRun($leftparagraphStyle);
$textRun006->addText('You can negotiate the terms of this agreement with us or accept it as it is.', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

$textRun007 = $section->addTextRun($leftparagraphStyle);
$textRun007->addText('You can accept this agreement by:', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

// Add bulleted list
$section->addListItem('Signing this agreement and returning it to us;', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $paragraphStyle);
$section->addListItem('Communicating to us either verbally or in writing that you accept this offer;', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $paragraphStyle);
$section->addListItem('Requesting us to undertake any of the work.', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $paragraphStyle);

$textRun008 = $section->addTextRun($leftparagraphStyle);
$textRun008->addText('If you do not accept this agreement within seven days then we can withdraw our offer and ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun008->addText('charge you for the work done to the date we withdraw that offer. That work will be charged ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun008->addText('on the same basis as set out in this agreement and disclosure.', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

$textRun009 = $section->addTextRun($leftparagraphStyle);
$textRun009->addText('We can refuse to commence work until a copy of this offer is signed by you and returned to us.', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);


//$Col1Width = Converter::cmToTwip(3.5);  // 3.5 cm for the first column
//$Col2Width = Converter::cmToTwip(12.5); // 12.5 cm for the second column

//$Col2AWidth = \PhpOffice\PhpWord\Shared\Converter::cmToTwip(9);
//$Col2BWidth = \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3);

$Col1Width = Converter::cmToTwip(3.5);  // 3.5 cm for the first column
$Col2Width = Converter::cmToTwip(9.5); // 12.5 cm for the second column
$Col3Width = \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3);


$table = $section->addTable();
$table->addRow();
$table->addCell($Col1Width)->addText("The Work", ['name' => 'Times New Roman', 'size' => 11, 'bold' => true], $leftparagraphStyle);

// Add the second cell
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText('The work you require us to do is:', ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

if (!empty($data['WorkDescription01'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription01'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription02'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription02'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription03'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription03'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription04'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription04'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription05'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription05'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription06'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription06'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription07'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription07'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription08'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription08'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription09'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription09'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

if (!empty($data['WorkDescription10'])) {
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText($data['WorkDescription10'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
}

// arrange lawyer
if ($data['Lawyer'] = "PMM") {
    $Lawyer = "Paul McPhee";
} elseif ($data['Lawyer'] == "TC") {
    $Lawyer = "Trevor Cork";
} elseif ($data['Lawyer'] == "SJN") {
    $Lawyer = "Steven Nicholson";
} elseif ($data['Lawyer'] == "AGD") {
    $Lawyer = "Ashley Dewell";
} elseif ($data['Lawyer'] == "JC") {
    $Lawyer = "Justine Cole";
} else {
    $Lawyer = "Susan Wild";
}

// continue adding to the list
$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText('The person responsible for undertaking the work will be ', ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText($Lawyer, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText('.   You may contact him regarding your matter and your legal costs.', ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);


// our fees cell 1
$table->addRow();
$table->addCell($Col1Width)->addText("Our Fees", ['name' => 'Times New Roman', 'size' => 11, 'bold' => true], $leftparagraphStyle);

// Add the second cell
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns

$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText('We will charge you as follows:', ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// heading - our fees
$textRun = $cell->addTextRun($leftinsetparagraphStyle);
$textRun->addText('Time spent by a solicitor on your matter ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun->addText('(including but not limited to time spent in ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun->addText('conference, obtaining instructions, advising, ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun->addText('negotiating, and, if necessary, travelling to ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun->addText('and from court, appearing in court, waiting time at ', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);
$textRun->addText('court, making and receiving telephone calls):', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

// row
$table->addRow();
$table->addCell($Col1Width);
$table->addCell($Col2Width)->addText("(per hour)", ['name' => 'Times New Roman', 'size' => 9, 'bold' => false, 'italic' => true], $centerparagraphStyle);
$table->addCell($Col3Width)->addText("(inclusive of GST)", ['name' => 'Times New Roman', 'size' => 9, 'bold' => true], $centerparagraphStyle);


// 3.1 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem005 = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem005->addText('at the rate of $500 per hour + GST for a principal, director, specialist;', ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun013 = $cell->addTextRun($centerparagraphStyle);
    $textRun013->addText('$550.00', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.2 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem005 = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem005->addText('at the rate of $500 per hour + GST for a senior solicitor (over 7 years since admission);', ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun013 = $cell->addTextRun($centerparagraphStyle);
    $textRun013->addText('$550.00', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);


// 3.3 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem005 = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem005->addText("at the rate of $375 per hour + GST for a solicitor (2-7 years' admission);", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun013 = $cell->addTextRun($centerparagraphStyle);
    $textRun013->addText('$412.50', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.4 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("at the rate of $335 per hour + GST for a junior solicitor (less than 2 years admission);", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$368.50', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.6 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("at the rate of $245 per hour + GST for a paralegal/secretary;", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$269.50', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.7 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("PER FOLIO (100 words) for drafting, typing and finalising letters, emails and documents (including Court documents), unless charged on a time basis $45 per folio +GST.", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$49.50', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

    
// blank row
$table->addRow();
$table->addCell($Col1Width);
$table->addCell($Col2Width);

// dibursements row
$table->addRow();
    // Cell 1  
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $textRun = $cell->addTextRun($leftinsetparagraphStyle);
    $textRun->addText("Disbursements", ['name' => 'Times New Roman', 'size' => 11, 'italic' => true]);
//xxxzzz

// 3.7 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("at the rate of $0.50+GST for photocopying (if not charged at paralegal hourly rate);", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('(per page)', ['name' => 'Times New Roman', 'size' => 9, 'bold' => false]);
    $textRun->addTextBreak();
    $textRun->addText('$0.55', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.8 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("at the rate of $0.50+GST for each scan (if not charged at paralegal hourly rate);", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('(per page)', ['name' => 'Times New Roman', 'size' => 9, 'bold' => false]);
    $textRun->addTextBreak();
    $textRun->addText('$0.55', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.9 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("at the rate of $0.50+GST for printing (if not charged at paralegal hourly rate);", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('(per page)', ['name' => 'Times New Roman', 'size' => 9, 'bold' => false]);
    $textRun->addTextBreak();
    $textRun->addText('$0.55', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.10 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("for each document filed online $23+GST", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$25.30', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.11 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("file establishment and maintenance fee of $79+GST", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$86.90', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.12 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("file retrieval fee - for each request to retrieve your completed file from our storage facility $19+GST", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$20.90', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.13 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("EFT transfer fee per transfer $23+GST", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$25.30', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// 3.14 row
    $table->addRow();
    // Cell 1
    $table->addCell($Col1Width);
    // Cell 2a
    $cell = $table->addCell($Col2Width);
    $listItem = $cell->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
    $listItem->addText("Opening a controlled money account $85+GST", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
    // Cell 2b
    $cell = $table->addCell($Col3Width);
    $textRun = $cell->addTextRun($centerparagraphStyle);
    $textRun->addText('$93.50', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true]);

// blank row
$table->addRow();
$table->addCell($Col1Width);
$table->addCell($Col2Width);
$table->addCell($Col3Width);

// 4 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("We will charge in six minute units.  This means the time ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("charged for an attendance up to six minutes is six minutes ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("and the time charged for an attendance between six and twelve ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("minutes is twelve minutes.  We can increase our hourly rates ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("and charges by more than the CPI but only where we give you ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("thirty days' notice in writing of that increase.", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 5 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Our hourly rates will increase as and from 1 July in accordance ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("with the increase in the consumer price index (all groups index) ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("Sydney ('CPI') for that financial year. We can otherwise increase ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("our hourly rates and charges after 30 days from our giving you ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("written notice advising you of the increases.", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 6 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Where we have commenced the work prior to the date of this ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("agreement then this agreement will apply to that work as if ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("the work was done after this agreement and unless we indicate ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("otherwise our estimate of costs and expenses will include the ", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem->addText("value of that work.", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 7 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("We estimate our fees for completing the work to be:", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// new row
$table->addRow();
$table->addCell($Col1Width);
$cell = $table->addCell($Col2Width, array('borderSize' => 6, 'borderColor' => '000000'));
$cell->getStyle()->setGridSpan(2); // Span across 2 columns

$EstimateCompletion = null;
$EstimateCompletion = $data['EstimateCompletion'];
if (!empty($data['EstimateCompletionGST'])) {
    $EstimateCompletion = $EstimateCompletion." (inc. GST)";
}

// Define the font style
$fontStyle = array('name' => 'Times New Roman', 'size' => 11);

// Add the text with the defined style
$cell->addText($EstimateCompletion, $fontStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// new row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$cell->addText("The preceding amount is approximate.", ['name' => 'Times New Roman', 'size' => 11, 'italic' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// 8 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Expenses we incur on your behalf", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause08, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);


// 8.1 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2a
$cell2 = $table->addCell($Col2Width);
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Photocopying", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 8.2 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2a
$cell2 = $table->addCell($Col2Width);
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Searches", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 8.3 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2a
$cell2 = $table->addCell($Col2Width);
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Counsel's fees", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 8.4 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2a
$cell2 = $table->addCell($Col2Width);
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Travel and parking", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 8.5 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2a
$cell2 = $table->addCell($Col2Width);
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Agens Fee and Mentions etc", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 8.6 row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2a
$cell2 = $table->addCell($Col2Width);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("File maintenance fee", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2b
$cell3 = $table->addCell($Col3Width);
$cell3->addText("$86.90", $fontStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// new row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$cell->addText("The preceding amounts are approximate.", ['name' => 'Times New Roman', 'size' => 11, 'italic' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// 9 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause09, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 10 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Estimate of fees and Expenses", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause10, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// new row
$table->addRow();
$table->addCell($Col1Width);
$cell = $table->addCell($Col2Width, array('borderSize' => 6, 'borderColor' => '000000'));
$cell->getStyle()->setGridSpan(2); // Span across 2 columns

$EstimateFees = null;
$EstimateFees = $data['EstimateFeesExpenses'];
if (!empty($data['EstimateFeesExpensesGST'])) {
    $EstimateFees = $EstimateFees." (inc. GST)";
}

// Add the text with the defined style
$cell->addText($EstimateFeesExpenses, $fontStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// new row
$table->addRow();
// Cell 1
$table->addCell($Col1Width);
// Cell 2
$cell = $table->addCell($Col2Width);
$cell->getStyle()->setGridSpan(2); // Span across 2 columns
$cell->addText("The preceding amount is approximate.", ['name' => 'Times New Roman', 'size' => 11, 'italic' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// 11 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Expenses we incur on your behalf", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause11, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 12 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause12, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Determining the length of the proceedings;", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("The extent of the evidence;", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Whether the quantum of damages is contested;", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("The number of adjournments that are granted;", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Whether any interlocutory applications are necessary;", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText("Can the proceedings be moved to a closer court.", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 13 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("GST", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause13, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 14 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Interest", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause14, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 15 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Estimate of Completion of the Work", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause15, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 16 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Billing Arangements", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause16, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 17 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Itemised Bills", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause17, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 18 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Dispute as to legal costs", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18_3, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18_4, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18_5, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause18_6, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 19 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause19, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 20 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause20, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause20_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause20_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 21 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause29, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 22 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Progress Reports", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause22, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 23 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause23, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 24 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Substantial Change of Circumstances", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause24, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 25 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause25, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 26 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Payment of moneys on bill of charge and expenses to be incurred", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width, array('borderSize' => 6, 'borderColor' => '000000')); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns

// Set border style for the cell
$cellStyle = $cell2->getStyle();
$cellStyle->setBorderSize(6);
$cellStyle->setBorderColor('000000');

// Add text to the cell
$cell2->addText($TrustMoney, $fontStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

// 26 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause26, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause26_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause26_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 27 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Authority to receive and trasnfer moneys", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause27, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 28 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Costs in Court Proceedings", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause28, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 29 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause29, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 30 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Engagement of another legal practitioner", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause30, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 31 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Retention of documents", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause31, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 32 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause32, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 33 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Company's representative signing this agremenet is personally liable for the money woed by the company to us", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause33, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause33_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause33_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(2, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause33_2_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(2, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause33_2_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(2, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause33_2_3, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 34 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Legal Aid", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause34, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause34_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause34_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause34_3, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 35 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause35, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 36 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Suspension of work and termination of this agreement", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_1, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_2, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_3, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_4, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_5, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_6, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_7, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$listItem = $cell2->addListItemRun(1, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause36_8, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 37 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause37, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 38 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause38, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 39 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause39, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 40 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause40, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 41row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause41, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 42 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Applicable Law", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause42, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 43 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Email", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause43, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 44 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause44, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 45 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Interpretation and Severance", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$listItem = $cell2->addListItemRun(0, $multilevelNumberingStyleName, $listStyle);
$listItem->addText($clause45, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

// 46 row
$row = $table->addRow();
// Cell 1
$cell1 = $row->addCell($Col1Width); // Assign the first cell to the current row
$cell1->addText("Signing", ['bold' => true, 'name' => 'Times New Roman', 'size' => 11]);
// Cell 2
$cell2 = $row->addCell($Col2Width); // Assign the second cell to the current row
$cell2->getStyle()->setGridSpan(2); // Span across 2 columns
$cell2->addText("Signed for and on behalf of McPhee Kelshaw", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("..................................................\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("Date:\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("Signed for and on behalf of ".$data['Client'], ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
//$cell2->addText(, ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("..................................................\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);
$cell2->addText("Date:\n", ['bold' => false, 'name' => 'Times New Roman', 'size' => 11]);

$section->addPageBreak();

// Add a TextRun to the section
$textRun = $section->addTextRun($centerparagraphStyle);
$textRun->addText('FORM OF DISCLOSURE OF COSTS TO CLIENTS', ['name' => 'Times New Roman', 'size' => 11, 'bold' => false, 'underline' => 'single']);

$textRun = $section->addTextRun($centerparagraphStyle);
$textRun->addText("FORM 2 – CLAUSE 109A OF LEGAL PROFESSION REGULATION 2005", ['name' => 'Times New Roman', 'size' => 11, 'bold' => false]);

$textRun = $section->addTextRun($leftparagraphStyle);
$textRun->addText("\n", ['name' => 'Times New Roman', 'size' => 11, 'bold' => false], $leftparagraphStyle);

$textRun = $section->addTextRun($leftparagraphStyle);
$textRun->addText("Legal Costs – Your Right to Know\n", ['name' => 'Times New Roman', 'size' => 11, 'bold' => false], $leftparagraphStyle);

$textRun = $section->addTextRun($leftparagraphStyle);
$textRun->addText("You have the right to:\n", ['name' => 'Times New Roman', 'size' => 11, 'bold' => false], $leftparagraphStyle);

// Add bulleted list
$section->addListItem('Negotiate a costs agreement with us', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);
$section->addListItem('Receive a bill of costs from us', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);
$section->addListItem('Request an itemised bill of costs after you receive a lump sum bill from us', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);
$section->addListItem('Request written reports about the progress of your matter and the costs incurred in your matter', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);
$section->addListItem('Apply for costs to be assessed within 12 months if you are unhappy with our costs', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);
$section->addListItem('Apply for costs agreement to be set aside', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);
$section->addListItem('Accept or reject any offer we make for interstate costs law to apply to your matter', 0, array('name' => 'Times New Roman', 'size' => 11), $listStyle, $leftinsetparagraphStyle);

$textRun = $section->addTextRun($leftparagraphStyle);
$textRun->addText("For more information about your rights please read the facts sheet titled Legal Costs- your right to know.  You can ask us for a copy or obtain it from your local Law Society or law Institute (or down load from their website).", ['name' => 'Times New Roman', 'size' => 11, 'bold' => false], $leftparagraphStyle);


?>