<?php 

namespace Cinebot;

use Cinebot\Exception\CinebotException;
use Monolog\Logger;
use Cinebot\Exception\LogicalException;

/**
 * Classe di collegamento remoto al sistema fiscale per recupero dati e emissione titoli
 * 
 * @author tobia.scapin
 * @require CURL PHP extension
 * 
 * esempio di utilizzo:
 * 		
 * 		$cac=new \Cinebot\CinebotApiClient("https://1.2.3.4:8443", "2", "secrepasswordkey", $options);
 * 		$programmazione=$cac->getProgrammazione();
 *
 */
class CinebotApiClient {
	
    const VERSION="2.1.0 1";
	
	private $url;
	private $idpv;
	private $passkey;
	private $timeout=10;
	private ?Logger $logger=null;
	
	/**
	 * Inizializza un client remoto per collegare il sistema
	 * 
	 * @param string $url				url nel formato http[s]://host:port es https://1.2.3.4:8080
	 * @param string/number $idpv		numero client remoto registrato a sistema
	 * @param string $passkey			password del client remoto
	 * @param array $options            opzioni aggiuntive ["logger"=>$monologObj,"timeout"=>10]
	 */
	public function __construct($url, $idpv, $passkey, $options=[]) {
       if(filter_var($url, FILTER_VALIDATE_URL)===false)
       		throw new \Exception(_("Url non valido"));
       $this->url=$url;
       $this->idpv=$idpv;
       $this->passkey=$passkey;
       if($options["logger"])
           $this->logger=$options["logger"];
       if($options["timeout"])
           $this->timeout=$options["timeout"];
	}
	
	/**
	 * Ottiene i dati del sistema, come stato, versione, ultima programmazione, ultimo abbonamento
	 * @return object
	 * 
	 * Esempio oggetto risposta
	 * 		{"codicesistema":"00012345","utente":"test@cinebot.it","password":null,"ready":true,"versione":"2.1.0 1 1111","stepprog":0,"stepabb":0,"stepupdate":0}
	 * 			codicesistema: 	string codice siae
	 * 			utente:			string utente cinebot
	 * 			password:		sempre null
	 * 			ready:			boolean sistema pronto o non pronto
	 * 			versione:		string da separare con spazio con valori di: versione fiscale, db, build
	 * 			stepprog:		number progressivo per aggiornamento della programmazione
	 * 			stepabb:		number progressivo per aggiornamento abbonamenti
	 *	 		stepupdate:		number progressivo per aggiornamento remoto sistema (ignorare)
	 */
	public function ping($timeout=false) {
		$ping=$this->_get("ping",[],$timeout,$timeout);
		return $ping;
	}
	
	/**
	 * Verifica se il sistema è accesso e pronto per emissioni
	 * @return boolean
	 */
	public function isReady($timeout=false) : bool {
		$ping=$this->ping($timeout);
		return $ping && $ping->ready;
	}
	

	/**
	 * Verifica l'utente locale per SSO con portale
	 * @param string $userid		Riferimento all'utente locale
	 * @param string $sessionref	Riferimento alla sessione locale
     * @return boolean
	 */
	public function verifySSO($userid,$session){
		$res=$this->_get("sso",["userid"=>$userid,"session"=>$session]);
		return $res;
	}
	
