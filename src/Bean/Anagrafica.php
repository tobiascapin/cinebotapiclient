<?php 

namespace Cinebot;


class Anagrafica {
    public $cognome;
    public $nome;
    public $email;
    public $telefono=null;
    public $indirizzo=null;
    public $cap=null;
    public $citta=null;
    public $luogonascita=null;
    public $datanascita=null;
    public $note=null;
    public $registrazione=null;
    public $ipregistrazione=null;
    public $autenticazione=null;
    public $marketing=false;
    public $riferi1=null;
    public $riferi2=null;
    public $riferi3=null;
    
    function __construct($cognome, $nome, $email) {
        $this->cognome=$cognome;
        $this->nome=$nome;
        $this->email=$email;
    }
}