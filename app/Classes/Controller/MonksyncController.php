<?php declare(strict_types = 1);
/**
 * @author Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>, Ergänzung durch Karsten Hannig <dl1tux@t-online.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */
/* vim-Settings
 * set mouse-=a
 * set tabstop=4
 * set shiftwidth=4
 * set smarttab
 */

namespace App\Controller;

use App\Service\Ldap;
use Hengeb\Router\Attribute\AllowIf;
use Hengeb\Router\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

define("MITNR", "mhnmitnr");

class MonksyncController extends Controller {
	private $tracelevel = -1;
	private array $ldapIds =[];
	private array $ldapUsers =[];
	private array $monkUsers =[];
	private array $monkLists =[];
	private	array $stats=[];

	/* 
	 * @throws \InvalidArgumentException wrong number of arguments
	 */ 
	private function trace_r($tlvl,$lin, $txt,$var) {
		if(func_num_args() !=4) {
			throw new \InvalidArgumentException('wrong number of arguments for trace_r called in line '.$lin, __LINE__);
			self::error(__LINE__,'wrong number of arguments for trace_r called in line '.$lin);
		}
		if ($this->tracelevel < $tlvl) return;
		printf("TRACE_R_%-3d %3d : %s\n", $tlvl, $lin, $txt);
		print_r($var);
		printf("\n");
	}

	/* 
	 * @throws \InvalidArgumentException wrong number of arguments
	 */ 
	private function trace($tlvl,$lin, $txt) {
		if(func_num_args() !=3) {
			throw new \InvalidArgumentException('wrong number of arguments for trace_r called in line '.$lin, __LINE__);
			self::error(__LINE__,'wrong number of arguments for trace called in line '.$lin);
		}
		if ($this->tracelevel < 0) {
			if (empty(getenv("MONKSYNCTRACE"))) {
				$this->tracelevel = 0;
			} else {
				$this->tracelevel = intval(getenv("MONKSYNCTRACE"));
				printf("TRACE %d : LEVEL: %d\n",__LINE__, $this->tracelevel);
			}
		}
		if ($this->tracelevel < $tlvl) return;

		printf("TRACE_%-3d %3d : %s\n", $tlvl, $lin, $txt);
	}

	private function error($lin, $txt) {
		printf("ERROR %d : %s\n", $lin, $txt);
	}

	private function isMarkedInvalid(String $mail):bool {
		if ($mail === null
				|| !str_ends_with(strtolower($mail), ".invalid"))
			return false;
		return true;
	}

	private function stripInvalid(String $mail):String {
		if (self::isMarkedInvalid($mail) === false)
			return $mail;
		return substr($mail, 0, -8);
	}

	private function searchLdapByMail(String $mail):int {
		if (empty($mail)) return -1;
		$needle = self::stripInvalid($mail);
		foreach($this->ldapUsers as $key => $val) {

			$hay = self::stripInvalid($val['email']);
			if (strcasecmp($hay, $needle) === 0) {
				return $key;
			}
		}
		return -1;
	}

	private function searchLdapById(int $needleId):int {
		foreach($this->ldapUsers as $key => $val) {
			if($val['id']== $needleId) return $key;
		}
		return -1;
	}

	private function getMonkDefaultListIds() {
		//cURL initialisieren
		$ch =
			curl_init
			(getenv("MONKSYNCMONKBASE")."api/lists?page=1&per_page=10000");

		//Optionen setzen
		curl_setopt_array($ch,[
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_USERPWD => getenv("MONKSYNCAPIKEY"),	//Authentifizierung
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_CUSTOMREQUEST => "GET",	//HTTP-Methode
				CURLOPT_HTTPHEADER => ["Accept: application/json"],
				CURLOPT_SSL_VERIFYPEER => false,//entspricht -k
				CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,	//entspricht -4
				CURLOPT_HEADER => true	//Header mit ausgeben
		]);

		//Anfrage ausführen
		$response = curl_exec($ch);

		//Fehlerprüfung
		if ($response === false) {
			self::error(__LINE__,
					"getMonkDefaultListIds fehlgeschlagen: ".curl_error
					($ch)." : ".getenv("MONKSYNCMONKBASE"));
			curl_close($ch);
			return;
		}
		//HTTP-Statuscode ermitteln
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		//Header und Body trennen
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		//Verbindung schließen
		curl_close($ch);

		//Antwort verarbeiten
		$monkanswer = json_decode($body, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			if ($monkanswer['data'] === null
					|| $monkanswer['data']['results'] === null) {
				self::error(__LINE__,
						"monkanswer enthaelt keine Daten : ".$body);
			} else {
				$monkListsArray = $monkanswer['data']['results'];
				foreach($monkListsArray as $monkElem) {
					if(preg_match("/\\b".$monkElem['name']."\\b/",getenv("MONKSYNCLISTS")))
						$this->monkLists[]=$monkElem['id'];
				}
			}
		}
	}

