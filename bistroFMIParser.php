<?php
include 'vendor/autoload.php';
/**
 * Class representing a mensa's meal
 * Class Meal
 */
class Meal {
    public $id;
    public $mensa_id;
    public $date;
    public $type_short;
    public $type_long;
    public $type_nr;
    public $name;

     public function store($db){
        if ($this->id != ""){
            $sql = "INSERT INTO mensa_menu VALUES 
            ({$this->id}, {$this->mensa_id}, DATE('{$this->date}'), '{$this->type_short}', '{$this->type_long}', {$this->type_nr}, '{$this->name}');";
            $result = $db->Query($sql);
        }

    }

}

/**
 * Class representing the mensa's info
 * Class MensaInfo
 */
class MensaInfo {
    public $id;
    public $name;
    public $anschrift;
    /**
     * MensaInfo constructor.
     * @param $id
     * @param $name
     * @param $anschrift
     */
    public function __construct($id, $name, $anschrift)
    {
        $this->id = $id;
        $this->name = $name;
        $this->anschrift = $anschrift;
    }

     public function store($db){
        if ($this->id != ""){
            $sql = "INSERT INTO mensa_mensen VALUES ({$this->id}, '{$this->name}', '{$this->anschrift}');";
            $result = $db->Query($sql);
        }

    }
}

/**
 * Class representing the mensa
 *      'mensa_mensen' array containing the info on the mensa
 *      'mensa_menu' array containing the info on all the mensa's meals
 * Class Mensa
 */
class Mensa {
    public $mensa_mensen = array();
    public $mensa_menu = array();

    /**
     * Mensa constructor.
     * @param array $mensa_mensen
     * @param array $meals
     */
    public function __construct(array $mensa_mensen, array $meals)
    {
        $this->mensa_mensen = $mensa_mensen;
        $this->mensa_menu = $meals;
    }

}


class SplitMeal {
    public $price = "";
    public $name = "";

   

}
/**
 * Parser for pdf containing the restaurant menu for the week
 * @return string Text version of pfd
 */
function pdfToString(){
    $i = 0;
    $links = crawl_page("http://www.betriebsrestaurant-gmbh.de/index.php?id=91");
    $pdfLink = "";
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


function pdfToJSON() {
    $mensa = null;
    // Generating info on FMI Bistro
    $mensaInfo = new MensaInfo("666","FMI Bistro","Boltzmannstr. 2, Garching");
    // Array for parsed meals
    $meals = array();
    // Split the whole pdf string on the days
    $pdfText = pdfToString();
    $raw = preg_split("/\n\s*\n/", $pdfText);
    // Cleaning data
    $days = array_slice($raw, 4, count($raw)-7);
    // Only display today and future days
    $currentDayOfWeek = idate('w', time());

    $i = 1;
    foreach($days as $day) {
        if ($i >= $currentDayOfWeek) {
            // Getting daily menu
            $dayArray = preg_split("/\n\d[.]/", $day);
            $title = array_shift($dayArray);

            $dateTitles = preg_split("/[\s,]+/", $title);
            // Correcting date format to (YYYY-MM-DD)
            $realDate = getCorrectDataFormat($dateTitles[count($dateTitles)-2]);

            //Parsing meal data
            $i=1;
            foreach($dayArray as $meal) {
                $aMeal = new Meal();
                $aMeal->id= 1;
                $aMeal->date = $realDate;
                $aMeal->mensa_id = $mensaInfo->id;
                $splitMeal = splitMealFromPrice(preg_replace("/\d([,]\d*)* oder B.n.W./", "", $meal));
                // splitMeal->name  meal's name
                // splitMeal->price meal's price
                $aMeal->name = $splitMeal->name;
                $aMeal->type_short = $splitMeal->price;
                $aMeal->type_long = "Tagesgericht ".$i;
                $aMeal->type_nr = $i;
                //$aMeal->type_nr = $splitMeal->price;
                $meals[] = $aMeal;
                $i++;
            }
        }
        $i++;
    }
    $mensa = new Mensa(array($mensaInfo),$meals);
    //echo json_encode($mensa);
    return $mensa;
}

/**
 * Correctinf format from DD.MM.YYYY to YYYY-MM-DD
 * @param $date
 * @return string
 */
function getCorrectDataFormat($date) {
    $correctDate = "incorrect";
    $splitDate = explode(".", $date);//preg_split($date,'/[,.\s;]+/');

    if(count($splitDate) == 3) {
        $day = $splitDate[0];
        $month = $splitDate[1];
        $year = $splitDate[2];
        $correctDate = $year."-".$month."-".$day;
    }

    return $correctDate;
}

/**
 * Splitting Meal's name from the price
 * @param $mealString
 * @return null
 */
function splitMealFromPrice($mealString) {
    $splitString = explode(" ", $mealString);
    $price = $splitString[count($splitString)-2];
    $mealName = "";
    for ($i = 0; $i<count($splitString)-3; $i++) {
        $mealName .= $splitString[$i]." ";
    }
    $splitMeal = new SplitMeal();
    $splitMeal->name = $mealName;
    $splitMeal->price = $price;
    return $splitMeal;
}

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

?>


<?php
    //pdfToJSON();
?>

