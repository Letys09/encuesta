<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;

	class IntentoModel {
		private $db;
		private $table = 'enc_intento';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
		}

		public function get($ID_intento) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('ID_intento', $ID_intento)
				->fetch();

			if($this->response->result) { 
				foreach($this->response->result as $field => $value) {
					$this->response->result->$field = utf8_decode($value);
				}

				$this->response->SetResponse(true,' '); 
			} else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getAll($pagina=0, $limite=0) {
			$this->response = new Response();
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->limit("$inicial, $limite")
					->fetchAll();
			}
			foreach($this->response->result as &$intento) {
				foreach($intento as $field => $value) {
					$intento->$field = utf8_decode($value);
				}
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getByUrl($ID_url) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('ID_url', $ID_url)
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function existsByUrlInvitado($ID_url, $invitado_id) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('ID_url', $ID_url)
				->where('invitado_id', $invitado_id)
				->fetch();

			if($this->response->result) {
				$this->response->SetResponse(true, 'registro existente');
			} else {
				$this->response->SetResponse(false, 'no existe registro');
			}

			return $this->response;
		}

		public function add($data) {
			$this->response = new Response();
			$fields = array_keys($data);
			$placeholders = array_map(function($field) { return ':' . $field; }, $fields);
			try{
				$stmt = $this->db->getPdo()->prepare("INSERT INTO $this->table(".implode(', ', $fields).") VALUES(".implode(', ', $placeholders).")");
				$this->response->result = $stmt->execute($data);

				if($this->response->result!=0) { 
					$this->response->result = $this->db->getPdo()->query("SELECT MAX(ID_intento) AS ID_intento FROM $this->table")->fetch()->ID_intento; 
					$this->response->SetResponse(true, 'id del registro: '.$this->response->result); 
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function sendEmail($emailAddress, $subject, $body) {
			require_once './core/defines.php';
			ini_set('memory_limit', '480M');
			set_time_limit(3000);
			$this->response = new Response();
			$mail = new PHPMailer(true);
			try {
				$mail->SMTPDebug = 0;
				$mail->isSMTP();
				$mail->SMTPOptions = array(
					'ssl'=> array(
						'verify_peer' => false,
						'verify_peer_name'=> false,
						'allow_self_signed' => true
					)
				);
				
				$mail->SMTPAuth = true;

				// $mail->SMTPSecure = 'SSL';
				// $mail->Host = 'vps-1457117-x.ferozo.com';
				// $mail->Port = 465;
				
				// $mail->SMTPSecure = 'SSL';
				// $mail->Host = 'a4000319.ferozo.com';
				// $mail->Port = 465;

				$mail->SMTPSecure = 'tls';
				$mail->Host = 'smtp.gmail.com';
				$mail->Port = 587;


				$mail->Username = $_SESSION['mail_username'];
				$mail->Password = $_SESSION['mail_pwd'];

            
            /**********************************************/



			
			// $mail->setFrom('encuestas.atm@trasladosuniversales.com.mx', SITE_NAME);	
			// $mail->setFrom('encuestas.atm@atmexicana.com.mx', SITE_NAME);	
			$mail->setFrom($_SESSION['mail_username'], SITE_NAME);
				
				$mail->addAddress($emailAddress);
				
				$mail->isHTML(true);
				$mail->CharSet = 'UTF-8';
				$mail->Subject = $subject;
				$mail->Body = $body;

				$mail->send();

				unset($mail->Username, $mail->Password);
				$this->response->SetResponse($mail);
			}
			catch (Exception $e) {
				$this->response->SetResponse(false, $e);
			}

			return $this->response;
		}
	}
?>