	private function getLdapData($ldap) {
		//1. Schritt : Daten aus dem ldap lesen 
		$this->ldapUsers = $ldap->getAll();

		//2. Schritt Indizes erzeugen
		foreach($this->ldapUsers as $user) {
			$this->ldapIds[] = intval($user['id']);
		}
		//3. Schritt Indizes sortieren
		if($this->ldapIds !== null && count($this->ldapIds)>0) {
			sort($this->ldapIds);
		}

		self::trace_r(9,__LINE__,"ldapUsers[0]",$this->ldapUsers[0]);
		$this->stats['anzahlLdap']=count($this->ldapUsers);
	}

	private function repairMissingMonkIds():bool {
		for($idx=0;$idx<count($this->monkUsers);$idx++) {
			if(self::isMarkedInvalid($this->monkUsers[$idx]['email'])) $this->stats['invalidMonk']++;
			if (empty($this->monkUsers[$idx]['attribs'][MITNR])) {
				$ldapofs=self::searchLdapByMail($this->monkUsers[$idx]['email']);
				if($ldapofs < 0) {
					self::error(__LINE__, "repairMissingMonkIds Mailadresse in Ldap unbekannt "
							.$this->monkUsers[$idx]['email']
							," (".$this->monkUsers[$idx]['name'].")");
					return false;
				}
				$this->monkUsers[$idx]['attribs'][MITNR]=$this->ldapUsers[$ldapofs]['id'];
				if(self::updateMonkData($idx)!==true) {
					return false;
				}
			}
		}
		return true;
	}
	private function buildMonkIdList() {
		$this->monkIds=array();
		foreach($this->monkUsers as $subscriber) {
			if(empty($subscriber['attribs'][MITNR])) {
				$this->monkIds[] = -1;
			} else {
				$this->monkIds[] = intval($subscriber['attribs'][MITNR]);
			}
		}
		//Indizies sortieren
		if($this->monkIds !== null && count($this->monkIds)>0) {
			sort($this->monkIds);
		}
		$this->stats['anzahlMonk']=count($this->monkUsers);
	}

	private function getMonkData() {
		//cURL initialisieren
		$ch =
			curl_init
			(getenv("MONKSYNCMONKBASE")."api/subscribers?page=1&per_page=10000");

		//Optionen setzen
		curl_setopt_array($ch,[
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_USERPWD => getenv("MONKSYNCAPIKEY"),	//Authentifizierung
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_CUSTOMREQUEST => "GET",	//HTTP-Methode
				CURLOPT_HTTPHEADER => ["Accept: application/json"],
				CURLOPT_SSL_VERIFYPEER => false,//entspricht -k
				CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,	//entspricht -4
				CURLOPT_HEADER => true	//Header mit ausgeben
		]);

		//Anfrage ausführen
		$response = curl_exec($ch);

		//Fehlerprüfung
		if ($response === false) {
			self::error(__LINE__,
					"getMonkData fehlgeschlagen: ".curl_error
					($ch)." : ".getenv("MONKSYNCMONKBASE"));
			curl_close($ch);
			return;
		}
		//HTTP-Statuscode ermitteln
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		//Header und Body trennen
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		//Verbindung schließen
		curl_close($ch);

		//JSON-Antwort verarbeiten
		$this->monkUsers = array();
		$monkanswer = json_decode($body, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			if ($monkanswer['data'] === null
					|| $monkanswer['data']['results'] === null) {
				self::error(__LINE__,
						"monkanswer enthaelt keine Daten : ".$body);
			} else {
				$this->monkUsers = $monkanswer['data']['results'];
			}
		}
		self::trace_r(8,__LINE__, "getMonkData monkUsers[0]=",$this->monkUsers[0]);
	}

	private function searchMonkById(int $idnum):int {
		if ($idnum === null) return -1;
		foreach($this->monkUsers as $key => $val) {
			if ($val['attribs'][MITNR] == $idnum) return $key;
		}
		return -1;
	}

