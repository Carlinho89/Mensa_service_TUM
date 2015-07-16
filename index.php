<?php
ini_set('max_execution_time', 300);
include_once('simple_html_dom.php');
include 'bistroFMIParser.php';
require 'Slim/Slim.php';

require_once("DataBase.class.php");
      

define("URLBASE", "http://www.studentenwerk-muenchen.de/");


/**
*Object to hold a mensa_mensen
*
**/
class Mensa_Mensen{
    public $id  = "";
    public $name = "";
    public $anschrift ="";

    public function store($db){
        if ($this->id != ""){
            $sql = "INSERT INTO mensa_mensen VALUES ({$this->id}, '{$this->name}', '{$this->anschrift}');";
            $result = $db->Query($sql);
        }

    }

}

/**
 * Class representing the menu's info
 * 
 */
class Mensa_Menu{
    public $id = "";
    public $mensa_id = "";
    public $date = "";
    public $type_short = "";
    public $type_long = "";
    public $type_nr = "";
    public $name = "";

    public function __toString() {
            return "id: {$this->id}<br>".
            "mensa_id: {$this->mensa_id}<br>".
            "date: {$this->date}<br>".
            "type_short: {$this->type_short}<br>".
            "type_long: {$this->type_long}<br>".
            "type_nr: {$this->type_nr}<br>".
            "name: {$this->name}<br>";
    }
    /**
     * Function to save to db.
     * @param $db
     */
    public function store($db){
        if ($this->id != ""){
            $sql = "INSERT INTO mensa_menu VALUES 
            ({$this->id}, {$this->mensa_id}, '{$this->date}', '{$this->type_short}', '{$this->type_long}', {$this->type_nr}, '{$this->name}');";
            $result = $db->Query($sql);
        }

    }


}

/**
 * Class representing the beilagen's info
 * 
 */
class Mensa_Beilagen{
   
    public $mensa_id = "";
    public $date = "";
    public $type_short = "";
    public $type_long = "";
    public $name = "";

    public function __toString() {
            return "mensa_id: {$this->mensa_id}<br>".
            "date: {$this->date}<br>".
            "type_short: {$this->type_short}<br>".
            "type_long: {$this->type_long}<br>".
            "name: {$this->name}<br>";
    }

    /**
     * Function to save to db.
     * @param $db
     */
    public function store($db){
        if ($this->mensa_id != ""){
            $sql = "INSERT INTO mensa_beilagen VALUES 
            ( {$this->mensa_id}, '{$this->date}', '{$this->type_short}', '{$this->type_long}', '{$this->name}');";
            $result = $db->Query($sql);
        }

    }



}


/**
 * Class which will be json encoded for the result
 * 
 */
class Result{
    public $x_info = "TCA Web Service to parse Mensa"; 
    public $mensa_mensen = array();
    public $mensa_menu = array();
    public $mensa_beilagen = array();
    public $mensa_preise = array();
}





/**
*Framework for rest api
*
**/

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

//initializing db
$db =  new DataBase();





/**
 ************MAIN ENDPOINT***********
 *This endpoint can be called with mensaId = all or a specific mensaId
 *-it checks for state of db and calls the parsing if the content on the db is old
 *-the frequency of updating fmi is different from other mensas so it's updated separetly
 *
 */
$app->get('/list/:mensaId', function ($mensaId) use ($app, $db) {

    if($mensaId=="all"){
        
        //Check weather updating info on fmi on the db
        if(strtotime("today") > strtotime(getLatestDateFMI($db))){
            updateFMI($db);

        }
        
         //Check weather updating or using db
        if(strtotime("today") <= strtotime(getLatestDate($db))){

            $result = new Result();
            $result->mensa_mensen = getMensaList($db, "mensa_mensen");
            $result->mensa_menu = getMensaList($db, "mensa_menu");
     
            $result->mensa_beilagen = getMensaList($db, "mensa_beilagen");

            echo json_encode($result);

        }else{
        
            $app->redirect('./parse/'.$mensaId);
        }

        

       

    } else{
        $app->redirect('./parse/'.$mensaId);
    }
   
    
});


