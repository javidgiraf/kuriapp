<?php
require 'vendor/autoload.php'; // Include Composer autoload for endroid/qr-code

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$html = '';

if (isset($_POST['vcard-post'])) {
    try {
        
        $fname = isset($_POST['firstname']) ? $_POST['firstname'] : '';
        $lname = isset($_POST['lastname']) ? $_POST['lastname'] : '';
        $phone = isset($_POST['workphone']) ? $_POST['workphone'] : '';
        $email = isset($_POST['email']) ? $_POST['email'] : '';
        $company = isset($_POST['company']) ? $_POST['company'] : '';
        $website = isset($_POST['website']) ? $_POST['website'] : '';
        $role = isset($_POST['role']) ? $_POST['role'] : '';
        $address = isset($_POST['address']) ? $_POST['address'] : '';

        
        $vCard = "BEGIN:VCARD\n";
        $vCard .= "FN:$fname $lname\n";
        $vCard .= "ORG:$company\n";
        $vCard .= "TEL:$phone\n";
        $vCard .= "EMAIL:$email\n";
        $vCard .= "URL:$website\n";
        $vCard .= "ROLE:$role\n";
        $vCard .= "ADR:$address\n";
        $vCard .= "END:VCARD";

        
        $qrCode = new QrCode($vCard);
        $writer = new PngWriter();
        $image = $writer->write($qrCode)->getDataUri(); // Get the Data URI directly

        
        $html .= '
            <h4 class="d-flex justify-content-between align-items-center mb-3"><span class="text-primary">QR CODE</span></h4>
            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-center lh-sm" id="qrcode-img"><img src="' . $image . '" alt="QR Code" id="qr-img" /></li>
            </ul>
            <div class="input-group d-flex justify-content-center">
                <button type="button" class="btn btn-warning" onClick="downloadImage()">Download</button>
            </div>';

        echo $html;

    } catch (Exception $e) {
        echo '<p><b>Exception launched!</b><br /><br />' .
            'Message: ' . $e->getMessage() . '<br />' .
            'File: ' . $e->getFile() . '<br />' .
            'Line: ' . $e->getLine() . '<br />' .
            'Trace: <p/><pre>' . $e->getTraceAsString() . '</pre>';
    }

} else if (isset($_POST['url-post'])) {
    try {
        $weburl = isset($_POST['weburl']) ? $_POST['weburl'] : '';

        
        $qrCode = new QrCode($weburl);
        $writer = new PngWriter();
        $image = $writer->write($qrCode)->getDataUri(); // Get the Data URI directly

        
        $html .= '
            <h4 class="d-flex justify-content-between align-items-center mb-3"><span class="text-primary">QR CODE</span></h4>
            <ul class="list-group mb-3">
                <li style="list-style: none;">' . $weburl . '</li>
                <li class="list-group-item d-flex justify-content-center lh-sm" id="qrcode-img" style="list-style: none;">
                    <img src="' . $image . '" alt="QR Code" id="qr-img" />
                </li>
            </ul>
            <div class="input-group d-flex justify-content-center">
                <button type="button" class="btn btn-warning" onClick="downloadImage()">Download</button>
            </div>';

        echo $html;

    } catch (Exception $e) {
        echo '<p><b>Exception launched!</b><br /><br />' .
            'Message: ' . $e->getMessage() . '<br />' .
            'File: ' . $e->getFile() . '<br />' .
            'Line: ' . $e->getLine() . '<br />' .
            'Trace: <p/><pre>' . $e->getTraceAsString() . '</pre>';
    }

} else {
    try {
        
        $weburl = 'https://yesmachinery.ae';

        
        $qrCode = new QrCode($weburl);
        $writer = new PngWriter();
        $image = $writer->write($qrCode)->getDataUri(); // Get the Data URI directly

        
        $html .= '
            <h4 class="d-flex justify-content-between align-items-center mb-3"><span class="text-primary">QR CODE</span></h4>
            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-center lh-sm" id="qrcode-img"><img src="' . $image . '" alt="QR Code" id="qr-img" /></li>
            </ul>
            <div class="input-group d-flex justify-content-center">
                <button type="button" class="btn btn-warning" onClick="downloadImage()">Download</button>
            </div>';

        echo $html;

    } catch (Exception $e) {
        echo '<p><b>Exception launched!</b><br /><br />' .
            'Message: ' . $e->getMessage() . '<br />' .
            'File: ' . $e->getFile() . '<br />' .
            'Line: ' . $e->getLine() . '<br />' .
            'Trace: <p/><pre>' . $e->getTraceAsString() . '</pre>';
    }
}

?>