	/**
	 * Ottiene la programmazione del sistema
	 * @return [stepprog: int, array titoli, array tipiabbonamenti]
	 * 
	 * Esempio oggetto risposta
	 * 		{stepprog: 1, titoli:[{"id":10,"titolo":"Titolo Evento","autore":null,"esecutore":null,"distributore":null,"durata":120,"descrizione":null,"locandina":null,"note":null,"eventi":[{"id":4,"inizio":1464724800000,"locale":"Sala1","mappa":null,"settori":[{"id":1,"settore":"Posto unico","prezzi":[{"id":4,"prezzo":"Intero","tipo":"I","importo":9.5}]}]},{"id":5,"inizio":1464771600000,"locale":"Sala2","mappa":1,"settori":[{"id":1,"settore":"Posto unico","prezzi":[{"id":4,"prezzo":"Intero","tipo":"I","importo":9.5}]}]}]}]}
	 *
	 * Esempio array titoli
	 * 			id:				number codice titolo
	 * 			titolo:			string titolo dell'evento
	 * 			autore:			string (opzionale) autore evento
	 * 			esecutore:		string (opzionale) esecutore evento
	 * 			distributore:	string (opzionale) distributore evento
	 * 			durata:			number minuti durata
	 * 			descrizione:	string (opzionale) descrizione o trama
	 * 			locandina:		string (opzionale) base64 encoded jpeg
	 * 			note:			string (opzionale) note
	 * 			eventi:			array
	 * 				id:				number codice evento
	 * 				inizio:			number unixtime*1000 dell'inizio evento
	 * 				localeid:		numbeer codice locale
	 * 				locale:			string nome locale
	 * 				mappa:			number (opzionale) codice mappa posti numerati
     * 				slot:			number 1=evento gestito a slot di accesso
	 * 				stato:			number 1=pubblicato 2=prenotabile 3=vendibile
	 * 				settori:		array
	 * 					id:				number codice settore
	 * 					nome:			string nome settore
	 * 					prezzi:			array
	 * 						id:				number codice prezzo
	 * 						prezzo:			string descrizione
	 * 						tipo:			string [I,R,O] per intero, ridotto, omaggio
	 * 						importo:		decimal importo totale lordo, prevendita inclusa
	 * 						prevendita:		decimal quota parte importo di prevendita
	 * 						iva:			decimal %iva titolo (0.1=10%)
	 * 
	 * Esempio array tipiabbonamenti
	 * 			id:				number codice numerico tipo abbonamento
	 *   		nome			string nome tipo abbonamento
	 * 			codice			string codice alfanumerico tipo abbonamento
	 *			descrizione		string descrizioen tipo abbonamento
	 *			scadenza		number unixtime*1000 dalla scadenza, null=scadenza relativa 
	 *			scadenzarelval	number valore scadenza relativa
	 *			scadenzarelunt	string unità scadenza relativa
	 *			entrate			number numero entrate
	 *			importo			decimal importo totale lordo, prevendita inclusa
	 *			iva				decimal %iva abbonamento (0.1=10%)
	 *			prevendita		decimal quota parte importo di prevendita
	 *			organizzatorecf	string codice fiscale organizzatore
	 * 			stato:			number 1=pubblicato 2=prenotabile 3=vendibile
	 * 			
	 */
	public function getProgrammazione() {
		return $this->_get("programmazione",[]);
	}
	
	/**
	 * Ottiene i posti di una mappa
	 * @return array
	 * 
	 * Esempio oggetto risposta:
	 * 		[{"id":1,"nome":"A1","settore":1,"sottosettore":"1","x":167,"y":80,"classe":0}]
	 * 			id:				number codice posto
	 * 			nome:			string nome posto
	 * 			settore:		codice settore appartenenza
	 * 			sottosettore:	string (opzionale) descrizione sottosettore
	 * 			x:				number posizione coordinata x
	 * 			y:				number posizione coordinata y
	 * 			classe:			number tipologia posto, default 0
	 */
	public function getMappa($idmappa) {
		return $this->_get("mappa",["mappa"=>$idmappa]);
	}
	