/**
 * Endpoint to parse the content from the studentenwerk mensa 
 * this call is intended to be executed just once a month
 * execution takes over a minute
 * while parsing it also fills the db
 */
$app->get('/list/parse/:mensaId', function ($mensaId) use ($db){
    $deutch = "http://www.studentenwerk-muenchen.de/mensa/speiseplan/index-de.html";
    $english = "http://www.studentenwerk-muenchen.de/mensa/speiseplan/index-en.html";
    $html = file_get_html($english);

    $mensen_list = $html->find('#c1582 p')[0];

    $result = new Result();
    $result->mensa_mensen = parseMensa_Mensen($mensen_list, $db);


    $mensen_links = mensaLinks($mensen_list, $mensaId);
    $all_daily_menus = getAllDaylyMenus($mensen_links);

   
    foreach ($all_daily_menus as $adm) {
        if($adm->type_short == "bio" || $adm->type_short == "bei" || $adm->type_short == "akt"){
            $beilagen = new Mensa_Beilagen();
            $beilagen->mensa_id =  $adm->mensa_id;
            $beilagen->date =  $adm->date;
            $beilagen->type_short =  $adm->type_short;
            $beilagen->type_long =  trim($adm->type_long);
            $beilagen->name =  trim($adm->name);

            $beilagen->store($db);
            array_push( $result->mensa_beilagen, $beilagen);
        } else{
            $adm->store($db);
            array_push( $result->mensa_menu, $adm);
        }
        
    }


    
    //Adding mensa fmi content
    $resultFMI= pdfToJSON();

    if($mensaId == "all" || $mensaId == "666"){
        foreach ($resultFMI->mensa_mensen as $mensen) {
            $mensen->store($db);
            array_push( $result->mensa_mensen, $mensen);
        }

        foreach ($resultFMI->mensa_menu as $menu) {
            $menu->store($db);
            array_push( $result->mensa_menu, $menu);
        } 
    }
   
    $json = json_encode($result);
    
    echo $json;    
     

});


/**
 *Static retrieval of list of menses and their position
 *-note: fmi is added at the end with mensaid = 666
 */
$app->get('/listmensen/', function () {    
     $template = <<<EOT
     [{"mensa":"5","id":"421","name":"Mensa Arcisstra\u00dfe","address":"Arcisstr. 17, M\u00fcnchen","latitude":"48.147312","longitude":"11.567229"},{"mensa":"6","id":"422","name":"Mensa Garching","address":"Lichtenbergstr. 2, Garching","latitude":"48.267509","longitude":"11.671278"},{"mensa":"1","id":"411","name":"Mensa Leopoldstra\u00dfe","address":"Leopoldstra\u00dfe 13a, M\u00fcnchen","latitude":"48.156586","longitude":"11.582004"},{"mensa":"8","id":"431","name":"Mensa Lothstra\u00dfe","address":"Lothstr. 13 d, M\u00fcnchen","latitude":"48.154003","longitude":"11.552526"},{"mensa":"2","id":"412","name":"Mensa Martinsried","address":"Gro\u00dfhaderner Stra\u00dfe 6, Planegg-Martinsried","latitude":"48.109894","longitude":"11.459931"},{"mensa":"9","id":"432","name":"Mensa Pasing","address":"Am Stadtpark 20, M\u00fcnchen","latitude":"48.141586","longitude":"11.450717"},{"mensa":"7","id":"423","name":"Mensa Weihenstephan","address":"Maximus-von-Imhof-Forum 5, Freising","latitude":"48.399590","longitude":"11.723350"},{"mensa":"3","id":"414","name":"Mensaria Gro\u00dfhadern","address":"Butenandtstr. 13 Geb\u00e4ude F, M\u00fcnchen","latitude":"48.113762","longitude":"11.467660"},{"mensa":"10","id":"441","name":"StuBistro Mensa Rosenheim","address":"Hochschulstr. 1, Rosenheim","latitude":"47.867451","longitude":"12.106990"},{"mensa":"4","id":"416","name":"StuBistro Schellingstra\u00dfe","address":"Schellingstr. 3, M\u00fcnchen","latitude":"48.149300","longitude":"11.579093"},{"mensa":"11","id":"512","name":"StuCaf\u00e9 Adalbertstra\u00dfe","address":"Adalbertstr. 5, M\u00fcnchen","latitude":"48.151428","longitude":"11.580292"},{"mensa":"14","id":"526","name":"StuCaf\u00e9 Akademie","address":"Alte Akademie 1, Freising","latitude":"48.395134","longitude":"11.728629"},{"mensa":"15","id":"527","name":"StuCaf\u00e9 Boltzmannstra\u00dfe","address":"Boltzmannstr. 15, Garching","latitude":"48.265842","longitude":"11.667780"},{"mensa":"16","id":"532","name":"StuCaf\u00e9 Karlstra\u00dfe","address":"Karlstr. 6, M\u00fcnchen","latitude":"48.142761","longitude":"11.568387"},{"mensa":"12","id":"524","name":"StuCaf\u00e9 Mensa Garching","address":"Lichtenbergstr. 2, Garching","latitude":"48.267509","longitude":"11.671278"},{"mensa":"13","id":"525","name":"StuCaf\u00e9 Mensa-WST","address":"Maximus-von-Imhof-Forum 5, Freising","latitude":"48.398453","longitude":"11.724441"},{"mensa":"99","id":"666","name":"FMI Bistro","address":"Boltzmannstr 3, Garching","latitude":"48.2622985","longitude":"11.6697764"}]
EOT;
 
    echo $template;

});

