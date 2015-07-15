<?php

 
class DataBase
{


    const DB_host = 'localhost';
    const DB_user = 'root';
    const DB_password='';
    const DB_name= 'mensawebservice';
    
    
    private $connected = false; 
    private $lastConnection;    
    private $dbhost,            
            $dbuser,           
            $dbpwd,             
            $dbname;            
 
 
    public function __construct()
    {
       
 
        
        $this->dbhost = self::DB_host;
        $this->dbuser = self::DB_user;
        $this->dbpwd = self::DB_password;
        $this->dbname = self::DB_name;
    }
 
 
 
 
    
    public function OpenConnection()
    {
        
        if ($this->connected)
            return $this->lastConnection;
 
       
        $link = new mysqli($this->dbhost, $this->dbuser, $this->dbpwd, $this->dbname);
 
        
        if ($link)
        {
            
            $this->connected = true;
            
            $this->lastConnection = $link;
           
            return $link;
        }
        
        return false;
    }
 
 
 
 
 
 
    
    public function CloseConnection($link=null)
    {
        
        if (!$this->connected)
            return true;
 
        if (!$link)
            $link = $this->lastConnection;
 
        if ($link->close())
        {
            $this->connected = false;
            return true;
        }
        return false;
    }
 
 
 
 

 
 
 
 
 
 
   
    
     public function Query($query)
    {
        
        $link = $this->OpenConnection();
        if (!$link)
            return false;

        
        $link->autocommit(true);
        $result =  $link->query($query);
        if (!$result)
            return false;
 
       
        if (!$this->CloseConnection())
            return false;
 
       
        return $result;
    }
 
 
 
 
 
   
    public function NumRows($query)
    {
        
        $result = $this->Query($query);
 
        return $result->num_rows ;
    }
 
 
  
}
?>