	/**
	 * Ottiene lo stato dell'evento e, se a posti numerati, lo stato dei posti
	 * @return object
	 * 
	 * Esempio oggetto risposta:
	 * 		{"idevento":5,"mappa":1,"posti":{"1":1,"11":0},"settori":{"1":{"capienza":100,"residui":99,"limitecapienza":true}}}
	 * 			idevento:		number codice evento
	 * 			mappa:			number codice mappa
	 *          stato:          number stato evento
	 * 			posti:			mappa {id:stato}
	 * 				stato:			stato posto (0=libero, 1=occupato, 2=riservato abbonato, 3=prenotato, 4=bloccato, 5=abbonato non riservato)
	 * 			settori:		mappa {id: oggetto}
	 * 				capienza:		number capienza settore
	 * 				residui:		number posti residui per la vendita online
	 * 				limitecapienza:	boolean indica se il settore ha capienza limitata
	 *              limiteremoto:	boolean indica se il settore ha capienza limitata per vendite online
	 * 			    slots:		mappa {id: oggetto} facoltativo, solo per evento a slot
	 * 		      		capienza:		number capienza settore
	 * 		      		residui:		number posti residui per il settore/slot per la vendita online
	 *                  limiteremoto:	boolean indica se lo slot ha capienza limitata per vendite online
	 */
	public function getStatoEvento($idevento) {
		return $this->_get("statoevento",["evento"=>$idevento]);
	}
	
	/**
	 * Ottiene lo stato dei posti occupati per un tipo abbonamento a posti numerati
	 * @return object
	 *
	 * Esempio oggetto risposta:
	 * 		{"idtipoabbonamento":5,"mappa":1,"posti":{"1":1,"11":0}}
	 * 			idtipoabbonamento:		number tipoabbonemento
	 * 			mappa:			number codice mappa
	 * 			posti:			mappa {id:stato}
	 * 				stato:			stato posto (0=libero, 1=occupato, -1=fuorisettore)
	 *
	 */
	public function getStatoTipoabbonamento($idtipoabbonamento) {
		return $this->_get("statotipoabbonamento",["tipoabbonamento"=>$idtipoabbonamento]);
	}
	
	/**
	 * Verifica se nell'abbonamento è presente del residuo ingressi della quantità indicata
	 * 
	 * @param string $codiceabbonamento		codice tipologia dell'abbonamento
	 * @param int $progressivoabbonamento	progressivo dell'abbonamento
	 * @param int $qta						quantita ingressi da verificare, default=1
	 */
	public function verificaAbbonato($codiceabbonamento,$progressivoabbonamento,$qta=1){
		return $this->_post("verificaAbbonato",[
				"codiceabbonamento"=>$codiceabbonamento,
				"progressivoabbonamento"=>$progressivoabbonamento,
				"qta"=>$qta
		]);
	}
	
	/**
	 * Registrazione di una prenotazione
	 * 
	 * @param int $evento			id dell'evento
	 * @param int $slot			     id dello slot, null se l'evento non è a slot
	 * @param object $anagrafica	(facoltativo) oggetto \Cinebot\Bean\Anagrafica
	 * @param array	 $ingressi		array[obj] oggetti \Cinebot\Bean\Ingresso
	 */
	public function prenotazione($evento,$slot,$anagrafica,$ingressi){
		if($ingressi && !is_array($ingressi))
			$ingressi=[$ingressi];
		return $this->_post("prenotazione",[
				"evento"=>$evento,
		        "slot"=>$slot,
				"anagrafica"=>$anagrafica,
				"ingressi"=>$ingressi
		]);
	}
	
	/**
	 * Registrazione di una blocco
	 *
	 * @param int $evento			id dell'evento
	 * @param int $slot			    id dello slot, null se l'evento non è a slot
	 * @param array	 $ingressi		array[obj] oggetti \Cinebot\Bean\Ingresso, anche senza prezzo indicato
	 */
	public function blocco($evento,$slot,$ingressi){
	    if($ingressi && !is_array($ingressi))
	        $ingressi=[$ingressi];
	        return $this->_post("prenotazione",[
	            "evento"=>$evento,
	            "slot"=>$slot,
	            "ingressi"=>$ingressi
	        ]);
	}
		
