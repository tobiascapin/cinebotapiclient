<?php 

namespace Cinebot;


class Abbonamento {
    
    public $id;
    public $tipoabbonamento;
    public $qta=1;
    public $posti=[];
    
    function __construct($tipoabbonamento, $qta, $posti=[]) {
        $this->tipoabbonamento=$tipoabbonamento;
        $this->qta=$qta;
        $this->posti=$posti;
    }
}