<?php

require './vendor/autoload.php';

if (!empty($_POST["generate"])) {
    $targetPath = "barcode/";

    if (!is_dir($targetPath)) {
        mkdir($targetPath, 0777, true);
    }
     $barcode_numbers = explode("\n", trim($_POST["bnumber"]));

    try {
        // This will output the barcode as HTML output to display in the browser
        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        //$generator = new Picqer\Barcode\BarcodeGeneratorDynamicHTML();

        foreach ($barcode_numbers as $number) {
            $number =  trim($number);
            //$barcode = $generator->getBarcode($number, $generator::TYPE_CODE_128, 3, 50);

            $barcode = '<img src="data:image/png;base64,' . base64_encode($generator->getBarcode($number, $generator::TYPE_CODE_128)) . '">';
            echo getHTML($barcode, $number);
        }
    } catch (Exception $e) {
        echo 'Message: ' . $e->getMessage();
        die;
    }
}

function getHTML($barcode, $number)
{
    $html = str_replace(
        array('%barcode%', '%number%'),
        array($barcode, $number),
        file_get_contents("printlabel.html")
    );

    return $html;
}