	/**
	 * <<<RICHIEDE OMOLOGAZIONE INTEGRAZIONE>>>
	 * Preemissione per emissione in due passaggi, la preemissione non confermata sarà invalitata in 5 minuti
	 *
	 * @param int $evento			id dell'evento, obbligatorio per ingressi
	 * @param int $slot			     id dello slot, null se l'evento non è a slot
	 * @param object $anagrafica		(facoltativo) oggetto Anagrafica
	 * @param array $ingressi			array[obj] oggetti \Cinebot\Bean\Ingresso
	 * @param array $abbonamenti		array[obj] oggetti \Cinebot\Bean\Abbonamento
	 * @param int tipopagamento;
	 * @param string pagamento;
	 * @param string iptransazione;
	 * @param string transazione;
	 * @param long/millisunixtime datacheckout;
	 */
	public function preemissione($evento, $slot, Anagrafica $anagrafica, array $ingressi, array $abbonamenti, int $tipopagamento, string $iptransazione, string $transazione, int $datacheckout){
		if($ingressi && !is_array($ingressi))
			$ingressi=[$ingressi];
		if($abbonamenti && !is_array($abbonamenti))
			$abbonamenti=[$abbonamenti];
		$data=[
			"evento"=>$evento,
		    "slot"=>$slot,
			"anagrafica"=>$anagrafica,
			"ingressi"=>$ingressi,
			"abbonamenti"=>$abbonamenti,
			"tipopagamento"=>$tipopagamento,
			"iptransazione"=>$iptransazione,
			"transazione"=>$transazione,
			"datacheckout"=>$datacheckout,
			"email"=>$anagrafica->email,
			"telefono"=>$anagrafica->telefono,
			"autenticazione"=>$anagrafica->autenticazione,
			"registrazione"=>$anagrafica->registrazione,
			"ipregistrazione"=>$anagrafica->ipregistrazione
		];
		return $this->_post("preemissione",$data);
	}
	
	/**
	 * <<<RICHIEDE OMOLOGAZIONE INTEGRAZIONE>>>
	 * Rinnova la transazione e rimanda la scadenza di una preemissione
	 *
	 * @param array $ingressiid		array[int] id ingressi preemessi da rinnovare
	 * @param array $abbonamentiid	array[int] id abbonamenti preemessi da rinnovare
	 */
	public function rinnovapreemissione($ingressiid,$abbonamentiid){
		if($ingressiid && !is_array($ingressiid))
			$ingressiid=[$ingressiid];
		if($abbonamentiid && !is_array($abbonamentiid))
			$abbonamentiid=[$abbonamentiid];
		return $this->_post("rinnovapreemissione",[
				"ingressi"=>$ingressiid,
				"abbonamenti"=>$abbonamentiid
		]);
	}
	
	/**
	 * <<<RICHIEDE OMOLOGAZIONE INTEGRAZIONE>>>
	 * Nell'emissione in due passaggi conferma l'emissione di un elenco di ingressi e/o abbonamenti preemessi
	 * 
	 * @param array $ingressiid		array[int] id ingressi preemessi da confermare
	 * @param array $abbonamentiid	array[int] id abbonamenti preemessi da confermare
	 * @param string $pagamento
	 * @param long/millisunixtime $datapagamento
	 */
	public function emissione($ingressiid,$abbonamentiid,$pagamento,$datapagamento){
		if($ingressiid && !is_array($ingressiid))
			$ingressiid=[$ingressiid];
		if($abbonamentiid && !is_array($abbonamentiid))
			$abbonamentiid=[$abbonamentiid];
		return $this->_post("emissione",[
				"ingressi"=>$ingressiid,
				"abbonamenti"=>$abbonamentiid,
		        "pagamento"=>$pagamento,
		        "datapagamento"=>$datapagamento		          
		]);
	}
	
	/**
	 * <<<RICHIEDE OMOLOGAZIONE INTEGRAZIONE>>>
	 * Nell'emissione in due passaggi annulla la preemissione di un elenco di ingressi e/o abbonamenti liberando le risorse
	 *
	 * @param array $ingressiid		array[int] id ingressi preemessi da liberare
	 * @param array $abbonamentiid	array[int] id abbonamenti preemessi da liberare
	 */
	public function liberapreemissione($ingressiid,$abbonamentiid){
		if($ingressiid && !is_array($ingressiid))
			$ingressiid=[$ingressiid];
		if($abbonamentiid && !is_array($abbonamentiid))
			$abbonamentiid=[$abbonamentiid];
		return $this->_post("liberaPreemissione",[
				"ingressi"=>$ingressiid,
				"abbonamenti"=>$abbonamentiid
		]);
	}
	
