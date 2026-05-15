<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class RespuestaModel {
		private $db;
		private $table = 'enc_respuesta';
		private $tblResp = 'enc_valor_resp';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
		}

		public function get($ID_respuesta) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where("ID_respuesta", $ID_respuesta)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getByIntento($ID_intento, $ID_pregunta=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where("ID_intento = $ID_intento")
				->where("ID_pregunta ".($ID_pregunta!=0? "=": ">")." $ID_pregunta")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where("ID_intento = $ID_intento")
				->where("ID_pregunta ".($ID_pregunta!=0? "=": ">")." $ID_pregunta")
				->fetch()->total;

			return $this->response->SetResponse(true);
		}

		public function getByPregunta($ID_pregunta) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('ID_pregunta', $ID_pregunta)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where('ID_pregunta', $ID_pregunta)
				->fetch()->total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			$this->response = new Response();
			$fields = array_keys($data);
			$placeholders = array_map(function($field) { return ':' . $field; }, $fields);
			try{
				$stmt = $this->db->getPdo()->prepare("INSERT INTO $this->table(".implode(', ', $fields).") VALUES(".implode(', ', $placeholders).")");
				$this->response->result = $stmt->execute($data);

				if($this->response->result!=0) { 
					$this->response->result = $this->db->getPdo()->query("SELECT MAX(ID_respuesta) AS ID_respuesta FROM $this->table")->fetch()->ID_respuesta; 
					$this->response->SetResponse(true, 'id del registro: '.$this->response->result); 
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function addResp($data) {
			$this->response = new Response();
			try {
				$SQL = $this->db
				->insertInto($this->tblResp, $data)
				->execute();
			
				$this->response->result = $SQL;
				if($this->response->result){
					$this->response->SetResponse(true, 'Registro agregado.'); 
				} else {
					$this->response->SetResponse(false, 'No se pudo agregar el registro '.$this->tblResp); 
				}
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch agregar registro '.$this->tblResp);
			}
				
			return $this->response;
		}
	}
?>