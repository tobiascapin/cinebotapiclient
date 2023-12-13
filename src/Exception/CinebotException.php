<?php
namespace Cinebot\Exception;

class CinebotException extends \ErrorException
{
    public function __construct($msg){
        if($msg instanceof \Exception)
            parent::__construct($msg->getMessage());
        else
            parent::__construct($msg);
    }
}