	private function _get($path,$var=[],$timeout=false,$connecttimeout=false){
		if(!$timeout){
			if($this->timeout){
				$timeout=$this->timeout;
			}else{
				$timeout=30;
			}
		}
		if(!$connecttimeout){
			if($this->timeout){
				$connecttimeout=$this->timeout;
			}else{
				$connecttimeout=10;
			}
		}
		set_time_limit(120);
		$url = $this->url.'/remote/'.$path;
		/* DA ELIMINARE APPENA TUTTI I CLIENT PASSANO IN AUTHENTICATION b18*/
		$var["id"]=$this->idpv;
		$var["passkey"]=$this->passkey;
		/* */
		if($var)
			$url.=(strpos("?", $url)!==false?"&":"?").http_build_query($var);
	   if($this->logger)
            $this->logger->debug("GET >> ".$url);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connecttimeout);
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
		$headers=$this->_getHeaders();
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		
		$curl_response = curl_exec($curl);
		$curl_err=curl_error($curl);
		curl_close($curl);
		if($curl_response!==false){
		    if($this->logger)
		        $this->logger->debug("GET << ".$curl_response);
			$obj=json_decode($curl_response);
			if($obj){
    			if($obj->success)
    				return $obj->value;
    			if($this->logger)
    			     $this->logger->error("Errore risposta ".$obj->error);
			     if($obj->exception=="com.cinebot.exception.LogicalException")
			         throw new LogicalException($obj->error);
			     throw new CinebotException($obj->error);
			}else
			    throw new CinebotException(_("Errore di connessione: risposta non valida"));
		}
		else{
		    if($this->logger)
		      $this->logger->error("Errore chiamata ".$curl_err);
	      throw new CinebotException("Errore di connessione: ".$curl_err);
		}
	}
	
	private function _post($path,$var=[],$jsonbody=true,$timeout=false,$connecttimeout=false){
		if(!$timeout){
			if($this->timeout){
				$timeout=$this->timeout;
			}else{
				$timeout=30;
			}
		}
		if(!$connecttimeout){
			if($this->timeout){
				$connecttimeout=$this->timeout;
			}else{
				$connecttimeout=10;
			}
		}
		set_time_limit(120);
		$url = $this->url.'/remote/'.$path;
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connecttimeout);
		curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($curl, CURLOPT_POST, true);
		$headers=$this->_getHeaders();
		if($jsonbody){
			$var = json_encode($var);
			$headers[]='Content-Type:application/json';
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);		
		curl_setopt($curl, CURLOPT_POSTFIELDS, $var);
		if($this->logger)
		    $this->logger->debug("POST>> ".$url.($var?" POST".$var:""));
		$curl_response = curl_exec($curl);
		$curl_err=curl_error($curl);
		curl_close($curl);
		if($curl_response!==false){
			$obj=json_decode($curl_response);
			if($this->logger)
			    $this->logger->debug("POST<< ".$curl_response);
			if($obj->success)
				return $obj->value;
			if($this->logger)
			    $this->logger->error("Errore risposta ".$obj->error);
			if($obj->exception=="com.cinebot.exception.LogicalException")
			    throw new LogicalException($obj->error);
			throw new CinebotException($obj->error);
		}
		else{
			if($this->logger)
			    $this->logger->error("Errore chiamata ".$curl_err);
		    throw new CinebotException(_("Errore di connessione"));
		}
	}
	
	private function _getHeaders() {
		return array(
				'Authorization: Basic '. base64_encode($this->idpv.":".$this->passkey),
				'User-Agent: CinebotApiClient-PHP/'.self::VERSION
		);
	}
}