	private function searchMonkIdxByMail(String $mail):int {
		if ($mail === null) return -1;
		$needle = self::stripInvalid($mail);
		for($y=0;$y<count($this->monkUsers);$y++) {
			$hay = self::stripInvalid($this->monkUsers[$y]['email']);
			if (strcasecmp($hay, $needle) === 0) {
				return $y;
			}
		}
		return -1;
	}

	private function updateMonkData(int $monkofs):bool {
		self::trace(8,__LINE__, "updateMonkData monkofs=".$monkofs);
		if($monkofs < 0 || $monkofs > count($this->monkUsers)) {
			self::error(__LINE__, "updateMonkData monkofs invalid:");
			return false;
		}
		$monk = $this->monkUsers[$monkofs];
		if($monk === null) {
			self::error(__LINE__, "updateMonkData monkUsers not found");
			return false;
		}
		self::trace_r(1,__LINE__, "updateMonkData monk=",$monk);

		//curl_ mit Unterstuetzung  openai transkodiert

		//cURL initialisieren
		$ch = curl_init(getenv("MONKSYNCMONKBASE")."api/subscribers/".$monk['id']);

		//Listen muessen umformatiert werden da der Monk sein eigenes Format nicht akzeptiert
		foreach($monk['lists'] as $alist) $currlist[]= $alist['id'];

		//Optionen setzen
		curl_setopt_array($ch,[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERPWD => getenv("MONKSYNCAPIKEY"),	//Authentifizierung
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_CUSTOMREQUEST => "PUT",	//HTTP-Methode
				CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json"],
				CURLOPT_SSL_VERIFYPEER => false,	//entspricht curl -k
				CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,	//entspricht curl -4
				CURLOPT_HEADER => true,	//Header mit ausgeben
				CURLOPT_POSTFIELDS => json_encode([
					"email" => $monk['email'],
					"name" =>  $monk['name'],
					//					"lists" => $monk['lists'],
					"lists" => $currlist,
					//FEHLERSUCHE lists
					"preconfirm_subscriptions" => true,
					"attribs" => [ MITNR => $monk['attribs'][MITNR] ]
				]) 
		]);
		self::trace_r(1,__LINE__,"monkLists=",$this->monkLists);
		self::trace_r(1,__LINE__,"currlist=",$currlist);
		self::trace_r(1,__LINE__,"monk['lists']=",$monk['lists']);

		//Anfrage ausführen
		$response = curl_exec($ch);

		//Prüfen, ob ein Fehler aufgetreten ist
		if ($response === false) {
			self::error(__LINE__, "curl:".curl_error($ch));
			curl_close($ch);
			return false;
		}
		//HTTP-Statuscode ermitteln
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		//Header und Body trennen
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		if ($httpCode !== 200) {
			self::error(__LINE__,$httpCode." ".
					"updateMonkData fehlgeschlagen: ".curl_error
					($ch)." : ".getenv("MONKSYNCMONKBASE")." ".$body."\n");
			self::error(__LINE__,"json_encode:".json_encode($monk));
			self::trace_r(0,__LINE__,"updateMonkData monk=",$monk);
			curl_close($ch);
			return false;
		}

		curl_close($ch);
		self::trace(8,__LINE__, "updateMonkData erfolgreich ".$adr);
		$this->stats['updateMonkData']++;
		return true;
	}

