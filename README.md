# Cinebot API Client

V2.1.0
Classe di collegamento remoto al sistema fiscale per recupero dati, prenotazione, blocco posti ed emissione titoli per sistemi omologati.


Esempio di utilizzo:


```php
$cac=new \Cinebot\CinebotApiClient("https://1.2.3.4:8443", "2", "secretpasswordkey");
$programmazione=$cac->getProgrammazione();
```