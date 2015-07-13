<?php
ini_set('max_execution_time', 300);
include_once('simple_html_dom.php');
include 'bistroFMIParser.php';
require 'Slim/Slim.php';

define("URLBASE", "http://www.studentenwerk-muenchen.de/");

class MensaListElement {
    public $name = "";
    public $link  = "";
    public $address ="";
    
}
/**
*Object to hold a mensa_mensen
*
**/

class Mensa_Mensen{
    public $id  = "";
    public $name = "";
    public $anschrift ="";

}

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


}

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


}



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


/**
*returns the list of mensas
*
**/

$app->get('/list/:mensaId', function ($mensaId) {
    $deutch = "http://www.studentenwerk-muenchen.de/mensa/speiseplan/index-de.html";
    $english = "http://www.studentenwerk-muenchen.de/mensa/speiseplan/index-en.html";
    $html = file_get_html($english);

    $mensen_list = $html->find('#c1582 p')[0];

    $result = new Result();
    $result->mensa_mensen = parseMensa_Mensen($mensen_list);


    $mensen_links = mensaLinks($mensen_list, $mensaId);
    
    /**
    *Use mensaDailyLinks() to get all the results,
    *mensaRemainingDailyLinks() to get the days still to come
    */
    $mensen_daily_links = mensaDailyLinks($mensen_links);
    $mensen_daily_links = mensaRemainingDailyLinks($mensen_links);
    $all_daily_menus = array();
    foreach ($mensen_daily_links as $mdl) {             
        $daily=parseDailyLink($mdl);
        foreach ($daily as $d) {
            array_push( $all_daily_menus, $d);
        }
        
    }

    foreach ($all_daily_menus as $adm) {
        if($adm->type_short == "bio" || $adm->type_short == "bei" || $adm->type_short == "akt"){
            $beilagen = new Mensa_Beilagen();
            $beilagen->mensa_id =  $adm->mensa_id;
            $beilagen->date =  $adm->date;
            $beilagen->type_short =  $adm->type_short;
            $beilagen->type_long =  $adm->type_long;
            $beilagen->name =  $adm->name;


            array_push( $result->mensa_beilagen, $beilagen);
        } else{
            array_push( $result->mensa_menu, $adm);
        }
        
    }

    /*
    *Adding mensa fmi content
    *
    */

    $resultFMI= pdfToJSON();

    foreach ($resultFMI->mensa_mensen as $mensen) {
        array_push( $result->mensa_mensen, $mensen);
    }

    foreach ($resultFMI->mensa_menu as $menu) {
        array_push( $result->mensa_menu, $menu);
    }

    echo json_encode($result);    
 

});



$app->get('/listmensen/', function () {
   
   
    
     $template = <<<EOT
     [{"mensa":"5","id":"421","name":"Mensa Arcisstra\u00dfe","address":"Arcisstr. 17, M\u00fcnchen","latitude":"48.147312","longitude":"11.567229"},{"mensa":"6","id":"422","name":"Mensa Garching","address":"Lichtenbergstr. 2, Garching","latitude":"48.267509","longitude":"11.671278"},{"mensa":"1","id":"411","name":"Mensa Leopoldstra\u00dfe","address":"Leopoldstra\u00dfe 13a, M\u00fcnchen","latitude":"48.156586","longitude":"11.582004"},{"mensa":"8","id":"431","name":"Mensa Lothstra\u00dfe","address":"Lothstr. 13 d, M\u00fcnchen","latitude":"48.154003","longitude":"11.552526"},{"mensa":"2","id":"412","name":"Mensa Martinsried","address":"Gro\u00dfhaderner Stra\u00dfe 6, Planegg-Martinsried","latitude":"48.109894","longitude":"11.459931"},{"mensa":"9","id":"432","name":"Mensa Pasing","address":"Am Stadtpark 20, M\u00fcnchen","latitude":"48.141586","longitude":"11.450717"},{"mensa":"7","id":"423","name":"Mensa Weihenstephan","address":"Maximus-von-Imhof-Forum 5, Freising","latitude":"48.399590","longitude":"11.723350"},{"mensa":"3","id":"414","name":"Mensaria Gro\u00dfhadern","address":"Butenandtstr. 13 Geb\u00e4ude F, M\u00fcnchen","latitude":"48.113762","longitude":"11.467660"},{"mensa":"10","id":"441","name":"StuBistro Mensa Rosenheim","address":"Hochschulstr. 1, Rosenheim","latitude":"47.867451","longitude":"12.106990"},{"mensa":"4","id":"416","name":"StuBistro Schellingstra\u00dfe","address":"Schellingstr. 3, M\u00fcnchen","latitude":"48.149300","longitude":"11.579093"},{"mensa":"11","id":"512","name":"StuCaf\u00e9 Adalbertstra\u00dfe","address":"Adalbertstr. 5, M\u00fcnchen","latitude":"48.151428","longitude":"11.580292"},{"mensa":"14","id":"526","name":"StuCaf\u00e9 Akademie","address":"Alte Akademie 1, Freising","latitude":"48.395134","longitude":"11.728629"},{"mensa":"15","id":"527","name":"StuCaf\u00e9 Boltzmannstra\u00dfe","address":"Boltzmannstr. 15, Garching","latitude":"48.265842","longitude":"11.667780"},{"mensa":"16","id":"532","name":"StuCaf\u00e9 Karlstra\u00dfe","address":"Karlstr. 6, M\u00fcnchen","latitude":"48.142761","longitude":"11.568387"},{"mensa":"12","id":"524","name":"StuCaf\u00e9 Mensa Garching","address":"Lichtenbergstr. 2, Garching","latitude":"48.267509","longitude":"11.671278"},{"mensa":"13","id":"525","name":"StuCaf\u00e9 Mensa-WST","address":"Maximus-von-Imhof-Forum 5, Freising","latitude":"48.398453","longitude":"11.724441"},{"mensa":"99","id":"666","name":"FMI Bistro","address":"Boltzmannstr 3, Garching","latitude":"48.2622985","longitude":"11.6697764"}]
EOT;
 
    echo $template;

});


