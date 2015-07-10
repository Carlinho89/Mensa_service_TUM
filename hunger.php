<?php
include 'vendor/autoload.php';
function crawl_page($url){
    $mylinks = array();     
        //Create a new DOM document
    $dom = new DOMDocument;
        //Parse the HTML. The @ is used to suppress any parsing errors
        //that will be thrown if the $html string isn't valid XHTML.
    @$dom->loadHTMLFile($url);
        //Get all links. You could also use any other tag name here,
        //like 'img' or 'table', to extract other tags.
    $links = $dom->getElementsByTagName('a');
        //Iterate over the extracted links and display their URLs 
    foreach ($links as $link){
            //Extract and save the "href" attribute.
        array_push($mylinks, $link->getAttribute('href'));
    }
    return $mylinks;
}
function redirect($url, $statusCode = 303){
   header('Location: ' . $url, true, $statusCode);
   die();
}
function pdfToString(){
    $links = crawl_page("http://www.betriebsrestaurant-gmbh.de/index.php?id=91");
    $pdfLink;
    foreach ($links as $file) {
        if (strpos(strtolower($file), '.pdf') !== FALSE && strpos($file, '_FMI_') !== FALSE) {
            $weekNumber = date("W"); 
            if ($weekNumber === substr($file,16,2)){
                // current link is MI pdf
                $pdfLink = "http://www.betriebsrestaurant-gmbh.de/".$file;
            }
        }
    }
    
    // Parse pdf file and build necessary objects.
    $parser = new \Smalot\PdfParser\Parser();
    $pdf    = $parser->parseFile($pdfLink);
    
    $text = $pdf->getText();
    return $text;    
}
function debug_to_console( $data ) {
    if ( is_array( $data ) )
        $output = "<script>console.log( 'Debug Objects: " . implode( ',', $data) . "' );</script>";
    else
        $output = "<script>console.log( 'Debug Objects: " . $data . "' );</script>";
    echo $output;
}
?>

<html>
<head>
    <title>Hunger!11!! - Speiseplan MI, TUM</title>
    <meta charset="UTF-8">
    <link href="http://fonts.googleapis.com/css?family=Raleway:400,300,600" rel="stylesheet" type="text/css">
    <style media="screen" type="text/css">
        h2, h3, h4, h5, h6 {
            font-weight: 600;
        }
        html, body {
            margin: 0px;
            line-height: 1.6;
            font-weight: 400;
            font-family: "Raleway", "HelveticaNeue", "Helvetica Neue", Helvetica, Arial, sans-serif !important;
        }
        h1 {
            font-weight: 300;
            background-image: linear-gradient(#FF9800 0%, #FF9800 100%);
            background-image: webkit-linear-gradient(#FF9800 0%, #FF9800 100%);
            color: white;
            padding:5px;
        }
        .container {
            padding: 10px;
        }
    </style>
</head>
<body>
    <h1 style="font-size: 30px !important">Hunger | <a href="http://tum.sexy" style="color: #FFF !important; font-size:15px !important; text-decoration: none !important">TUM.<strong>sexy</strong></a></h1>
    <div class="container">
        <p>This is the 'Speiseplan' of the current week in the Bistro of the Informatik Fakultät at TUM.</p>
        <?php 
        $raw = preg_split("/\n\s*\n/", pdfToString()); //split the whole pdf string on the days
        $days = array_slice($raw, 4, count($raw)-7); // Remove unneded stuff
        $currentDayOfWeek = idate('w', time());// Only display today and future days
        
        $i = 1;
        foreach($days as $day) {
            if ($i >= $currentDayOfWeek) {
                $dayArray = preg_split("/\n\d[.]/", $day);
                $title = array_shift($dayArray);
                echo "<h3>".$title."</h3>";
                echo "<ul>";
                foreach($dayArray as $meal) {
                    echo "<li>".preg_replace("/\d([,]\d*)* oder B.n.W./", "", $meal)."€</li>";
                }
                echo "</ul>";
            }
            $i += 1;
        }
        ?>
    </div>
</body>
</html>