	private function insertLdapInMonk(int $ldapofs):bool {
		//cURL initialisieren
		$ch =
			curl_init
			(getenv("MONKSYNCMONKBASE")."api/subscribers");

		//Optionen setzen
		curl_setopt_array($ch,[
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_USERPWD => getenv("MONKSYNCAPIKEY"),	//Authentifizierung
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_CUSTOMREQUEST => "POST",	//HTTP-Methode
				CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json"],
				CURLOPT_SSL_VERIFYPEER => false,//entspricht -k
				CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,	//entspricht -4
				CURLOPT_HEADER => true,	//Header mit ausgeben
				CURLOPT_POSTFIELDS => json_encode([
					//Das klappt leider nicht					"id" => intval($this->ldapUsers[$ldapofs]['id']), //202511104
					"email" => $this->ldapUsers[$ldapofs]['email'],
					"name" => $this->ldapUsers[$ldapofs]['firstname']." "
					.$this->ldapUsers[$ldapofs]['lastname'],
					"lists" => $this->monkLists,
					"preconfirm_subscriptions" => true,
					"attribs" => [ MITNR => $this->ldapUsers[$ldapofs]['id'] ] 
				])
		]);

		self::trace(8,__LINE__, "insertLdapInMonk ".$adr);
		//Anfrage ausführen
		$response = curl_exec($ch);

		//Fehlerprüfung
		if ($response === false) {
			self::error(__LINE__,
					"insertLdapInMonk fehlgeschlagen: ".curl_error
					($ch)." : ".getenv("MONKSYNCMONKBASE"));
			curl_close($ch);
			return false;
		}
		//HTTP-Statuscode ermitteln
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		//Header und Body trennen
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		if ($httpCode !== 200) {
			self::error(__LINE__,$httpCode." ".
					"insertLdapInMonk fehlgeschlagen: ".curl_error
					($ch)." : ".getenv("MONKSYNCMONKBASE")." ".$body."\n");
			self::trace_r(0,__LINE__, "insertLdapInMonk ",$this->ldapUsers[$ldapofs]);
			curl_close($ch);
			return false;
		}

		curl_close($ch);

		//Ergebnis ausgeben
		self::trace(10,__LINE__, "insertLdapInMonk HTTP-Statuscode: $httpCode");
		self::trace(9,__LINE__, "insertLdapInMonk Header: $header");
		self::trace(8,__LINE__, "insertLdapInMonk Body: $body");
		$monkanswer = json_decode($body, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return false;
		}
		self::trace_r(10,__LINE__, "insertLdapInMonk monkanswer=",$monkanswer);
		$this->stats['insertLdapInMonk']++;

		return true ;
	}