/**
 *Separate endpoint for fmi parsing (uses bistroFMIParser.php)
 *-note: fmi is added at the end with mensaid = 666
 */
$app->get('/listfmi/', function () {
    $result= pdfToJSON();
   
    echo json_encode($result);
 

});


$app->run();



/**
***************Functions for caching***************
*The contents can be cashed via file or via db. 
*In this implementation db is used so that the parsing of studentenwerk mensa
*only happens once a month
*/


/**
 * function to update the content of FMI cantine.
 * it's been realized in a separate function because it has to be updated
 * once a week (while sw mensa is updated once a month)
 */
function updateFMI($db){

    $resultFMI= pdfToJSON();

    
        foreach ($resultFMI->mensa_mensen as $mensen) {
            $mensen->store($db);
        }

        foreach ($resultFMI->mensa_menu as $menu) {
            $menu->store($db);
        } 
    
}

/**
 * Checks latest fmi data added to decide weather to use cached info or parsing
 *
 */
function getLatestDateFMI($db){
    $sql = "SELECT MAX(m.date) as date FROM mensa_menu m where mensa_id = 666 ";

    $result = $db->Query($sql);
    
    if (!$result) {
        
        $result = "";
    
    }else{

        $row = $result->fetch_assoc();
        $result = $row["date"];
    }

    return $result;
}

/**
 * Checks latest studentenwerk data added to decide weather to use cached info or parsing
 *
 */
function getLatestDate($db){
    $sql = "SELECT MAX(m.date) as date from (select date from mensa_beilagen  UNION SELECT date FROM mensa_menu where mensa_id <> 666) m";

    $result = $db->Query($sql);
    
    if (!$result) {
        
        $result = "";
    
    }else{

        $row = $result->fetch_assoc();
        $result = $row["date"];
    }

    return $result;
}

/**
 * Retrieves cached copy from file
 *
 */
function getFromFile(){
    $filename = strtotime("today").".txt";
    if (file_exists($filename)) {
        $myfile = fopen($filename, "r") or die("Unable to open file!");
        echo fread($myfile,filesize($filename));
        fclose($myfile);
        return true;
    }
    return false;
}

/**
 * writes cached copy to file and deletes previous caches
 *
 */
