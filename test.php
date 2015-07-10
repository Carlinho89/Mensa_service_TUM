
<?php
include_once('simple_html_dom.php');
// Create DOM from URL or file
$html = file_get_html('http://www.studentenwerk-muenchen.de/mensa/speiseplan/index-en.html?vorlage_speiseplan_uebersicht=&cHash=502f9361c27815f5b13465388746f1e7');

$es = $html->find('#c1582 p')[0];

//$ret = $html->find('div[id=c1582]' h3);
echo($es);
$arr = array('a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5);

echo json_encode($arr);

// Find all images 
//foreach($html->find('img') as $element) 
       //echo $element->src . '<br>';

// Find all links 
//foreach($html->find('a') as $element) 
 //      echo $element->href . '<br>';
?>
