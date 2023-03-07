<?php
##JOSEPH MADE THIS ON 01/26/2023
##EDITED: 2/14/2023
error_reporting(E_ALL);
## USE ALL NAMESPACES REQUIRED FOR PDF ROTATION
    use setasign\Fpdi;
    use CzProject\PdfRotate\PdfRotate;

function pdfCreator() {

    ## REQUIRE ALL LIBRARIES NEEDED FOR PDF ROTATION (incl. PdfRotate, Fpdi, fpdf, Tcpdf):
        require_once('fpdi/fpdi/src/autoload.php');
        require_once('fpdi/fpdi/src/FpdiTrait.php');
        require_once('tcpdf-main/tcpdf.php');
        require_once('fpdi/fpdi/src/Tcpdf/Fpdi.php');
        require_once('pdf-rotate/src/PdfRotate.php');
        require_once('FPDF/fpdf.php');
    

    ## CHECK IF LOGGED IN
    $current_user = wp_get_current_user();
    $current_user_email = $current_user->user_email;
    $current_user_name = $current_user->user_login;  
    # IF NOT LOGGED IN DISPLAY LOG IN FORM      
    if (!strlen($current_user_email)>0) {
        echo "  <p>You are not logged in.</p>
                <p> You must be logged in to see this page.</p>
                <form><button type='submit' formaction='/login'>Login</button></form>
                    <p></p><p> or </>
                <form><button type='submit' formaction='/register'>Register</button></form>
            ";
        return False;
    }

    ## GET WWR_ID, OTHER IMPORTANT VARIABLES AND POST/GET DATA
        if(isset($_GET["wwr_id"])){
            $wwr_id = $_GET["wwr_id"];
        }
        else{
            echo "
            <b>Alert: No WWR ID Provided</b>
            <br>
            ";
            return False;
        }        
        $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);  
        $current_user = wp_get_current_user();
	    $now = time();  
        $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);   

        # MAKE SURE DATABASE CONNECTION SUCCESFUL
        if (mysqli_connect_errno()) {
            echo "<p><b>Failed to connect to the database.</b> Please contact WWR to notify us of this issue.</p>";
            return False;
        }

    ## TRACK WHO CAME HERE
    if(isset($wwr_id)) {
        mysqli_query($connection, "insert into 1wwr_usage_log   (WWR_ID
                                                                , User_Name
                                                                , User_Email 
                                                                , Page_Accessed) 
                                                        values ('$wwr_id'
                                                                , '$current_user_name'
                                                                , '$current_user_email' 
                                                                , '" . get_the_title() . "'
                                                                );
                                                                ");
    } 

    ## QUERY DATABASE TO FIND INFORMATION ABOUT SUBJECT/INVESTIGATION AND WHO LAST SAVED THE CURRENT REPORT
        $sql_statement = "SELECT subject_first_name
                                , subject_last_name
                                , 1wwr_investigation.file_number
                                , SUBSTRING_INDEX(1wwr_files.who_uploaded,'@',1) as User_Email
                                , when_uploaded as When_Submitted
                            FROM 1wwr_investigation
                            LEFT JOIN 1wwr_files
                            ON 1wwr_investigation.wwr_id = 1wwr_files.wwr_id
                            WHERE 1wwr_files.latest_record = 0 
                                AND 1wwr_investigation.latest_record = 0 
                                AND 1wwr_investigation.wwr_id = '$wwr_id' 
                                AND 1wwr_files.short_file_name = 'Current_Report.pdf'";

        ## RUN QUERY AND EXTRACT RETURNED FIELDS INTO VARIABLES
        $result = mysqli_query($connection, $sql_statement);
        while($row = mysqli_fetch_assoc($result)) {
            $first_name = $row["subject_first_name"];
            $last_name = $row["subject_last_name"];
            $subjectFileNumber = $row["file_number"];
            $file_number = $row["file_number"];            
            $whoLastSaved = $row["User_Email"];
            $whenLastSaved = $row["When_Submitted"];
        }
    
    ## UPLOAD FILES (IF REQUESTED)
        #CREATE ARRAY TO (TEMPORARILY) STORE UPLOADED FILES FOR LATER IN OUR PROCESS (~ LINE 110)
        $uploadedFiles = array();
        for ($i=1;$i<6;$i++){
            #CHECK TO SEE IF FILE WAS UPLOADED:
            if (isset($_FILES["fileupload$i"]['name']) && !empty($_FILES["fileupload$i"]['name'])){
                # MOVE UPLOADED FILE INTO UPLOADS DIRECTORY (NOTE, WE WILL DELETE THEM LATER ON). NOTE, I HAVE TO FIX THIS LINE LATER.
                # I STILL CAN'T FIGURE OUT WHY I DID THIS, BUT THERE MUST BE SOME REASON. I THINK ITS BECAUSE WHEN I GET RID OF SPECIAL CHARACTERS IT GETS RID OF PERIODS.
                $uploadFileName =  $_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/wwr-cu/TEMP_$wwr_id" . "_" . str_replace('tif', '', str_replace('tiff', '', str_replace('TIF', '', str_replace('TIFF', '', str_replace('PNG', '', str_replace('png','',str_replace('JPG', '', str_replace('PDF', '', str_replace('jpg','', str_replace('pdf', '', clean($_FILES["fileupload$i"]["name"])))))))))));
                ##CHECK IF PDF
                if(strtolower(pathinfo($_FILES["fileupload$i"]['name'], PATHINFO_EXTENSION)) == "pdf"){
                    $uploadFileName .= '.pdf';
                }
                #CHECK IF JPG
                elseif(strtolower(pathinfo($_FILES["fileupload$i"]['name'], PATHINFO_EXTENSION)) == "jpg"){
                    $uploadFileName .= '.jpg';
                }
                #CHECK IF PDF
                elseif(strtolower(pathinfo($_FILES["fileupload$i"]['name'], PATHINFO_EXTENSION)) == "png"){
                    $uploadFileName .= '.png';
                }
                #CHECK IF TIF/TIFF
                elseif(in_array(strtolower(pathinfo($_FILES["fileupload$i"]['name'], PATHINFO_EXTENSION)), array("tiff", "tif"))){
                    $uploadFileName .= ".tiff";
                }
                # SEE WHAT ELSE IT COULD BE
                else{
                    echo pathinfo($_FILES["fileupload$i"]['name'], PATHINFO_EXTENSION);
                }
                #MAKE SURE UPLOAD SUCCESSFUL
                if (move_uploaded_file($_FILES["fileupload$i"]["tmp_name"], $uploadFileName)) {
                    #ADD UPLOADED FILE TO UPLOADED FILE ARRAY
                    $ready = False;
                    if(pathinfo($uploadFileName, PATHINFO_EXTENSION) == "pdf"){
                        $ready= True;                        
                    }
                    elseif(in_array(pathinfo($uploadFileName, PATHINFO_EXTENSION), array("jpg","png"))){
                        ## TURN JPGS/PNGS INTO PDFS!
                        $convertedUploadName = str_replace("png","pdf", str_replace("jpg","pdf",$uploadFileName));
                        $gravity = "-gravity center";
                        ## THE PROBLEM I AM SOLVING HERE IS THAT SOMETIMES UPLOADED IMAGES ARE REALLY BIG (PIXEL WIZE)
                        ## IN THIS CASE, WE DON'T WANT THE GRAVITY TO BE SET TO CENTER. 
                        if(getimagesize($uploadFileName)[0]>800 || getimagesize($uploadFileName)[1] >800 ){
                            ## THIS COMMENTED CODE IS PROBABLY UNECESSARY, AND WE CAN GET RID OF IT LATER:
                            // $width = getimagesize($uploadFileName)[0];
                            // $height = getimagesize($uploadFileName)[1];
                            // $resizeName = str_replace("TEMP","TEMP0",$uploadFileName);
                            // $magickCommand = "convert $uploadFileName -resize $width"."x".$height." $resizeName";
                            $gravity = "";
                        }
                        $magickCommand = "convert $uploadFileName $gravity -density 72 -units pixelsperinch -page letter $convertedUploadName";
                        if(!shell_exec($magickCommand)){
                            #echo "There may have been a problem converting you uploaded image to a pdf.";
                        }
                        # DELETE THE RAW UPLOADED IMAGES
                        if(file_exists($uploadFileName)){
                            // print($uploadFileName);
                            unlink($uploadFileName);
                        }
                        $uploadFileName = $convertedUploadName;
                        $ready = True;                        
                    }
                    elseif(in_array(pathinfo($uploadFileName, PATHINFO_EXTENSION), array("tif", "tiff"))){
                        $convertedUploadName = str_replace("tif","pdf", str_replace("tiff", "pdf", $uploadFileName));
                        $magickCommand = "convert $uploadFileName -density 72 -units pixelsperinch -page letter -compress jpeg $convertedUploadName";
                        if(!shell_exec($magickCommand)){
                            #echo "There may have been a problem converting you uploaded TIF to a pdf.";
                        }
                        # DELETE THE RAW UPLOADED IMAGES
                        if(file_exists($uploadFileName)){
                            // print($uploadFileName);
                            unlink($uploadFileName);
                        }
                        $uploadFileName = $convertedUploadName;
                        $ready = True;   
                    }
                    # ADD TO UPLOADS ARRAY
                    if($ready){
                        $uploadedFiles[$i] = $uploadFileName;
                    }
                    
                } 
                # THERE WAS PROBLEM UPLOADING FILE:
                else {
                    echo "Sorry, there was an error uploading your file.";
                }
            }
        }

    ## WRITE ATTACHMENT COVER PAGES (IF REQUESTED)
        ## CREATE ARRAY TO STORE TEMPORARY COVER PAGES:
        $coverPages = array();
        ## LOOP THROUGH POST VARIABLES:
        for ($i=1;$i<6;$i++){
            ##CHECK TO SEE IF USER WISHES TO GENERATE ATTACHMENT COVER PAGE:
            if(!empty($_POST["covertext$i"]) && !ctype_space($_POST["covertext$i"])){
                ##CREATE GENERIC COVER PAGE BASED ON SOURCE NAME
                ## ESTABLISH PDF METADATA, HEADER, STYLE
                    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                    $pdf->SetCreator("Worldwide Resources Inc.");
                    $pdf->SetAuthor('Worldwide Resources Inc.');
                    $pdf->SetTitle('Download from www.WorldwideResources.com');
                    $pdf->setHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING, array(0, 6, 255), array(0, 64, 128));
                    $pdf->setFooterData(array(0,64,0), array(0,64,128));
                    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
                    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
                    # set default monospaced font
                    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
                    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
                    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
                    # set auto page breaks
                    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                    # set image scale factor
                    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
                    # set default font subsetting mode
                    $pdf->setFont('dejavusans', '', 14, '', true);
                    $pdf->AddPage();
                    $pdf->SetPrintFooter(false);
                ## WRITE CONTENT OF PDF
                    $pdf->writeHTML('<p style="text-align:center"><br><br><br><br><br><br><br><span nobr="true">' .  nl2br(trim($_POST["covertext$i"])) . "</span></p>");
                    $attachmentTitle = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/uploads/wwr-cu/' . $wwr_id .  '_' . 'TEMP_COVER' . $i . '.pdf';
                    $pdf->Output($attachmentTitle, 'F');
                ## APPEND TO ARRAY OF TEMPORARY ATTACHMENT TITLES
                    $coverPages[$i] = $attachmentTitle;        	
            }
        }

    ## SPLIT PDFs AND CREATE ARRAY OF TEMP PDFS
    $tempPdfArray = array();
    #BIG LOOP THROUGH ROWS OF POST VARIABLES:
    for ($i=1;$i<6;$i++){

        $uploadedPdf = False;
        $attachedPdf = False;
        $attachedImg = False;
        #GET SOURCE PDF/TEMP PDF NAMES IF ATTACHED (IF PDF):
        if(!empty($_POST["pdfAppend$i"]) && !empty($_POST["newFileName"]) && substr($_POST["pdfAppend$i"],-4) == ".pdf" && !empty($wwr_id)){
            $SourcePdf = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . $_POST["pdfAppend$i"];
            $TempPdf = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . "TEMP$i" . "_" . $_POST["pdfAppend$i"];
            $attachedPdf = True;
        }
        #CHECK IF ATTACHMENT WAS OTHER FILETYPE, LIKE PNG OR PDF
        elseif(!empty($_POST["pdfAppend$i"]) && !empty($_POST["newFileName"]) && in_array(strtolower(substr($_POST["pdfAppend$i"],-4)), array(".jpg",".png")) && !empty($wwr_id)){
            $sourceImg = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . $_POST["pdfAppend$i"];
            $TempPdf = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . "TEMP$i" . "_" . substr($_POST["pdfAppend$i"], 0, strlen($_POST["pdfAppend$i"])-4) . ".pdf";
            #CONVERT IMAGE TO PDF
            $magickCommand = "convert $sourceImg -gravity center -density 72 -units pixelsperinch -page letter $TempPdf";
            if(!shell_exec($magickCommand)){
                #echo "There may have been a problem converting your uploaded image to a pdf.";
            }
            # ADD CONVERTED PDF TO TEMP ARRAY
            $attachedImg = True;
        }
        # CHECK IF ATTACHMENT IS TIF/TIFF (MULTI PAGE JPG/PNG)
        elseif(!empty($_POST["pdfAppend$i"]) && !empty($_POST["newFileName"]) && in_array(strtolower(substr($_POST["pdfAppend$i"], -4)), array("tiff", ".tif")) && !empty($wwr_id)){
            # THERE ARE TWO POSSIBLE EXTENSIONS WITH VARIABLE LENGTH. DECIDE WHICH EXTENSION:
            if(strtolower(substr($_POST["pdfAppend$i"], -5)) == ".tiff"){
                $ext = ".tiff";
            }
            else{
                $ext = ".tif";
            }
            $sourceImg = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . $_POST["pdfAppend$i"];
            $TempPdf = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . "TEMP$i" . "_" . substr($_POST["pdfAppend$i"], 0, strlen($_POST["pdfAppend$i"])-strlen($ext)) . ".pdf";
            #CONVERT TIF/TIFF TO PDF
            $magickCommand = "convert $sourceImg -density 72 -units pixelsperinch -page letter -compress jpeg $TempPdf";
            if(!shell_exec($magickCommand)){
                #echo "There may have been a problem converting your attached TIF to a pdf.";
            }
            # ADD CONVERTED PDF TO TEMP ARRAY
            $attachedImg = True;   
        }
        # GET NAME OF PDF IF UPLOADED:
        elseif(!empty($uploadedFiles[$i]) && !empty($_POST["newFileName"]) && !empty($wwr_id)){
            $TempPdf = $uploadedFiles[$i];
            $uploadedPdf = True;
        }
        #CHECK TO SEE IF USER WANTED TO SELECT SPECIFIC PAGES FROM PDF:
        if(!empty($_POST["appendPagesStart$i"]) && !empty($_POST["appendPagesEnd$i"]) && isset($wwr_id)){
            # FIND FIRST PAGE:
            $FirstPage = $_POST["appendPagesStart$i"];
            # FIND LAST PAGE:
            $LastPage = $_POST["appendPagesEnd$i"];   
            #CHECK TO SEE IF UPLOADED FILES NEED TO BE SPLIT:    
            if($uploadedPdf){
                #IF SO, WE NEED TO CHANGE OUR STRATEGY ABOUT WHAT IS THE SOURCE/TEMP PDF NAME
                $SourcePdf = $TempPdf;
                $TempPdf = $_SERVER["DOCUMENT_ROOT"] . "wp-content/uploads/wwr-cu/" . "TEMP$i" . "_" . basename(str_replace('pdf', '', clean($_FILES["fileupload$i"]["name"]))) . ".pdf";
            }
            elseif($attachedImg){
                #SAME THING HERE
                $SourcePdf = $TempPdf;
                $TempPdf = str_replace("TEMP","TEMP2",$TempPdf); 
            }
            #RUN GS COMMAND TO SLICE PDF:
            $cmd = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -dAutoRotatePages=/None -dFirstPage=$FirstPage -dLastPage=$LastPage -sOutputFile=$TempPdf $SourcePdf";
            shell_exec($cmd);
            #DELETE TEMPORARY PDFS AFTER WE ROTATED THEM AND CREATED A NEW VERSION. 
            if($attachedImg){
                if(file_exists($SourcePdf)){
                    unlink($SourcePdf);
                }
            }           
        }

        #IF USER IS ATTACHING BUT DOES NOT WISH TO SPLIT PDF, JUST CREATE TEMPORARY PDF COPY
        elseif($attachedPdf) {
            $cmd = "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -dAutoRotatePages=/None -sOutputFile=$TempPdf $SourcePdf";
            shell_exec($cmd);
        }
        # IF USER UPLOADED PDF AND DOES NOT WISH TO SPLIT, THATS OK, IT WILL BE ADDED TO ARRAY. NO NEED TO DUPLICATE TEMP FILE AGAIN. 
        # ADD TEMP PDF TO THE ARRAY (AND MAKE SURE THAT USER ACTUALLY UPLOADED OR ATTACHED A PDF):
        if($uploadedPdf || $attachedPdf || $attachedImg){
            $tempPdfArray[$i] = $TempPdf;
        }
    }

    ## ROTATE THE PDFS
        ## LOOP THROUGH ALL ATTACHED PDFS:
        for ($i=1;$i<6;$i++){
            # CHECK IF USER REQUESTED TO ROTATE PDF:
            if (isset($tempPdfArray[$i]) && !empty($_POST["rotateDegrees$i"]) && $_POST["rotateDegrees$i"] != "0" && isset($wwr_id) && !empty($_POST["newFileName"])){
                #CREATE ROTATED PDF WITH PdfRotate CLASS
                $pdf = new PdfRotate;
                #SPECIFY SOURCE FILE (FROM OUR ARRAY OF TEMP PDFS)
                $sourceFile = $tempPdfArray[$i];
                #SPECIFY NAME OF ROTATED PDF (ITS NAME WILL BE THE SAME)
                $outputFile = $tempPdfArray[$i];
                #SPECIFY NUMBER OF DEGREES TO ROTATE PDF
                $degrees = intval($_POST["rotateDegrees$i"]);
                #ROTATE PDF
                $pdf->rotatePdf($sourceFile, $outputFile, $degrees);
            }
        }

    ## MERGE THE TEMP PDFS INTO ONE PDF
        #CHECK TO SEE IF USER ACTUALLY REQUESTED NEW PDF:
        if (!empty($_POST["newFileName"]) && count($tempPdfArray)>0 && isset($wwr_id)){
            #DEFINE THE NAME OF THE FINAL PDF
            $outputName = "/wp-content/uploads/wwr-cu/" . $wwr_id . "_" . clean($_POST["newFileName"]) . ".pdf";
            $outputFile = $_SERVER["DOCUMENT_ROOT"] . $outputName;
            
            #WRITE GHOSTSCRIPT SHELL COMMAND TO MERGE PDFS, BY LOOPING THROUGH ALL FILES IN ARRAY
            $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dAutoRotatePages=/None -sOutputFile=$outputFile ";
            #ADD PDF TO BE MERGED THROUGH LOOP
            for ($i=1;$i<6;$i++){
                if(!empty($coverPages[$i])){
                    $cmd .=$coverPages[$i]." ";
                }
                if(!empty($tempPdfArray[$i])){
                    $cmd .=$tempPdfArray[$i]." ";
                }
            }
            #EXECUTE SHELL COMMAND
            shell_exec($cmd);
        }
       

    ## DELETE THE TEMPORARY PDFS
        if(count($tempPdfArray)>0){
            foreach ($tempPdfArray as $pdf){
                if(file_exists($pdf)){
                    unlink($pdf);
                }
            }
        }
        ## SAME THING BUT FOR THE TEMPORARY COVER PAGES:
        if(count($coverPages)>0){
            foreach($coverPages as $pdf){
                if(file_exists($pdf)){
                    unlink($pdf);
                }
            }
        }
        ## SAME THING BUT FOR TEMPORARY UPLOADED FILES:
        if(count($uploadedFiles)>0){
            foreach($uploadedFiles as $pdf){
                if(file_exists($pdf)){
                    unlink($pdf);
                    echo $pdf;
                }
            }
        }

    ## INSERT NEW RECORD INTO DATABASE FOR THE NEW PDF
        ## CHECK TO SEE IF NEW PDF WAS ACTUALLY MADE:
        if (!empty($_POST["newFileName"]) && count($tempPdfArray)>0 && isset($wwr_id)) {
            #WRITE MySQL INSERT STATEMENT:
            $documentType = $_POST["documentType"];
            # LOGIC TO DECIDE IF PDF WILL BE VIEWABLE TO CUSTOMERS
            if (in_array($documentType, array("Attachment for Report"
                                                , "Customer Upload"
                                                , "Customer Upload after Submission"
                                                , "Customer Use"
                                                , "customer_uploaded_files"
                                                , "Message from WWR"
                                                , "Report"
                                                , "Report: Final")
                                            )){
                $customer_can_access = "Yes";
            }
            else{
                $customer_can_access = "No";
            }
            ## LOGIC TO DECIDE IF PDF IS ATTACHED TO SPECIFIC SOURCE (DEFAULT IS 0- NONE)
            if(isset($_POST['sourceNumber']) && !empty($_POST['sourceNumber']) &&  is_numeric($_POST['sourceNumber'])){
                $sourcenumber = intval($_POST['sourceNumber']); 
            }
            else{
                $sourcenumber = 0;
            }
            #WRITE INSERT SQL STATEMENT 
            $insert_statement = "INSERT INTO 1wwr_files (
                                            wwr_id
                                            , who_uploaded
                                            , how_uploaded
                                            , uploaded_file_name
                                            , uploaded_file_directory
                                            , uploaded_file_full_link
                                            , short_file_name
                                            , file_type
                                            , customer_can_access
                                            , independent_contractor_can_access
                                            , source_number
                                            )
                                        VALUES (
                                            '$wwr_id'
                                            , '" . $current_user->user_login . "'
                                            , 'PDF_screen'
                                            , '" .  $wwr_id . '_' . clean($_POST["newFileName"]) . ".pdf'
                                            , '/wp-content/uploads/wwr-cu/'
                                            , '/wp-content/uploads/wwr-cu/" . $wwr_id . '_' . clean($_POST["newFileName"]) . ".pdf'
                                            , '" . clean($_POST["newFileName"]) . ".pdf'
                                            , '$documentType'
                                            , '$customer_can_access'
                                            , 'Yes'
                                            , $sourcenumber
                                        );
            ";
            ## RUN INSERT STATEMENT, CHECK FOR ERRORS:
            if (!mysqli_query($connection,$insert_statement)) {
            echo "<p><b>There was an unspecified error with your PDF generation. </b> Please attempt again. </p>
            <p>If it still does not work, please contact WWR to notify us of this issue.</p>"; 
            }
        }

    ## QUERY THE DATABASE TO FIND ALL PDFS ASSOCIATED WITH WWR ID
        $sql_statement = "SELECT uploaded_file_name, short_file_name 
                            FROM 1wwr_files 
                            WHERE latest_record = 0 
                                AND wwr_id = '$wwr_id' 
                                AND RIGHT(short_file_name, 4) IN ('.pdf','.jpg','.png', 'tiff', '.tif')
                            ORDER BY short_file_name;";
        $result = mysqli_query($connection, $sql_statement);
        $optionString = "";
        $pdfLinks = "";
        ## FOR EACH RETURNED ROW, EXTRACT VALUES
        while($row = mysqli_fetch_assoc($result)) {
            $fileLocation = $row["uploaded_file_name"];
            $fileName = $row["short_file_name"];
            ##GET PAGE COUNT FOR EACH PDF
            $pageCount = pingPDF($_SERVER["DOCUMENT_ROOT"] . "/wp-content/uploads/wwr-cu/" . $fileLocation);
            ## CREATE STRING IN HTML THAT LISTS ALL PDFS AND THEIR PAGE COUNT. 
            ## WE WILL USE THIS STRING LATER WHEN WRITING THE PAGE HTML.
            $optionString .= "
            <option value='$fileLocation' pagecount='$pageCount'> $fileName ($pageCount pages) </option>";
            ## CREATE STRING IN HTML THAT CONTAINS ALL PDFS AS OPENABLE LINKS
            $pdfLinks .= "
            <a target='_blank' class='darklink' href='/wp-content/uploads/wwr-cu/$fileLocation'>$fileName ($pageCount pages)" . "</a><br>";
        }

    ## QUERY DATABASE TO FIND SOURCE NAMES:
        $sql_statement = "SELECT source_name, source_number, source_alternative_name
                            FROM 1wwr_sources 
                            WHERE latest_record = 0 
                                AND wwr_id = '$wwr_id'";
        $result = mysqli_query($connection, $sql_statement);
        $sourceOptions = "";
        $sourceAttachmentOptions = "";
        while($row = mysqli_fetch_assoc($result)) {
            $sourceName = $row["source_name"];
            $sourceAlt = $row["source_alternative_name"];
            $sourceNumber = $row["source_number"];
            ## WRITE HTML OPTION STRING TO USE LATER IN THE HTML:
            $sourceOptions .= "
            <option value='$sourceName'> $sourceName </option>";
            ## CHECK IF ALTERNATE NAME IS NOT BLANK, IF SO, INCLUDE AS AN OPTION
            if (!empty($sourceAlt) && strlen($sourceAlt)>4){
                $sourceOptions .= "
                <option value='$sourceAlt'> $sourceAlt </option>";
            }
            ## WRITE HTML OPTION STRING FOR LATER IN HTML, THIS TIME USING SOURCE NUMBER AS VALUE
            $sourceAttachmentOptions .= "<option value='$sourceNumber'> $sourceName </option>";
        }
        
    ## CREATE HEADER BAR
        ## CHECK TO SEE USER ROLE, IF HIGH RANKED ENOUGH THEY CAN SEE INVOICE NAVIGATION BUTTON
        if (WPTime_check_user_role('administrator') || WPTime_check_user_role('editor') || WPTime_check_user_role('author') ){
            ## WRITE HTML FOR THE TABLE CELL/BUTTON FOR INVOICE	
            $invoice = " <td class='noBorder' style='text-align: center;'> 
                            <a  class='' style='color:white' href='/invoice/?wwr_id=$wwr_id'>   
                                <button type='button' class='buttonb'>Invoice</button>
                            </a>
                        </td>";
        }
        ## IF NOT HIGH RANKED, THEY ONLY SEE EXPENSE NAVIGATION BUTTON
        else {
            ## WRITE HTML FOR THE TABLE CELL(BUTTON) FOR EXPENSE
            $invoice = "<td class='noBorder' style='text-align: center;'> 
                            <a  class='' style='color:white' href='/expense/?wwr_id=$wwr_id'>  
                                <button type='button' class='buttonb'>Expense</button>
                            </a>
                        </td>";
        }
        ## WRITE HTML FOR HEADER BAR
        $headerButtonsHtml =  "                                            
                <!--YOU NEED THIS EMPTY DIV 'HEADER' FOR JAVASCRIPT TO KNOW HOW TO MAKE THE HEADER BAR STICKY AS YOU SCROLL-->    
                <div id='header' ></div> 
                <div id='navbar'>        
                    <div class='si' >
                        <table class='noBorder'>
                            <tr class='noBorder'>
                                <td id='titleBox' style='width: 300px;' class='thisBox noBorder'><b> <span style='font-size:20px'>$last_name, $first_name</span>
                                    <br> 
                                    $wwr_id</b><small> / $file_number 
                                    <br>
                                    <div style='line-height:11px;'> 
                                        <a class='darklink' href='/wp-content/uploads/wwr-cu/". $wwr_id . "_Current_Report.pdf?buster=<$now' target='_blank'>Current Report</a></small>
                                        <span style='line-height:0px;'><small>, $whenLastSaved, $whoLastSaved</small></span>
                                    </div>
                                </td>
                                                

                                <td class='noBorder' style=' padding-right: 15px; text-align: right; font-size: 13px; line-height:14px;'>
                                    Use these buttons --> <br>instead of back-button. <br>Don't have two pages open <br>that are the same ID and page. 
                                </td>

                                <td class='noBorder' style='text-align: center;'>
                                    <button type='button' id='secondarySubmit' class='buttong noDoubleClick'>Create PDF</button>
                                </td>
                                <td  class='noBorder' style='text-align: center;'>
                                    <a style='color:white' href='/task-view/?task_id=$wwr_id'> 
                                        <button class='buttonb'>Task View</button>
                                    </a>
                                </td>
                                <td  class='noBorder' style='text-align: center;'>
                                    <a class='' style='color:white' href='/notes/?task_id=$wwr_id'>
                                        <button type='button' class='buttonb'>Source Notes</button>
                                    </a>
                                </td>
                                <td  class='noBorder' style='text-align: center;'>
                                    <a class='' style='color:white' href='/requests/?task_id=$wwr_id'>
                                        <button type='button' class='buttonb'>Record Requests</button>
                                    </a>
                                </td>
                                <td  class='noBorder' style='text-align: center;'>
                                    <a class='' style='color:white' href='/pdf-creator/?wwr_id=$wwr_id'>
                                        <button type='button' class='buttonb currentPage'>PDF Creator</button>
                                    </a>
                                </td>
                                $invoice
                                <td class='noBorder' style='text-align: center;'>
                                    <a class='' style='color:white' href='/time-card/?task_id=$wwr_id'>
                                        <button type='button' class='buttonb'> Time Card </button>  
                                    </a>
                                            
                                </td>
                                <td  class='noBorder' style='text-align: center;'>
                                    <a class='' style='color:white' href='/customer-view/?task_id=$wwr_id'>
                                        <button type='button' class='buttonb'>Customer View</button>
                                    </a>
                                </td>
                        
                                <td class='noBorder' style='text-align: center;'> 
                                    <a  style='color:white' href='/tasks/' role='button'>
                                        <button class='buttonb'>Submissions</button>
                                    </a>
                                </td>
                                <td class='noBorder' style='text-align: center;'> <button style='visibility: hidden;' type='button' class='buttonb'  disabled >Nothing</button></a>
                                </td>
                            </tr>";
                            
        #$headerButtonsHtml .=     "<tr class='noBorder'><td class='noBorder' colspan='100%' style='font-size: 9px;' name='change_tracker' id='change_tracker'>No Changes</td></tr>";
        $headerButtonsHtml .= "</table>
                            </div>
                        </div>
                    <div id='storage' style='display:none'>
                        I ONLY MADE THIS DIV TO STORE THE VALUE OF THE POSITION OF THE NAV.
                        WE HAD A PROBLEM WHERE USERS WHO SCROLLED THE PAGE IS IT WAS LOADING
                        WOULD HAVE ISSUES WITH THE STICKY HEADER BAR. 
                        SO HERE, WE ARE STORING THE POSITION RIGHT AS THE PAGE RENDERS. 
                    </div>
                        <script>
                    let navPos = document.getElementById('navbar').getBoundingClientRect().top;
                    document.getElementById('storage').navPos = navPos;
                    </script>    
                            ";

    ## WRITE CSS
        $css = "
        <style>
        /* MAKE <TEXTAREAS> SHORTER */
        .customTextArea {
            max-height: 80px;
        }
        .narrower{
            width: 15%;
        }
        </style>
        ";

    ## WRITE HTML
        $html = "
        $headerButtonsHtml
        ";
        #CHECK TO SEE IF NEW PDF WAS MADE
        if(!empty($outputName)){
            $html .= "
            <h4>Your PDF has been generated. Click this <a class='darklink' target='_blank' href='$outputName'>link</a> to view.</h4>
            ";
        }
        $html .= "
        <p>Here are all PDFs currently associated with this investigation:</p>
        $pdfLinks
        <br>
        <form method='POST' id='pdfManagerForm'  enctype='multipart/form-data'>
        <h4>PDF Creator: Create PDFs by uploading, merging and/or changing orientation of already existing PDFs.</h4>
        <table>
        <tr>
            <th>PDF</th>
            <th>File</th>
            <th colspan='2'>Optional Cover Letter (Leave Blank for No Cover Letter)</th>
            <th>Start on page</th>
            <th>End at page</th>
            <th>Rotate</th>
        </tr>";
        for ($i=1;$i<6;$i++){
            $html .= "
                <tr>
                    <td>
                        PDF #$i
                    </td>
                    <td>
                        Choose a pdf...
                        <select onchange='pageCounter(" . '"' . $i . '"' .  ");' name='pdfAppend$i' id='pdfAppend$i'>
                            <option value='' selected disabled>(Optional)</option>
                            $optionString			
                            <option value=''>None</option>
                        </select>
                        Or upload:
                        <input class='file' name='fileupload$i' id='fileupload$i' type='file' accept='.pdf, .png, .jpg, .tif, .tiff'>
                        </input>
                    </td>
                    <td class='narrower'>
                        Optional: Autofill with Source Name
                        <select class='' name='source$i' id='source$i'>
                            <option value=''>None</option>
                            $sourceOptions
                        </select>
                    </td>
                    <td>
                        (Note: &lt;b> &lt;/b> indicates bold text)
                        <textarea class='customTextArea' name='covertext$i' id='covertext$i' rows='1' cols='1'></textarea>
                    </td>
                    <td style='width:45px'>
                        First page:
                        <input type='number' min='1' name='appendPagesStart$i' id='appendPagesStart$i'>
                    </td>
                    <td style='width:45px'>
                        Last page:
                        <input type='number' min='1' name='appendPagesEnd$i' id='appendPagesEnd$i'>
                    </td>
                    <td>
                        Degrees rotate:
                        <select name='rotateDegrees$i'>
                            <option value='0'>0 degrees</option>
                            <option value='90'>90 degrees</option>
                            <option value='180'>180 degrees</option>
                            <option value='270'>270 degrees</option>
                        </select>
                    </td>
                </tr>
            ";
        }
          
        $html.= "</table>";

        $html .= "
        <label for ='newFileName'>Required: Choose a file name and document type for your new PDF. Note that the WWR ID will automatically be inserted before your filename.</label>
            $wwr_id _<input required style='width:200px!important' id='newFileName' name='newFileName'type='text'>.pdf
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Document Type:&nbsp;
            <select id='documentType' name='documentType' class='narrower'>";
        #ITERATE THROUGH ALL DOCUMENT TYPES 
        $documentType = array("Investigator Use","Customer Use","Attachment for Report","Report","Report: Draft","Customer Upload","Customer Upload after Submission","Message from WWR");
        foreach($documentType as $type){
            $html.="<option>$type</option>";
        }
        $html.="</select>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Attachment for specific source:&nbsp;
        <select id='sourceNumber' name='sourceNumber' class='narrower'>
            <option value=''>None</option>
            $sourceAttachmentOptions
        </select>
        <br>
        <br>
        <button id='primarySubmit' class='buttong noDoubleClick' type='submit'>Create Pdf</button>
        </form>
        <br>
        ";

    ## WRITE JAVASCRIPT
        $newline = '\n';
        $javascript = "
        <script>
        //MAKE THE HEADER BAR STICKY AS YOU SCROLL DOWN THE PAGE
            window.addEventListener('load', function () {
                // FIND THE HEADER BAR's ID
                let navbar = document.getElementById('navbar');
                let header = document.getElementById('header');
                // GET THE POS OF THE HEADER BAR
                let navPos = document.getElementById('storage').navPos;
                // HAVE JAVASCRIPT DETECT WHEN YOU SCROLL
                window.addEventListener('scroll', e => {
                let scrollPos = window.scrollY;
                // IF YOU SCROLL PAST THE NAV BAR, IT WILL NOW BE STICKY
                if (scrollPos > navPos) {
                    navbar.classList.add('navbar');
                    header.classList.add('marginScroll');
                // OTHERWISE IT WILL STAY AT THE TOP
                } else {
                    navbar.classList.remove('navbar');
                    header.classList.remove('marginScroll');
                }
                });
            })
           
        // DISABLE NAVIGATION BUTTON IN HEADER BAR IF IT IS THE CURRENT PAGE
            var currentPageButtons = document.querySelectorAll('.currentPage');
            for (i = 0; i < currentPageButtons.length; i++){
                currentPageButtons[i].disabled = true;
            }

        // FUNCTION THAT VALIDATES AND SUBMITS THE FORM EVEN WITH MULTIPLE SUBMIT BUTTONS
            // FIND THE PRIMARY AND SECONDARY SUBMIT BUTTONS
            const primary = document.getElementById('primarySubmit');
            const secondary = document.getElementById('secondarySubmit');
            // THIS FUNCTION IS RAN WHENEVER THE PRIMARY SUBMIT BUTTON IS CLICKED
            function primarySave(e){
                // 5 ms TIMER, THEN DISABLE THE SUBMIT BUTTONS SO THAT THEY ARE NOT DOUBLE CLICKED. 
                setTimeout(noDoubleClick, 5);
            }
            // THIS FUNCTION IS CALLED WHENEVER THE SECONDARY SUBMIT BUTTON IS CLICKED; IT CLICKS THE PRIMARY SUBMIT BUTTON. 
            function secondarySave(e){
                e.preventDefault();
                primary.click();
            }
            // HAVE JAVASCRIPT CHECK TO SEE IF SUBMIT BUTTONS ARE CLICKED
            primary.addEventListener('click', primarySave, false);
            secondary.addEventListener('click', secondarySave, false);


        // MAKE IT SO THAT YOU CAN'T DOUBLE CLICK THE SUBMIT BUTTON(S)
        	function noDoubleClick() {
                // CHECK TO SEE IF FORM IS VALID (ALL REQUIRED INFO IS FILLED, ETC)
                var f = document.getElementsByTagName('form')[0];
                if(f.checkValidity()) {
                    // IF THE FORM IS VALID AND THE SUBMIT BUTTON WAS CLICKED, DISABLE THE BUTTONS
                    // FIND ALL BUTTONS WITH CLASS noDoubleClick
                    const submitButtons = document.querySelectorAll('button.noDoubleClick');
                    // LOOP THROUGH ALL BUTTONS
                    var arrayLength = submitButtons.length;
                    for (var i = 0; i < arrayLength; i++) {
                        // DISABLE THE BUTTON
                        submitButtons[i].disabled=true;
                    }
                }               
            }

        // CREATE FUNCTION THAT IS CALLED EVERYTIME A USER SELECTS A PDF THAT AUTO SETS THE PAGE SELECTION
            function pageCounter(id) {
                // GET ELEMENT ID THAT WAS CHANGED:
                var pdfAppend = 'pdfAppend' + id;
                // GET IDS FOR ADJACENT ELEMENTS FOR CHOOSING PAGE SELECTION
                var appendPagesEnd = 'appendPagesEnd' + id;
                var appendPagesStart = 'appendPagesStart' + id;
                // GET THE PAGE COUNT FOR THE SELECTED PDF             
                var pageCount = document.getElementById(pdfAppend).options[document.getElementById(pdfAppend).selectedIndex].getAttribute('pagecount');
                if(pageCount == 0){
                    document.getElementById(appendPagesEnd).value = '';
                    document.getElementById(appendPagesStart).value = '';
                    return;
                }
                // pageCount = pageCount.trim();
                // SET THE DEFAULT START PAGE AS 1
                document.getElementById(appendPagesStart).value = 1;
                // SET THE DEFAULT END PAGE AS THE LAST PAGE OF SELECTED PDF
                document.getElementById(appendPagesEnd).value = pageCount;
                // SET THE MAXIMUM PAGE SELECTION OPTIONS AS THE LAST PAGE OF SELECTED PDF
                document.getElementById(appendPagesStart).max = pageCount;
                document.getElementById(appendPagesEnd).max = pageCount;
            }

        // CREATE FUNCTION THAT PREPOPULATES THE TEXTAREAS WHEN USERS SELECT A SOURCE NAME
            function populateTextArea(evt){
                // GET ID OF SELECT THAT CALLED FUNCTION:
                source = evt.currentTarget;
                // FIND ROW NUMBER
                i = source.row;
                newline = '$newline'; 
                textarea = document.getElementById('covertext' + i);
                // FIND SOURCE NAME
                sourceName = source.value;
                // POPULATE TEXT AREA
                textarea.innerHTML = '<b>Attachment</b>' + newline + '$first_name ' + '$last_name' + newline + '$subjectFileNumber' + newline + sourceName;
            }

            // ADD EVENT LISTENER TO ALL SOURCE SELECT ELEMENTS
            for (var i = 1; i < 6; i++){
                source = document.getElementById('source' + i);
                source.row = i;
                source.addEventListener('change', populateTextArea, false);
            }

        // USE JAVASCRIPT TO GET PAGE COUNT FOR USER UPLOADED PDFS:
            const fileLoop = document.querySelectorAll('input[type=file]');
            for (var i = 0;i<fileLoop.length;i++){
                fileLoop[i].addEventListener('change', pageCounterUpload);
            }

            function pageCounterUpload(e) {
                var input = e.target;
                id = input.id.charAt(input.id.length-1);
                var reader = new FileReader();
                reader.readAsBinaryString(input.files[0]);
                reader.onloadend = function(){
                    // GET PAGE COUNT FOR UPLOADED PDF
                    var pageCount = reader.result.match(/\/Type[\s]*\/Page[^s]/g).length;
                    // GET IDS FOR ADJACENT ELEMENTS FOR CHOOSING PAGE SELECTION
                    var appendPagesEnd = 'appendPagesEnd' + id;
                    var appendPagesStart = 'appendPagesStart' + id;
                    // SET THE DEFAULT START PAGE AS 1
                    document.getElementById(appendPagesStart).value = 1;
                    // SET THE DEFAULT END PAGE AS THE LAST PAGE OF SELECTED PDF
                    document.getElementById(appendPagesEnd).value = pageCount;
                    // SET THE MAXIMUM PAGE SELECTION OPTIONS AS THE LAST PAGE OF SELECTED PDF
                    document.getElementById(appendPagesStart).max = pageCount;
                    document.getElementById(appendPagesEnd).max = pageCount;
                }
            }
           
        </script>
        ";

    ## ECHO EVERYTHING
        echo $css;
        echo $html;
        echo $javascript;
    
}
    ##THIS IS JUST A SPACE FOR SAVING CODE SNIPPETS FOR THE FUTURE:
    //<b>Attachment</b><br>$first_name $last_name <br>$subjectFileNumber <br>
?>