function writeToFile($json){
    $filename = strtotime("today").".txt";
    $filename2 = strtotime("yesterday").".txt";

    $myfile = fopen($filename, "w") or die("Unable to open file!");
    
    fwrite($myfile, $json);

    fclose($myfile);

    
    if (file_exists($filename2)) {
        unlink($filename2);
    }


}



/**
 * Function to query the db and populate the json result
  * @param $table = name of the table being retrieved
 */
function getMensaList($db, $table){
    if($table == "mensa_mensen"){
        $sql = "SELECT * FROM ".$table;
    }else{
        $sql= "SELECT * FROM ".$table." m WHERE date(m.date) >= CURRENT_DATE ORDER BY m.date";        
    }
    
    $mensa_list = array();
    $result = $db->Query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            array_push($mensa_list, $row);
       }
    
    } 
    

    return $mensa_list;

}



/**
*Functions to parse MENSA studentenwerk
*/
function mensaLinks($mensen_list, $mensaId){

 $mensalinks = array();
    foreach($mensen_list->find('a') as $element){

        if($element->plaintext!="today" && $element->plaintext!="heute" ){
            $id  = preg_replace("/[^0-9]/","",$element->href);
          
            if($id != ""){
                if($mensaId == "all"){
                    array_push($mensalinks, $element->href);
                }
                elseif($mensaId == $id){
                    array_push($mensalinks, $element->href);
                }
                
            }        
        }
        
    }
    return $mensalinks;

}



function parseMensa_Mensen($mensen_list,$db)
{


    $addresses = array();


    foreach($mensen_list->find('text') as $element){
    if (substr($element, 0, 1) === ')')
        //echo $element->href . '<br>';
        //echo $element->plaintext . '<br>';
        array_push($addresses, substr($element, 3));
        //echo substr($element, 3).'<br>';
    }

    
    $mensalist=array();
   
    foreach($mensen_list->find('a') as $element){

        if($element->plaintext!="today"){
           /* $listelem = new MensaListElement();
            $listelem->name = $element->plaintext;
            $listelem->link  = $element->href;
            $listelem->address = array_shift($addresses);
    */
            $mensen = new Mensa_Mensen();
            $mensen->name = trim($element->plaintext);
            
            //filter_var($element->href, FILTER_SANITIZE_NUMBER_INT);
            $mensen->id  = preg_replace("/[^0-9]/","",$element->href);
            $mensen->anschrift = trim(array_shift($addresses));
            if($mensen->id != ""){
                $mensen->store($db);
                array_push($mensalist, $mensen);
            }
            
             
        }
        
    }
    return $mensalist;
}


/**
*Functions to fetch MENSA studentenwerk daily links from current date
* @param $links = list of mensen links
*/
function mensaRemainingDailyLinks($links){
     $mensa_beilagen_links= array();
     foreach ($links as $link) {

        $html = file_get_html(URLBASE . $link);
        $menus = $html->find('.menu');
            foreach ($menus as $menu){

                 
                /**
                *Headline parsing to retrieve link to the daily beilagen
                *and date
                *
                */
                $headline = $menu->find('.headline', 1);
                $a= $headline->find('a',0);
                $date = filter_var($a->class, FILTER_SANITIZE_NUMBER_INT);

                //Change to this to get only next ten days
                //if(strtotime($date)>=strtotime('today') && strtotime($date)<=strtotime('now +10 days')){
                if(strtotime($date)>=strtotime('today')){
                    $link_beilagen = $headline -> find('a', 1)->href;
                    array_push($mensa_beilagen_links, $link_beilagen);
                }
               
            }


     }
    
     return $mensa_beilagen_links;              



}




function mensaDailyLinks($links){
     $mensa_beilagen_links= array();
     foreach ($links as $link) {

        $html = file_get_html(URLBASE . $link);
        $menus = $html->find('.menu');
            foreach ($menus as $menu){

                 
                /**
                *Headline parsing to retrieve link to the daily beilagen
                *and date
                *
                */
                $headline = $menu->find('.headline', 1);

                $link_beilagen = $headline -> find('a', 1)->href;
                array_push($mensa_beilagen_links, $link_beilagen);
            }


     }
    
           
  return $mensa_beilagen_links;              

}



