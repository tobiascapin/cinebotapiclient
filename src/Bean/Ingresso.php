<?php 

namespace Cinebot;


class Ingresso {
    
    public $id;
    public $settore;
    public $prezzo;
    public $abbonamento=null;
    public $qta=1;
    public $posti=[];
    
    function __construct($settore, $prezzo, $abbonamento, $qta, $posti=[]) {
        $this->settore=$settore;
        $this->prezzo=$prezzo;
        $this->abbonamento=$abbonamento;
        $this->qta=$qta;
        $this->posti=$posti;
    }
}