	/**
	 * Durchfuehrung der Syncronisation zwischen dem ldap und dem link-monk
	 */
#[Route('GET /monk-sync'), AllowIf(role: 'newsletter-export')]
	public function show(Ldap $ldap):Response {

		ob_start();
		self::trace(1,__LINE__,"show");
		if (empty(getenv("MONKSYNCAPIKEY"))) {
			self::error(__LINE__,
					"Environment MONKSYNCAPIKEY ist nicht gefiniert");
		} else if (empty(getenv("MONKSYNCMONKBASE"))) {
			self::error(__LINE__,
					"Environment MONKSYNCMONKBASE ist nicht gefiniert");
		} else if (empty(getenv("MONKSYNCLISTS"))) {
			self::error(__LINE__,
					"Environment MONKSYNCLISTS ist nicht gefiniert");
		} else {
			self::getLdapData($ldap);
			self::getMonkDefaultListIds();
			self::getMonkData();
			self::repairMissingMonkIds();
			self::buildMonkIdList();

			if($this->stats['invalidMonk'] > count($this->monkUsers)/2) {
				self::error(__LINE__,
						"Verarbeitung abgebrochen. "
						.$this->stats['invalidMonk']." von "
						.count($this->monkUsers)." Adressen im Monk sind invalid !!!");
			} else if (count($this->ldapUsers) < 1
					|| count($this->monkUsers) < 0) {
				self::error(__LINE__,
						"Verarbeitung abgebrochen. "
						. count($this->ldapUsers)." Daten im Ldap, "
						.count($this->monkUsers)." Daten im Monk");
			} else {
				/**
				 * Es wird ueber alle Ids im LDAP iteriert
				 * Adressen die nicht im LDAP sind werden im Monk auf invalid gesetzt.
				 * $nl iteriert ueber LDAP
				 * $nm iteriert ueber Monk
				 */

				$itM = 0;
				$itL = 0;
				self::trace(1,__LINE__,"########################################################");
				self::trace(1,__LINE__,"countmonkUsers=".count($this->monkUsers)." countldapUsers=".count($this->ldapUsers));

				while ($itM < count($this->monkUsers)
						|| $itL < count($this->ldapUsers)) {
					if ($itL >= count($this->ldapUsers)) {
						$sdiff = -1;
					} else if ($itM >= count($this->monkUsers)) {
						$sdiff = 1;
					} else {
						$sdiff = $this->monkIds[$itM] - $this->ldapIds[$itL];
						self::trace(1,__LINE__,"show itM=".$itM." itL=".$itL);
						self::trace(1,__LINE__,"show diff=".$sdiff);
						self::trace(9,__LINE__, "show ldapId=".$this->ldapIds[$itL]." <--> monkId=".$this->monkIds[$itM]);
					}
					self::trace(1,__LINE__,"show diff=".$sdiff);
					self::trace(9,__LINE__, "show ldapId=".$this->ldapIds[$itL]." <--> monkId=".$this->monkIds[$itM]);
					if ($sdiff > 0) {
						//In Monk nicht vorhanden
						self::trace(9,__LINE__, "In Monk nicht vorhanden ".$this->ldapIds[$itL].  " ".$this->monkIds[$itM]);
						if(self::insertLdapInMonk($itL) != true) break;
						
						$itL++;
					} else if ($sdiff < 0) {
						//In LDAP nicht vorhanden
						self::trace(9,__LINE__, "In LDAP nicht vorhanden monkIds=".$this->monkIds[$itM]);
						$midx=self::searchMonkById(intval($this->monkIds[$itM]));
						self::trace(9,__LINE__, "In LDAP nicht vorhanden midx=".$midx);
						self::trace_r(9,__LINE__, "In LDAP nicht vorhanden monkUsers=",$this->monkUsers[$midx]);
						self::trace(9,__LINE__, "In LDAP nicht vorhanden ".$this->ldapIds[$itL].  " ".$this->monkIds[$itM]);
						if($midx>=0 && !self::isMarkedInvalid($this->monkUsers[$midx]['email']))  {
							$this->monkUsers[$midx]['email'].=".invalid";
							if(self::updateMonkData($midx)===true) $this->stats['setFlagInMonk']++;
							}
						$itM++;
					} else { /* $sdiff==0 */
						//In beiden vorhanden
						self::trace(9,__LINE__, "show gleich ".$this->ldapIds[$itL].  " ".$this->monkIds[$itM]);
						$midx=self::searchMonkById(intval($this->ldapIds[$itL]));
						$lidx=self::searchLdapById(intval($this->monkIds[$itM]));
						if ($midx<0 || $lidx<0) {
							self::error(__LINE__,
									"Ldap-Daten nicht gefunden (".$this->monkIds[$itL].")");
						}
						//Mailadresse in Monk und in Ldap unterschiedlich
						if (strcasecmp(self::stripInvalid($this->monkUsers[$midx]['email'])
									,self::stripInvalid($this->ldapUsers[$lidx]['email']))) {
							self::trace(9,__LINE__, "show midx=".$midx." lidx=".$lidx);
							self::trace(9,__LINE__, "show midx_email=".$this->monkUsers[$midx]['email']);
							self::trace(9,__LINE__, "show this->ldapUsers[$lidx]=".$this->ldapUsers[$lidx]['email']);

							$this->monkUsers[$midx]['email'] =$this->ldapUsers[$lidx]['email'];
							self::trace_r(9,__LINE__, "show this->ldapUsers[$lidx]=",$this->ldapUsers[$lidx]);
							self::trace_r(9,__LINE__, "show monkUsers=",$this->monkUsers[$midx]);
							self::updateMonkData($midx);
						////Mailadresse gleich aber Valid-Kenzeichen unterschiedlich
						} else if ( self::isMarkedInvalid($this->monkUsers[$midx]['email'])
								!=  self::isMarkedInvalid($this->ldapUsers[$lidx]['email'])) {
							self::trace(9,__LINE__, "show midx=".$midx." lidx=".$lidx);
							self::trace(9,__LINE__, "show midx_email=".$this->monkUsers[$midx]['email']);
							self::trace(9,__LINE__, "show this->ldapUsers[$lidx]=".$this->ldapUsers[$lidx]['email']);

							//In Ldap invalid aber in Monk valid --> An monk uebertragen
							if(self::isMarkedInvalid($this->ldapUsers[$lidx]['email'])==true) {
								self::trace(9,__LINE__, "show an Monk midx=".$midx." lidx=".$lidx);
								$this->monkUsers[$midx]['email'] =$this->ldapUsers[$lidx]['email'];
								self::updateMonkData($midx);
								//In Ldap valid aber in Monk invalid --> An ldap uebertragen
							} else if(self::isMarkedInvalid($this->monkUsers[$midx]['email'])==true) {
								self::trace(9,__LINE__, "show an Ldap midx=".$midx." lidx=".$lidx);
								$this->ldapUsers[$lidx]['email'] =$this->monkUsers[$midx]['email'];
								$ldap->modifyUser ($this->ldapUsers[$lidx]['username'],$this->ldapUsers[$lidx]);
								$this->stats['modifyLdap']++;
							}

						}
						$itM++;
						$itL++;
					}
				}
			}
		}

		print_r($this->stats);

		/*3. Kram ausgeben */
		$content = ob_get_contents();
		ob_end_clean();

		$response = new Response($content);
		$response->headers->set('Content-Type',
				'text/plain charset=utf-8');

		return $response;
	}
}
