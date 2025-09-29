<?php

namespace App\Utilitaire;

use PDO;

class Singleton_ConnexionPDO extends PDO
{
    protected static ?PDO $_PDO = null;

    private function __construct()
    {
        $myFile =  __DIR__ . '/../../paramBDD.txt';
        $lines = file($myFile);//file in to an array
        foreach($lines as $line)
        {
            $var = explode(' ', $line, 2);
            if(!isset($var[1]))
                $var[1] = "";
            $arr[$var[0]] = trim($var[1]);
        }

        parent::__construct('mysql:host='.$arr["IPBDD"].';dbname='.$arr["BDD"].';charset=utf8mb4',
            $arr["USERBDD"],
            $arr["MDPBDD"],
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ));

    }

    public static function getInstance(): PDO
    {

        if (is_null(self::$_PDO)) {
            self::$_PDO = new Singleton_ConnexionPDO();
        }
        return self::$_PDO;
    }
}