$app->get('/listfmi/', function () {
    $result= pdfToJSON();
   
    echo json_encode($result);
 

});





$app->get('/hello/:name', function ($name) {
    echo "Hello, " . $name;
});
// GET route
$app->get(
    '/',
    function () {
        $template = <<<EOT
<!DOCTYPE html>
    <html>
        <head>
            <meta charset="utf-8"/>
            <title>Slim Framework for PHP 5</title>
            <style>
                html,body,div,span,object,iframe,
                h1,h2,h3,h4,h5,h6,p,blockquote,pre,
                abbr,address,cite,code,
                del,dfn,em,img,ins,kbd,q,samp,
                small,strong,sub,sup,var,
                b,i,
                dl,dt,dd,ol,ul,li,
                fieldset,form,label,legend,
                table,caption,tbody,tfoot,thead,tr,th,td,
                article,aside,canvas,details,figcaption,figure,
                footer,header,hgroup,menu,nav,section,summary,
                time,mark,audio,video{margin:0;padding:0;border:0;outline:0;font-size:100%;vertical-align:baseline;background:transparent;}
                body{line-height:1;}
                article,aside,details,figcaption,figure,
                footer,header,hgroup,menu,nav,section{display:block;}
                nav ul{list-style:none;}
                blockquote,q{quotes:none;}
                blockquote:before,blockquote:after,
                q:before,q:after{content:'';content:none;}
                a{margin:0;padding:0;font-size:100%;vertical-align:baseline;background:transparent;}
                ins{background-color:#ff9;color:#000;text-decoration:none;}
                mark{background-color:#ff9;color:#000;font-style:italic;font-weight:bold;}
                del{text-decoration:line-through;}
                abbr[title],dfn[title]{border-bottom:1px dotted;cursor:help;}
                table{border-collapse:collapse;border-spacing:0;}
                hr{display:block;height:1px;border:0;border-top:1px solid #cccccc;margin:1em 0;padding:0;}
                input,select{vertical-align:middle;}
                html{ background: #EDEDED; height: 100%; }
                body{background:#FFF;margin:0 auto;min-height:100%;padding:0 30px;width:440px;color:#666;font:14px/23px Arial,Verdana,sans-serif;}
                h1,h2,h3,p,ul,ol,form,section{margin:0 0 20px 0;}
                h1{color:#333;font-size:20px;}
                h2,h3{color:#333;font-size:14px;}
                h3{margin:0;font-size:12px;font-weight:bold;}
                ul,ol{list-style-position:inside;color:#999;}
                ul{list-style-type:square;}
                code,kbd{background:#EEE;border:1px solid #DDD;border:1px solid #DDD;border-radius:4px;-moz-border-radius:4px;-webkit-border-radius:4px;padding:0 4px;color:#666;font-size:12px;}
                pre{background:#EEE;border:1px solid #DDD;border-radius:4px;-moz-border-radius:4px;-webkit-border-radius:4px;padding:5px 10px;color:#666;font-size:12px;}
                pre code{background:transparent;border:none;padding:0;}
                a{color:#70a23e;}
                header{padding: 30px 0;text-align:center;}
            </style>
        </head>
        <body>
            <header>
                <a href="http://www.slimframework.com"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHIAAAA6CAYAAABs1g18AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABRhJREFUeNrsXY+VsjAMR98twAo6Ao4gI+gIOIKOgCPICDoCjCAjXFdgha+5C3dcv/QfFB5i8h5PD21Bfk3yS9L2VpGnlGW5kS9wJMTHNRxpmjYRy6SycgRvL18OeMQOTYQ8HvIoJKiiz43hgHkq1zvK/h6e/TyJQXeV/VyWBOSHA4C5RvtMAiCc4ZB9FPjgRI8+YuKcrySO515a1hoAY3nc4G2AH52BZsn+MjaAEwIJICKAIR889HljMCcyrR0QE4v/q/BVBQva7Q1tAczG18+x+PvIswHEAslLbfGrMZKiXEOMAMy6LwlisQCJLPFMfKdBtli5dIihRyH7A627Iaiq5sJ1ThP9xoIgSdWSNVIHYmrTQgOgRyRNqm/M5PnrFFopr3F6B41cd8whRUSufUBU5EL4U93AYRnIWimCIiSI1wAaAZpJ9bPnxx8eyI3Gt4QybwWa6T/BvbQECUMQFkhd3jSkPFgrxwcynuBaNT/u6eJIlbGOBWSNIUDFEIwPZFAtBfYrfeIOSRSXuUYCsprCXwUIZWYnmEhJFMIocMDWjn206c2EsGLCJd42aWSyBNMnHxLEq7niMrY2qyDbQUbqrrTbwUPtxN1ZZCitQV4ZSd6DyoxhmRD6OFjuRUS/KdLGRHYowJZaqYgjt9Lchmi3QYA/cXBsHK6VfWNR5jgA1DLhwfFe4HqfODBpINEECCLO47LT/+HSvSd/OCOgQ8qE0DbHQUBqpC4BkKMPYPkFY4iAJXhGAYr1qmaqQDbECCg5A2NMchzR567aA4xcRKclI405Bmt46vYD7/Gcjqfk6GP/kh1wovIDSHDfiAs/8bOCQ4cf4qMt7eH5Cucr3S0aWGFfjdLHD8EhCFvXQlSqRrY5UV2O9cfZtk77jUFMXeqzCEZqSK4ICkSin2tE12/3rbVcE41OBjBjBPSdJ1N5lfYQpIuhr8axnyIy5KvXmkYnw8VbcwtTNj7fDNCmT2kPQXA+bxpEXkB21HlnSQq0gD67jnfh5KavVJa/XQYEFSaagWwbgjNA+ywstLpEWTKgc5gwVpsyO1bTII+tA6B7BPS+0PiznuM9gPKsPVXbFdADMtwbJxSmkXWfRh6AZhyyzBjIHoDmnCGaMZAKjd5hyNJYCBGDOVcg28AXQ5atAVDO3c4dSALQnYblfa3M4kc/cyA7gMIUBQCTyl4kugIpy8yA7ACqK8Uwk30lIFGOEV3rPDAELwQkr/9YjkaCPDQhCcsrAYlF1v8W8jAEYeQDY7qn6tNGWudfq+YUEr6uq6FZzBpJMUfWFDatLHMCciw2mRC+k81qCCA1DzK4aUVfrJpxnloZWCPVnOgYy8L3GvKjE96HpweQoy7iwVQclVutLOEKJxA8gaRCjSzgNI2zhh3bQhzBCQQPIHGaHaUd96GJbZz3Smmjy16u6j3FuKyNxcBarxqWWfYFE0tVVO1Rl3t1Mb05V00MQCJ71YHpNaMcsjWAfkQvPPkaNC7LqTG7JAhGXTKYf+VDeXAX9IvURoAwtTFHvyYIxtnd5tPkywrPafcwbeSuGVwFau3b76NO7SHQrvqhfFE8kM0Wvpv8gVYiYBlxL+fW/34bgP6bIC7JR7YPDubcHCPzIp4+cum7U6NlhZgK7lua3KGLeFwE2m+HblDYWSHG2SAfINuwBBfxbJEIuWZbBH4fAExD7cvaGVyXyH0dhiAYc92z3ZDfUVv+jgb8HrHy7WVO/8BFcy9vuTz+nwADAGnOR39Yg/QkAAAAAElFTkSuQmCC" alt="Slim"/></a>
            </header>
            <h1>Welcome to Slim!</h1>
            <p>
                Congratulations! Your Slim application is running. If this is
                your first time using Slim, start with this <a href="http://docs.slimframework.com/#Hello-World" target="_blank">"Hello World" Tutorial</a>.
            </p>
            <section>
                <h2>Get Started</h2>
                <ol>
                    <li>The application code is in <code>index.php</code></li>
                    <li>Read the <a href="http://docs.slimframework.com/" target="_blank">online documentation</a></li>
                    <li>Follow <a href="http://www.twitter.com/slimphp" target="_blank">@slimphp</a> on Twitter</li>
                </ol>
            </section>
            <section>
                <h2>Slim Framework Community</h2>

                <h3>Support Forum and Knowledge Base</h3>
                <p>
                    Visit the <a href="http://help.slimframework.com" target="_blank">Slim support forum and knowledge base</a>
                    to read announcements, chat with fellow Slim users, ask questions, help others, or show off your cool
                    Slim Framework apps.
                </p>

                <h3>Twitter</h3>
                <p>
                    Follow <a href="http://www.twitter.com/slimphp" target="_blank">@slimphp</a> on Twitter to receive the very latest news
                    and updates about the framework.
                </p>
            </section>
            <section style="padding-bottom: 20px">
                <h2>Slim Framework Extras</h2>
                <p>
                    Custom View classes for Smarty, Twig, Mustache, and other template
                    frameworks are available online in a separate repository.
                </p>
                <p><a href="https://github.com/codeguy/Slim-Extras" target="_blank">Browse the Extras Repository</a></p>
            </section>
        </body>
    </html>
EOT;
        echo $template;
    }
);

// POST route
$app->post(
    '/post',
    function () {
        echo 'This is a POST route';
    }
);

// PUT route
$app->put(
    '/put',
    function () {
        echo 'This is a PUT route';
    }
);

// PATCH route
$app->patch('/patch', function () {
    echo 'This is a PATCH route';
});

// DELETE route
$app->delete(
    '/delete',
    function () {
        echo 'This is a DELETE route';
    }
);

/**
 * Step 4: Run the Slim application
 *
 * This method should be called last. This executes the Slim application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();






/**
*Functions to parse MENSA studentenwerk
*/

function mensaLinks($es, $mensaId){

 $mensalinks = array();
    foreach($es->find('a') as $element){

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

function parseMensa_Mensen($es)
{


    $addresses = array();


    foreach($es->find('text') as $element){
    if (substr($element, 0, 1) === ')')
        //echo $element->href . '<br>';
        //echo $element->plaintext . '<br>';
        array_push($addresses, substr($element, 3));
        //echo substr($element, 3).'<br>';
    }

    
    $mensalist=array();
   
    foreach($es->find('a') as $element){

        if($element->plaintext!="today"){
           /* $listelem = new MensaListElement();
            $listelem->name = $element->plaintext;
            $listelem->link  = $element->href;
            $listelem->address = array_shift($addresses);
    */
            $mensen = new Mensa_Mensen();
            $mensen->name = $element->plaintext;
            
            //filter_var($element->href, FILTER_SANITIZE_NUMBER_INT);
            $mensen->id  = preg_replace("/[^0-9]/","",$element->href);
            $mensen->anschrift = array_shift($addresses);
            if($mensen->id != ""){
                
                array_push($mensalist, $mensen);
            }
            
             
        }
        
    }
    return $mensalist;
}


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
                if(strtotime($date)>=strtotime('now')){
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
                $type_short = "??";
            }



            


            
            $type_nr = preg_replace("/[^0-9]/","",$type_long);

            $mensa_menu = new Mensa_Menu();
            $mensa_menu->id = "";
            $mensa_menu->mensa_id = $mensa_id;
            $mensa_menu->date = $date;
            $mensa_menu->type_short = $type_short;
            $mensa_menu->type_long = $type_long;
            $mensa_menu->type_nr = $type_nr;
            
            $mensa_menu->name = str_replace(array( "\n", "\t", "\r"), '', $name);
            
            //echo $mensa_menu;
            array_push($mensa_menus, $mensa_menu);
            unset($mensa_menu);

            
            
           
        }
        
    }
       
        /**
    *I use this to fix some empty values that the html is giving
    */
    for ($i = 0; $i < count($mensa_menus); $i++) {
       if($mensa_menus[$i]->type_long == " "){
        $mensa_menus[$i]->type_long =$mensa_menus[$i-1]->type_long;
        $mensa_menus[$i]->type_short =$mensa_menus[$i-1]->type_short;
        $mensa_menus[$i]->type_nr =$mensa_menus[$i-1]->type_nr;
   


       } 
    }
            
    return $mensa_menus;
}








?>