/**
*Functions to parse MENSA studentenwerk daily link
* @param $mdl = mensa daily link
*/
function parseDailyLink($mdl){
    $html = file_get_html(URLBASE . $mdl);
    $menu = $html->find('.menu',0);
    $mensa_menus = array();

    $mensa_id = substr($mdl, strlen($mdl)-12, 3);


     
    /**
    *Headline parsing to retrieve id
    *and date
    *
    */

    $headline = $menu->find('.headline', 1);
    $a= $headline->find('a',0);
    $date = filter_var($a->class, FILTER_SANITIZE_NUMBER_INT);



    

    /**
    *Parsing of each table row (except headline) to retrieve
    *mensamenu element
    *
    */

    
    $rows = $menu->find('tr');
    $index=0;
    foreach ($rows as $row) {
        if($row != $rows[0]){
            $type_long= $row->find('td',0)->plaintext;
            $name= "".$row->find('td',1)->plaintext;


            if (strpos($type_long,'Aktionsessen') !== false) {
                 
                 $type_short = "ae";

                 
            } elseif(strpos($type_long,'Biogericht') !== false) {

                $type_short = "bg";

            } elseif(strpos($type_long,'Tagesgericht') !== false) {

                $type_short = "tg";
            
            } elseif(strpos($type_long,'Beilagen') !== false) {

                $type_short = "bei";
            
            } elseif(strpos($type_long,'Aktion') !== false && strpos($type_long,'Aktionsessen') == false) {

                $type_short = "akt";
            
            } elseif(strpos($type_long,'Bio') !== false && strpos($type_long,'Biogericht') == false) {

                $type_short = "bio";
            
            } else{
                
                $type_short = "oth";
            }



            

            
            
            $type_nr = "".preg_replace("/[^0-9]/","",$type_long);

            $mensa_menu = new Mensa_Menu();
           
            $mensa_menu->mensa_id = $mensa_id;
            $mensa_menu->date = $date;
            $mensa_menu->type_short = $type_short;
            $mensa_menu->type_long = trim($type_long);
            $mensa_menu->type_nr = $type_nr;
            
            $temp = trim(str_replace(array( "\n", "\t", "\r", " with meat ", "  " ), '', $name));
            $mensa_menu->name = str_replace( "(v)", '', $temp);
            //echo $mensa_menu;
          
            array_push($mensa_menus, $mensa_menu);
            unset($mensa_menu);
            
            
            
           
        }
        
    }
       
        /**
    *I use this to fix some empty values that the html is giving
    */
    for ($i = 0; $i < count($mensa_menus); $i++) {
        $mensa_menus[$i]->id=$i;
       if($mensa_menus[$i]->type_long == ""){
        $mensa_menus[$i]->type_long =$mensa_menus[$i-1]->type_long;
        $mensa_menus[$i]->type_short =$mensa_menus[$i-1]->type_short;
        $mensa_menus[$i]->type_nr =$mensa_menus[$i-1]->type_nr;
   


       } 
    }


    for ($i = 0; $i < count($mensa_menus); $i++) {
       if($mensa_menus[$i]->type_short == "oth"){
        $mensa_menus[$i]->type_long = "Other 1";
        //$mensa_menus[$i]->type_short = "oth";
        $mensa_menus[$i]->type_nr = "1";
       } 

    }


            
    return $mensa_menus;
}



function getAllDaylyMenus($mensen_links){
     $all_daily_menus = array();
    /**
    *Use mensaDailyLinks() to get all the results,
    *mensaRemainingDailyLinks() to get the days from current
    */
    //$mensen_daily_links = mensaDailyLinks($mensen_links);
    $mensen_daily_links = mensaRemainingDailyLinks($mensen_links);
  
    
    foreach ($mensen_daily_links as $mdl) {             
        $daily=parseDailyLink($mdl);
        foreach ($daily as $d) {
            array_push( $all_daily_menus, $d);
        }
        
    }
    return $all_daily_menus;

}
    


 








?>